<?php

use App\Actions\QueueConversationMessage;
use App\Enums\ConversationHandlingMode;
use App\Enums\ConversationStatus;
use App\Enums\MessageActor;
use App\Enums\MessageStatus;
use App\Enums\WidgetChannel;
use App\Jobs\GenerateConversationReply;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VisitorSession;
use App\Models\Widget;
use App\Services\SendAgentConversationReply;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** @return array{user: User, bot: Bot, subscription: Subscription} */
function createConversationInboxEnvironment(): array
{
    $user = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create(['limits' => array_fill_keys(array_keys(Plan::LIMITS), 0)]);
    $subscription = Subscription::factory()->for($user)->for($plan)->create([
        'plan_snapshot' => $plan->subscriptionSnapshot(),
    ]);
    $bot = Bot::factory()->for($user)->create(['is_active' => true]);

    return compact('user', 'bot', 'subscription');
}

test('guest is redirected and subscriber sees only their tenant conversations', function () {
    $this->get(route('conversations.index'))->assertRedirect(route('login'));
    $own = createConversationInboxEnvironment();
    $other = createConversationInboxEnvironment();
    $ownConversation = Conversation::factory()->for($own['user'])->for($own['bot'])->create([
        'visitor_identifier' => 'VISITOR-OWN1',
        'last_message_preview' => 'Tenant-private preview',
    ]);
    Conversation::factory()->for($other['user'])->for($other['bot'])->create([
        'visitor_identifier' => 'VISITOR-OTHER',
        'last_message_preview' => 'Other tenant secret',
    ]);

    $this->actingAs($own['user'])->get(route('conversations.index'))
        ->assertOk()
        ->assertSee($ownConversation->visitorDisplayName())
        ->assertSee('Tenant-private preview')
        ->assertDontSee('Other tenant secret');
});

test('admin accounts cannot enter the subscriber conversation inbox', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('conversations.index'))
        ->assertForbidden();
});

test('cross tenant detail actions and attachment downloads return not found', function () {
    $owner = createConversationInboxEnvironment();
    $intruder = createConversationInboxEnvironment();
    $conversation = Conversation::factory()->for($owner['user'])->for($owner['bot'])->create([
        'status' => ConversationStatus::OPEN_MANUAL,
        'handling_mode' => ConversationHandlingMode::MANUAL,
    ]);
    $message = ConversationMessage::factory()->for($conversation)->create();
    $attachment = $message->attachments()->make([
        'disk' => 'conversation_attachments', 'path' => 'private/file.txt',
        'original_name' => 'file.txt', 'mime_type' => 'text/plain', 'size' => 10,
    ]);
    $attachment->uuid = (string) Str::uuid();
    $attachment->user_id = $owner['user']->id;
    $attachment->bot_id = $owner['bot']->id;
    $attachment->conversation_id = $conversation->id;
    $attachment->save();

    $this->actingAs($intruder['user'])->get(route('conversations.index', ['conversation' => $conversation->uuid]))->assertNotFound();
    $this->actingAs($intruder['user'])->postJson(route('conversations.messages.store', $conversation), [
        'message' => 'Cross tenant reply', 'idempotency_key' => (string) Str::uuid(),
    ])->assertNotFound();
    $this->actingAs($intruder['user'])->get(route('conversations.attachments.download', [$conversation, $attachment->uuid]))->assertNotFound();
});

test('search filters counts and ordering stay tenant scoped', function () {
    $environment = createConversationInboxEnvironment();
    $older = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create([
        'visitor_identifier' => 'OLDER-ONE', 'last_message_preview' => 'Password help',
        'last_message_at' => now()->subHour(), 'status' => ConversationStatus::OPEN_AI,
    ]);
    $newer = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create([
        'visitor_identifier' => 'NEWER-TWO', 'last_message_preview' => 'Billing question',
        'last_message_at' => now(), 'status' => ConversationStatus::NEEDS_HUMAN,
        'handling_mode' => ConversationHandlingMode::MANUAL,
    ]);
    Conversation::factory()->for($environment['user'])->for($environment['bot'])->create([
        'status' => ConversationStatus::RESOLVED, 'last_message_preview' => 'Resolved subject',
        'last_message_at' => now()->subDay(),
    ]);

    $response = $this->actingAs($environment['user'])->get(route('conversations.index'))
        ->assertOk()->assertViewHas('counts', fn (array $counts) => $counts['all'] === 3 && $counts['open'] === 2 && $counts['manual'] === 1 && $counts['resolved'] === 1);
    $items = $response->viewData('conversations')->items();
    expect($items[0]->id)->toBe($newer->id)->and($items[1]->id)->toBe($older->id);

    $this->actingAs($environment['user'])->get(route('conversations.index', ['q' => 'Billing']))
        ->assertOk()->assertSee('Billing question')->assertDontSee('Password help');
    $this->actingAs($environment['user'])->get(route('conversations.index', ['filter' => 'manual']))
        ->assertOk()->assertSee('Billing question')->assertDontSee('Password help');
});

test('human replies require manual mode store the agent and are idempotent without openai', function () {
    Queue::fake();
    $environment = createConversationInboxEnvironment();
    $conversation = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create([
        'status' => ConversationStatus::OPEN_MANUAL,
        'handling_mode' => ConversationHandlingMode::MANUAL,
    ]);
    ConversationMessage::factory()->for($conversation)->create(['body' => 'Can somebody help?']);
    $key = (string) Str::uuid();
    $payload = ['message' => 'A human reply', 'idempotency_key' => $key];

    $this->actingAs($environment['user'])->postJson(route('conversations.messages.store', $conversation), $payload)
        ->assertCreated()->assertJsonPath('data.message.actor', 'agent');
    $this->actingAs($environment['user'])->postJson(route('conversations.messages.store', $conversation), $payload)
        ->assertCreated();

    $agentReplies = ConversationMessage::query()->where('conversation_id', $conversation->id)->where('actor_type', MessageActor::AGENT)->get();
    expect($agentReplies)->toHaveCount(1)
        ->and($agentReplies->first()->sender_id)->toBe($environment['user']->id)
        ->and($agentReplies->first()->body)->toBe('A human reply');
    Queue::assertNotPushed(GenerateConversationReply::class);
});

test('human reply is rejected in ai mode and while resolved', function () {
    $environment = createConversationInboxEnvironment();
    $ai = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create();
    $resolved = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create([
        'status' => ConversationStatus::RESOLVED,
        'handling_mode' => ConversationHandlingMode::MANUAL,
        'resolved_at' => now(),
    ]);
    $payload = ['message' => 'Should fail', 'idempotency_key' => (string) Str::uuid()];

    $this->actingAs($environment['user'])->postJson(route('conversations.messages.store', $ai), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('message');
    $payload['idempotency_key'] = (string) Str::uuid();
    $this->actingAs($environment['user'])->postJson(route('conversations.messages.store', $resolved), $payload)
        ->assertUnprocessable()->assertJsonValidationErrors('message');
});

test('manual takeover pauses ai and returning control affects only future messages', function () {
    Queue::fake();
    $environment = createConversationInboxEnvironment();
    $conversation = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create();

    $this->actingAs($environment['user'])->patch(route('conversations.mode.update', $conversation), ['mode' => 'manual'])->assertRedirect();
    $manual = app(QueueConversationMessage::class)->execute(
        $environment['user'], $environment['bot'], 'Message during takeover', (string) Str::uuid(), $conversation->uuid,
    );
    expect($manual->message->status)->toBe(MessageStatus::RECEIVED);
    Queue::assertNotPushed(GenerateConversationReply::class);

    $this->actingAs($environment['user'])->patch(route('conversations.mode.update', $conversation), ['mode' => 'ai'])->assertRedirect();
    app(QueueConversationMessage::class)->execute(
        $environment['user'], $environment['bot'], 'Future message', (string) Str::uuid(), $conversation->uuid,
    );
    Queue::assertPushed(GenerateConversationReply::class, 1);
    expect(ConversationMessage::query()->where('body', 'Message during takeover')->count())->toBe(1);
});

test('resolve reopen archive and soft delete follow safe state transitions', function () {
    $environment = createConversationInboxEnvironment();
    $conversation = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create([
        'status' => ConversationStatus::OPEN_MANUAL,
        'handling_mode' => ConversationHandlingMode::MANUAL,
    ]);

    $this->actingAs($environment['user'])->patch(route('conversations.resolve', $conversation))->assertRedirect();
    expect($conversation->fresh()->status)->toBe(ConversationStatus::RESOLVED)
        ->and($conversation->fresh()->resolved_by)->toBe($environment['user']->id);
    $this->actingAs($environment['user'])->patch(route('conversations.reopen', $conversation))->assertRedirect();
    expect($conversation->fresh()->status)->toBe(ConversationStatus::OPEN_MANUAL);
    $this->actingAs($environment['user'])->patch(route('conversations.archive', $conversation))->assertRedirect(route('conversations.index'));
    expect($conversation->fresh()->status)->toBe(ConversationStatus::ARCHIVED)
        ->and($conversation->fresh()->archived_at)->not->toBeNull();
    $this->actingAs($environment['user'])->delete(route('conversations.destroy', $conversation))->assertRedirect(route('conversations.index'));
    $this->assertSoftDeleted('conversations', ['id' => $conversation->id, 'user_id' => $environment['user']->id]);
});

test('handoff enters manual mode and creates an unread system event', function () {
    Notification::fake();
    $environment = createConversationInboxEnvironment();
    $conversation = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create();

    $this->actingAs($environment['user'])->patch(route('conversations.mode.update', $conversation), ['mode' => 'manual'])->assertRedirect();
    $conversation->refresh();
    expect($conversation->status)->toBe(ConversationStatus::OPEN_MANUAL)
        ->and($conversation->effectiveHandlingMode())->toBe(ConversationHandlingMode::MANUAL)
        ->and($conversation->messages()->where('actor_type', MessageActor::SYSTEM)->exists())->toBeTrue();
});

test('safe attachments are private and unsafe files are rejected', function () {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturnUsing(fn (string $directory, UploadedFile $file, string $name): string => $name);
    $disk->shouldReceive('exists')->once()->andReturnTrue();
    $disk->shouldReceive('download')->once()->andReturnUsing(fn (string $path, string $name, array $headers) => response()->streamDownload(fn () => print 'image', $name, $headers));
    $manager = Mockery::mock(FilesystemManager::class);
    $manager->shouldReceive('disk')->andReturn($disk);
    $this->app->instance(FilesystemManager::class, $manager);
    $this->app->instance('filesystem', $manager);
    $environment = createConversationInboxEnvironment();
    $conversation = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create([
        'status' => ConversationStatus::OPEN_MANUAL,
        'handling_mode' => ConversationHandlingMode::MANUAL,
    ]);

    $response = $this->actingAs($environment['user'])->postJson(route('conversations.messages.store', $conversation), [
        'message' => 'Screenshot attached',
        'idempotency_key' => (string) Str::uuid(),
        'attachments' => [UploadedFile::fake()->image('screenshot.png', 20, 20)],
    ])->assertCreated();
    $attachment = $conversation->attachments()->sole();
    expect($attachment->path)->not->toContain('screenshot.png');
    $this->actingAs($environment['user'])->get($response->json('data.message.attachments.0.download_url'))
        ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($environment['user'])->postJson(route('conversations.messages.store', $conversation), [
        'message' => '', 'idempotency_key' => (string) Str::uuid(),
        'attachments' => [UploadedFile::fake()->create('payload.php', 1, 'text/plain')],
    ])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');

    $this->actingAs($environment['user'])->postJson(route('conversations.messages.store', $conversation), [
        'message' => '', 'idempotency_key' => (string) Str::uuid(),
        'attachments' => [UploadedFile::fake()->create(
            'oversized.pdf',
            ((int) config('neuraldesk.conversations.attachment_max_kb')) + 1,
            'application/pdf',
        )],
    ])->assertUnprocessable()->assertJsonValidationErrors('attachments.0');
});

test('incremental messages do not repeat the anchor and message html is escaped', function () {
    $environment = createConversationInboxEnvironment();
    $conversation = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create([
        'status' => ConversationStatus::OPEN_MANUAL,
        'handling_mode' => ConversationHandlingMode::MANUAL,
    ]);
    $first = ConversationMessage::factory()->for($conversation)->create(['body' => '<img src=x onerror=alert(1)>']);
    $second = ConversationMessage::factory()->for($conversation)->create(['body' => 'Second message']);

    $this->actingAs($environment['user'])->get(route('conversations.index', ['conversation' => $conversation->uuid]))
        ->assertOk()->assertSee('&lt;img src=x onerror=alert(1)&gt;', escape: false)
        ->assertDontSee('<img src=x onerror=alert(1)>', escape: false);
    $response = $this->actingAs($environment['user'])->getJson(route('conversations.messages.index', [$conversation, 'after' => $first->uuid]))
        ->assertOk()->assertJsonCount(1, 'data.messages');
    expect($response->json('data.messages.0.uuid'))->toBe($second->uuid);
});

test('agent and ai sender types remain distinct and mark read clears only this conversation', function () {
    $environment = createConversationInboxEnvironment();
    $conversation = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create(['unread_count' => 2]);
    $other = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create(['unread_count' => 3]);
    ConversationMessage::factory()->for($conversation)->create(['actor_type' => MessageActor::AI, 'body' => 'AI answer']);
    ConversationMessage::factory()->for($conversation)->create(['actor_type' => MessageActor::VISITOR, 'body' => 'Visitor answer']);

    $this->actingAs($environment['user'])->postJson(route('conversations.read', $conversation))->assertOk()->assertJsonPath('data.unread_count', 0);
    expect($other->fresh()->unread_count)->toBe(3)
        ->and($conversation->fresh()->messages()->where('actor_type', MessageActor::AI)->exists())->toBeTrue();
});

test('manual agent reply is delivered only to the matching public widget conversation', function () {
    $environment = createConversationInboxEnvironment();
    $widget = Widget::factory()->for($environment['bot'])->create(['user_id' => $environment['user']->id]);
    $token = Str::random(80);
    $session = VisitorSession::factory()->for($widget)->create([
        'user_id' => $environment['user']->id,
        'bot_id' => $environment['bot']->id,
        'token_hash' => hash('sha256', $token),
        'origin' => rtrim((string) config('app.url'), '/'),
        'channel' => WidgetChannel::HOSTED,
    ]);
    $conversation = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create([
        'visitor_session_id' => $session->id,
        'status' => ConversationStatus::OPEN_MANUAL,
        'handling_mode' => ConversationHandlingMode::MANUAL,
    ]);
    app(SendAgentConversationReply::class)->handle($conversation, $environment['user'], 'Private agent reply', (string) Str::uuid(), []);

    $this->withToken($token)->getJson(route('widgets.api.conversations.show', [$widget->public_id, $conversation->uuid]))
        ->assertOk()->assertJsonPath('data.messages.0.actor', 'agent')->assertJsonPath('data.messages.0.body', 'Private agent reply');

    $otherToken = Str::random(80);
    VisitorSession::factory()->for($widget)->create([
        'user_id' => $environment['user']->id, 'bot_id' => $environment['bot']->id,
        'token_hash' => hash('sha256', $otherToken), 'origin' => rtrim((string) config('app.url'), '/'),
    ]);
    $this->withToken($otherToken)->getJson(route('widgets.api.conversations.show', [$widget->public_id, $conversation->uuid]))->assertNotFound();
});

test('subscriber responses never expose embeddings keys prompts or private attachment paths', function () {
    $environment = createConversationInboxEnvironment();
    $conversation = Conversation::factory()->for($environment['user'])->for($environment['bot'])->create();
    ConversationMessage::factory()->for($conversation)->create([
        'metadata' => ['embedding' => [0.1, 0.2], 'api_key' => 'secret-key', 'prompt' => 'hidden prompt'],
    ]);

    $content = $this->actingAs($environment['user'])->getJson(route('conversations.messages.index', $conversation))->assertOk()->getContent();
    expect($content)->not->toContain('embedding')->not->toContain('secret-key')->not->toContain('hidden prompt')->not->toContain('storage/app/private');
});
