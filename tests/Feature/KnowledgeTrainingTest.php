<?php

use App\Enums\KnowledgeSourceStatus;
use App\Enums\KnowledgeSourceType;
use App\Jobs\ProcessKnowledgeSource;
use App\Models\Bot;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function knowledgeSubscription(User $user, array $limits = []): Subscription
{
    $plan = Plan::factory()->create(['limits' => array_replace(array_fill_keys(array_keys(Plan::LIMITS), 0), $limits)]);

    return Subscription::factory()->for($user)->for($plan)->create(['plan_snapshot' => $plan->subscriptionSnapshot()]);
}

function knowledgeSource(User $user, Bot $bot, array $attributes = []): KnowledgeSource
{
    return KnowledgeSource::factory()->create(array_replace([
        'user_id' => $user->id,
        'bot_id' => $bot->id,
        'created_by' => $user->id,
    ], $attributes));
}

beforeEach(function () {
    Queue::fake();
});

test('subscriber can view only their bots training page', function () {
    $owner = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($owner)->create();
    $foreignBot = Bot::factory()->for($other)->create();
    knowledgeSubscription($owner);

    $this->get(route('bots.training.index', $bot))->assertRedirect(route('login'));
    $this->actingAs($owner)->get(route('bots.training.index', $bot))->assertOk()->assertSee('Reliable knowledge');
    $this->actingAs($owner)->get(route('bots.training.index', $foreignBot))->assertNotFound();
});

test('plain text creates an owner and bot scoped queued source', function () {
    $owner = User::factory()->subscriber()->create();
    knowledgeSubscription($owner, ['knowledge_sources_limit' => 2]);
    $bot = Bot::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route('bots.training.text.store', $bot), [
        'name' => 'Refund policy',
        'text' => 'Refunds are available within thirty days.',
        'user_id' => User::factory()->subscriber()->create()->id,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $source = KnowledgeSource::query()->sole();
    expect($source->user_id)->toBe($owner->id)
        ->and($source->bot_id)->toBe($bot->id)
        ->and($source->status)->toBe(KnowledgeSourceStatus::QUEUED);
    Queue::assertPushed(ProcessKnowledgeSource::class, fn ($job) => $job->tenantId === $owner->id && $job->botId === $bot->id);
});

test('knowledge source plan limit is enforced server side', function () {
    $owner = User::factory()->subscriber()->create();
    knowledgeSubscription($owner, ['knowledge_sources_limit' => 1]);
    $bot = Bot::factory()->for($owner)->create();
    knowledgeSource($owner, $bot);

    $this->actingAs($owner)->post(route('bots.training.text.store', $bot), ['text' => 'Another source.'])
        ->assertSessionHasErrors('plan_limit');
    expect($owner->knowledgeSources()->count())->toBe(1);
});

test('valid text file is stored privately and queued', function () {
    Storage::fake('knowledge');
    config()->set('neuraldesk.storage.knowledge_disk', 'knowledge');
    $owner = User::factory()->subscriber()->create();
    knowledgeSubscription($owner);
    $bot = Bot::factory()->for($owner)->create();
    $file = UploadedFile::fake()->createWithContent('guide.txt', 'Private product documentation.');

    $this->actingAs($owner)->post(route('bots.training.files.store', $bot), ['files' => [$file]])
        ->assertRedirect()->assertSessionHasNoErrors();

    $source = KnowledgeSource::query()->sole();
    expect($source->type)->toBe(KnowledgeSourceType::TXT)
        ->and($source->original_filename)->toBe('guide.txt')
        ->and($source->file_path)->not->toContain('guide.txt');
    Storage::disk('knowledge')->assertExists($source->file_path);
});

test('invalid file extension mime and oversized files are rejected', function () {
    Storage::fake('knowledge');
    config()->set('neuraldesk.storage.knowledge_disk', 'knowledge');
    $owner = User::factory()->subscriber()->create();
    knowledgeSubscription($owner);
    $bot = Bot::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route('bots.training.files.store', $bot), [
        'files' => [UploadedFile::fake()->create('payload.php', 1, 'application/x-php')],
    ])->assertSessionHasErrors('files.0');

    $this->actingAs($owner)->post(route('bots.training.files.store', $bot), [
        'files' => [UploadedFile::fake()->create('oversized.pdf', 20481, 'application/pdf')],
    ])->assertSessionHasErrors('files.0');
    $this->assertDatabaseCount('knowledge_sources', 0);
});

test('private and localhost website addresses are rejected before queueing', function (string $url) {
    $owner = User::factory()->subscriber()->create();
    knowledgeSubscription($owner);
    $bot = Bot::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route('bots.training.website.store', $bot), [
        'source_type' => 'website', 'url' => $url, 'page_limit' => 1,
    ])->assertSessionHasErrors('url');
    $this->assertDatabaseCount('knowledge_sources', 0);
})->with(['http://localhost/private', 'http://127.0.0.1/admin', 'http://169.254.169.254/latest/meta-data']);

test('sitemap page limits are validated', function () {
    $owner = User::factory()->subscriber()->create();
    knowledgeSubscription($owner);
    $bot = Bot::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route('bots.training.website.store', $bot), [
        'source_type' => 'sitemap', 'url' => 'http://127.0.0.1/sitemap.xml', 'page_limit' => 999,
    ])->assertSessionHasErrors(['url', 'page_limit']);
});

test('another tenant cannot retry or delete a source', function () {
    $owner = User::factory()->subscriber()->create();
    $attacker = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($owner)->create();
    $source = knowledgeSource($owner, $bot);
    $attackerBot = Bot::factory()->for($attacker)->create();

    $this->actingAs($attacker)->post(route('bots.training.sources.retrain', [$attackerBot, $source]))->assertNotFound();
    $this->actingAs($attacker)->delete(route('bots.training.sources.destroy', [$attackerBot, $source]))->assertNotFound();
    $this->assertDatabaseHas('knowledge_sources', ['id' => $source->id, 'deleted_at' => null]);
});

test('deleting a source removes only its chunks and private file', function () {
    Storage::fake('knowledge');
    config()->set('neuraldesk.storage.knowledge_disk', 'knowledge');
    $owner = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($owner)->create();
    $path = 'user-'.$owner->id.'/bot-'.$bot->id.'/source.txt';
    Storage::disk('knowledge')->put($path, 'private');
    $source = knowledgeSource($owner, $bot, ['file_path' => $path, 'type' => KnowledgeSourceType::TXT]);
    $other = knowledgeSource($owner, $bot);
    KnowledgeChunk::factory()->create(['knowledge_source_id' => $source->id, 'user_id' => $owner->id, 'bot_id' => $bot->id]);
    $otherChunk = KnowledgeChunk::factory()->create(['knowledge_source_id' => $other->id, 'user_id' => $owner->id, 'bot_id' => $bot->id]);

    $this->actingAs($owner)->delete(route('bots.training.sources.destroy', [$bot, $source]))->assertRedirect();

    Storage::disk('knowledge')->assertMissing($path);
    $this->assertSoftDeleted('knowledge_sources', ['id' => $source->id]);
    $this->assertDatabaseMissing('knowledge_chunks', ['knowledge_source_id' => $source->id]);
    $this->assertDatabaseHas('knowledge_chunks', ['id' => $otherChunk->id]);
});

test('training page never exposes api keys extracted content or embeddings', function () {
    config()->set('neuraldesk.ai.openai.api_key', 'top-secret-api-key');
    $owner = User::factory()->subscriber()->create();
    knowledgeSubscription($owner);
    $bot = Bot::factory()->for($owner)->create();
    $source = knowledgeSource($owner, $bot, ['raw_text' => 'private raw source', 'extracted_text' => 'private extracted source']);
    KnowledgeChunk::factory()->create(['knowledge_source_id' => $source->id, 'user_id' => $owner->id, 'bot_id' => $bot->id, 'embedding' => [0.123456, 0.987654, 0.555555]]);

    $this->actingAs($owner)->get(route('bots.training.index', $bot))->assertOk()
        ->assertDontSee('top-secret-api-key')->assertDontSee('private raw source')->assertDontSee('private extracted source')
        ->assertDontSee('0.123456');
});
