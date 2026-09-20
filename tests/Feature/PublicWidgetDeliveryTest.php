<?php

use App\Enums\ConversationStatus;
use App\Enums\PrechatFieldType;
use App\Enums\WidgetChannel;
use App\Jobs\GenerateConversationReply;
use App\Models\Bot;
use App\Models\BotPrechatField;
use App\Models\BotSetting;
use App\Models\BotStarterQuestion;
use App\Models\Conversation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\VisitorSession;
use App\Models\Widget;
use App\Models\WidgetDomain;
use App\Notifications\ConversationHandoffRequestedNotification;
use App\Services\PublicWidgetAccessProof;
use App\Services\WidgetOriginPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/** @return array{user: User, bot: Bot, widget: Widget, subscription: Subscription} */
function createPublicWidgetEnvironment(array $setting = [], array $widget = []): array
{
    $user = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create([
        'limits' => array_fill_keys(array_keys(Plan::LIMITS), 0),
    ]);
    $subscription = Subscription::factory()->for($user)->for($plan)->create([
        'plan_snapshot' => $plan->subscriptionSnapshot(),
    ]);
    $bot = Bot::factory()->for($user)->create(['is_active' => true]);
    BotSetting::factory()->for($bot)->create($setting);
    $publicWidget = Widget::factory()->for($bot)->create([
        'user_id' => $user->id,
        ...$widget,
    ]);

    return ['user' => $user, 'bot' => $bot, 'widget' => $publicWidget, 'subscription' => $subscription];
}

/** @return array{token: string, uuid: string} */
function createPublicWidgetSession(TestCase $test, Widget $widget, ?string $origin = null, WidgetChannel $channel = WidgetChannel::HOSTED): array
{
    $origin ??= app(WidgetOriginPolicy::class)->applicationOrigin();
    $proof = app(PublicWidgetAccessProof::class)->issue($widget, $origin, $channel);
    $response = $test->postJson(route('widgets.api.sessions.store', $widget->public_id), [
        'access_proof' => $proof,
    ])->assertCreated();

    return [
        'token' => $response->json('data.session.token'),
        'uuid' => $response->json('data.session.uuid'),
    ];
}

test('dependency free loader and public pages expose only safe widget data', function () {
    ['bot' => $bot, 'widget' => $widget] = createPublicWidgetEnvironment(
        widget: ['welcome_message' => '<script>alert(1)</script>'],
    );

    $loader = $this->get(route('widgets.loader.show', $widget->public_id));
    $loader->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
        ->assertHeader('Cross-Origin-Resource-Policy', 'cross-origin')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertSee('document.createElement(\'iframe\')', escape: false)
        ->assertSee('sandbox', escape: false)
        ->assertDontSee('OPENAI_API_KEY')
        ->assertDontSee('embedding');

    $this->get(route('widgets.hosted.show', $widget->public_id))
        ->assertOk()
        ->assertSee($bot->display_name)
        ->assertSee('css/neuraldesk-widget.css?v=', escape: false)
        ->assertSee('js/neuraldesk-widget.js?v=', escape: false)
        ->assertDontSee('<script>alert(1)</script>', escape: false)
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->get(route('widgets.demo.show', $widget->public_id))
        ->assertOk()
        ->assertSee(route('widgets.loader.show', $widget->public_id), escape: false)
        ->assertSee('External-site simulation');
});

test('inactive widgets bots and subscriptions are unavailable publicly', function () {
    $inactiveWidget = createPublicWidgetEnvironment(widget: ['is_enabled' => false]);
    $this->get(route('widgets.loader.show', $inactiveWidget['widget']->public_id))->assertNotFound();

    $inactiveBot = createPublicWidgetEnvironment();
    $inactiveBot['bot']->forceFill(['is_active' => false])->save();
    $this->get(route('widgets.hosted.show', $inactiveBot['widget']->public_id))->assertNotFound();

    $expired = createPublicWidgetEnvironment();
    $expired['subscription']->forceFill([
        'current_period_ends_at' => now('UTC')->subMinute(),
        'ends_at' => now('UTC')->subMinute(),
    ])->save();
    $this->get(route('widgets.hosted.show', $expired['widget']->public_id))->assertNotFound();
});

test('embedded frame requires an exact allowed origin and emits a restrictive policy', function () {
    ['user' => $user, 'bot' => $bot, 'widget' => $widget] = createPublicWidgetEnvironment();
    $domain = new WidgetDomain(['origin' => 'https://shop.example.com']);
    $domain->user_id = $user->id;
    $domain->bot_id = $bot->id;
    $domain->widget_id = $widget->id;
    $domain->save();

    $allowed = $this->withHeader('Referer', 'https://shop.example.com/products/one')
        ->get(route('widgets.frame.show', $widget->public_id))
        ->assertOk();
    expect((string) $allowed->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'self'")
        ->toContain('https://shop.example.com');

    $this->withHeader('Referer', 'https://evil.example.com/')
        ->get(route('widgets.frame.show', $widget->public_id))
        ->assertForbidden();
    $this->get(route('widgets.frame.show', $widget->public_id))->assertForbidden();
});

test('subscriber manages normalized origins only for their widget', function () {
    ['user' => $user, 'bot' => $bot, 'widget' => $widget] = createPublicWidgetEnvironment();

    $this->actingAs($user)->put(route('bots.embed.update', $bot), [
        'is_enabled' => '1',
        'accent_color' => '#6259E8',
        'position' => 'bottom_right',
        'welcome_message' => 'Welcome',
        'allowed_origins_text' => "https://SHOP.example.com/\nhttps://shop.example.com\nhttp://localhost:3000",
    ])->assertSessionHasNoErrors();

    expect($widget->domains()->pluck('origin')->sort()->values()->all())->toBe([
        'http://localhost:3000',
        'https://shop.example.com',
    ]);

    $this->actingAs($user)->from(route('bots.embed.edit', $bot))->put(route('bots.embed.update', $bot), [
        'is_enabled' => '1',
        'accent_color' => '#6259E8',
        'position' => 'bottom_right',
        'welcome_message' => 'Welcome',
        'allowed_origins_text' => 'https://shop.example.com/path',
    ])->assertSessionHasErrors('allowed_origins.0', errorBag: 'widgetAppearance');

    expect($widget->domains()->count())->toBe(2);
});

test('bootstrap creates a hashed expiring session and returns configured starter and prechat data', function () {
    ['bot' => $bot, 'widget' => $widget] = createPublicWidgetEnvironment([
        'prechat_enabled' => true,
        'offer_human_handoff' => true,
    ]);
    BotStarterQuestion::factory()->for($bot)->create(['question' => 'Where is my order?', 'position' => 1]);
    BotPrechatField::factory()->for($bot)->create([
        'key' => 'email',
        'label' => 'Email address',
        'type' => PrechatFieldType::EMAIL,
        'is_required' => true,
        'position' => 1,
    ]);

    $session = createPublicWidgetSession($this, $widget);
    $stored = VisitorSession::query()->sole();

    expect($session['token'])->toHaveLength(80)
        ->and($stored->token_hash)->toBe(hash('sha256', $session['token']))
        ->and($stored->token_hash)->not->toBe($session['token'])
        ->and($stored->channel)->toBe(WidgetChannel::HOSTED);

    $proof = app(PublicWidgetAccessProof::class)->issue($widget, app(WidgetOriginPolicy::class)->applicationOrigin(), WidgetChannel::HOSTED);
    $response = $this->postJson(route('widgets.api.sessions.store', $widget->public_id), ['access_proof' => $proof]);
    $response->assertCreated()
        ->assertJsonPath('data.widget.starter_questions.0', 'Where is my order?')
        ->assertJsonPath('data.widget.prechat.fields.0.key', 'email')
        ->assertJsonPath('data.widget.handoff_available', true);
    expect($response->getContent())->not->toContain('OPENAI_API_KEY')
        ->not->toContain('embedding')
        ->not->toContain('token_hash');
});

test('access proofs are bound to one widget and reject tampering', function () {
    $first = createPublicWidgetEnvironment();
    $second = createPublicWidgetEnvironment();
    $proof = app(PublicWidgetAccessProof::class)->issue($first['widget'], app(WidgetOriginPolicy::class)->applicationOrigin(), WidgetChannel::HOSTED);

    $this->postJson(route('widgets.api.sessions.store', $second['widget']->public_id), ['access_proof' => $proof])
        ->assertUnprocessable();
    $this->postJson(route('widgets.api.sessions.store', $first['widget']->public_id), ['access_proof' => $proof.'changed'])
        ->assertUnprocessable();
    $this->assertDatabaseCount('visitor_sessions', 0);
});

test('required prechat is enforced and saved encrypted before messages can be queued', function () {
    Queue::fake();
    ['bot' => $bot, 'widget' => $widget] = createPublicWidgetEnvironment(['prechat_enabled' => true]);
    BotPrechatField::factory()->for($bot)->create([
        'key' => 'email',
        'label' => 'Email',
        'type' => PrechatFieldType::EMAIL,
        'is_required' => true,
        'position' => 1,
    ]);
    $session = createPublicWidgetSession($this, $widget);
    $messagePayload = ['message' => 'Help me', 'idempotency_key' => (string) Str::uuid()];

    $this->withToken($session['token'])->postJson(route('widgets.api.messages.store', $widget->public_id), $messagePayload)
        ->assertUnprocessable()->assertJsonValidationErrors('prechat');
    $this->withToken($session['token'])->postJson(route('widgets.api.prechat.store', $widget->public_id), [
        'fields' => ['email' => 'not-an-email'],
    ])->assertUnprocessable()->assertJsonValidationErrors('fields.email');
    $this->withToken($session['token'])->postJson(route('widgets.api.prechat.store', $widget->public_id), [
        'fields' => ['email' => 'visitor@example.com'],
    ])->assertOk()->assertJsonPath('data.completed', true);

    $raw = (string) DB::table('visitor_sessions')->value('prechat_data');
    expect($raw)->not->toContain('visitor@example.com');

    $this->withToken($session['token'])->postJson(route('widgets.api.messages.store', $widget->public_id), $messagePayload)
        ->assertAccepted();
    Queue::assertPushed(GenerateConversationReply::class);
});

test('public messages persist tenant scoped conversations and idempotently queue rag work', function () {
    Queue::fake();
    ['user' => $user, 'bot' => $bot, 'widget' => $widget] = createPublicWidgetEnvironment();
    $session = createPublicWidgetSession($this, $widget);
    $idempotencyKey = (string) Str::uuid();
    $payload = ['message' => 'How do I reset my password?', 'idempotency_key' => $idempotencyKey];

    $first = $this->withToken($session['token'])->postJson(route('widgets.api.messages.store', $widget->public_id), $payload)
        ->assertAccepted();
    $payload['conversation_uuid'] = $first->json('data.conversation_uuid');
    $second = $this->withToken($session['token'])->postJson(route('widgets.api.messages.store', $widget->public_id), $payload)
        ->assertOk();

    expect($second->json('data.message_uuid'))->toBe($first->json('data.message_uuid'));
    $conversation = Conversation::query()->sole();
    expect($conversation->user_id)->toBe($user->id)
        ->and($conversation->bot_id)->toBe($bot->id)
        ->and($conversation->visitor_session_id)->toBe(VisitorSession::query()->sole()->id)
        ->and($conversation->channel)->toBe(WidgetChannel::HOSTED->value)
        ->and($conversation->messages()->count())->toBe(1);
    $this->assertDatabaseCount('usage_ledgers', 1);
    Queue::assertPushed(GenerateConversationReply::class, 1);
});

test('visitor tokens cannot read or append another session or tenant conversation', function () {
    Queue::fake();
    $first = createPublicWidgetEnvironment();
    $firstSession = createPublicWidgetSession($this, $first['widget']);
    $firstMessage = $this->withToken($firstSession['token'])->postJson(
        route('widgets.api.messages.store', $first['widget']->public_id),
        ['message' => 'Private question', 'idempotency_key' => (string) Str::uuid()],
    )->assertAccepted();

    $otherSession = createPublicWidgetSession($this, $first['widget']);
    $conversationUuid = $firstMessage->json('data.conversation_uuid');
    $this->withToken($otherSession['token'])
        ->getJson(route('widgets.api.conversations.show', [$first['widget']->public_id, $conversationUuid]))
        ->assertNotFound();
    $this->withToken($otherSession['token'])->postJson(route('widgets.api.messages.store', $first['widget']->public_id), [
        'message' => 'Cross-session write',
        'idempotency_key' => (string) Str::uuid(),
        'conversation_uuid' => $conversationUuid,
    ])->assertNotFound();

    $secondTenant = createPublicWidgetEnvironment();
    $this->withToken($firstSession['token'])
        ->getJson(route('widgets.api.conversations.show', [$secondTenant['widget']->public_id, $conversationUuid]))
        ->assertNotFound();
});

test('public handoff transitions only the visitor session conversation and keeps visitor messaging in manual mode', function () {
    Queue::fake();
    Notification::fake();
    ['user' => $user, 'widget' => $widget] = createPublicWidgetEnvironment(['offer_human_handoff' => true]);
    $session = createPublicWidgetSession($this, $widget);
    $message = $this->withToken($session['token'])->postJson(route('widgets.api.messages.store', $widget->public_id), [
        'message' => 'I need a person',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertAccepted();

    $this->withToken($session['token'])->postJson(route('widgets.api.handoff.store', $widget->public_id), [
        'conversation_uuid' => $message->json('data.conversation_uuid'),
    ])->assertOk()->assertJsonPath('data.status', ConversationStatus::NEEDS_HUMAN->value);

    $conversation = Conversation::query()->sole();
    $handoffRequestedAt = $conversation->handoff_requested_at;
    expect($conversation->fresh()->status)->toBe(ConversationStatus::NEEDS_HUMAN)
        ->and($conversation->fresh()->handoff_requested_at)->not->toBeNull();
    $this->withToken($session['token'])->postJson(route('widgets.api.messages.store', $widget->public_id), [
        'message' => 'AI must not answer this',
        'idempotency_key' => (string) Str::uuid(),
        'conversation_uuid' => $conversation->uuid,
    ])->assertAccepted()
        ->assertJsonPath('data.conversation_status', ConversationStatus::NEEDS_HUMAN->value);

    $this->withToken($session['token'])->postJson(route('widgets.api.handoff.store', $widget->public_id), [
        'conversation_uuid' => $conversation->uuid,
    ])->assertOk()->assertJsonPath('data.status', ConversationStatus::NEEDS_HUMAN->value);

    expect($conversation->fresh()->handoff_requested_at->equalTo($handoffRequestedAt))->toBeTrue();
    $this->assertDatabaseCount('conversation_messages', 3);
    $this->assertDatabaseCount('usage_ledgers', 1);
    Queue::assertPushed(GenerateConversationReply::class, 1);
    Notification::assertSentTo($user, ConversationHandoffRequestedNotification::class);
});

test('widget session creation is rate limited by public widget and address', function () {
    config()->set('neuraldesk.widgets.bootstrap_rate_per_minute', 1);
    ['widget' => $widget] = createPublicWidgetEnvironment();
    $proofs = app(PublicWidgetAccessProof::class);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->postJson(route('widgets.api.sessions.store', $widget->public_id), [
            'access_proof' => $proofs->issue($widget, app(WidgetOriginPolicy::class)->applicationOrigin(), WidgetChannel::HOSTED),
        ])->assertCreated();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.50'])
        ->postJson(route('widgets.api.sessions.store', $widget->public_id), [
            'access_proof' => $proofs->issue($widget, app(WidgetOriginPolicy::class)->applicationOrigin(), WidgetChannel::HOSTED),
        ])->assertTooManyRequests();
});
