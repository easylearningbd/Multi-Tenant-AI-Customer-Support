<?php

use App\Enums\MessageActor;
use App\Enums\MessageStatus;
use App\Enums\PlanInterval;
use App\Enums\SubscriptionStatus;
use App\Enums\UsageLedgerStatus;
use App\Enums\UsageType;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\KnowledgeSource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\UsageCounter;
use App\Models\UsageLedger;
use App\Models\User;
use App\Services\AiAnswerUsageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function trackedUsageSubscription(User $owner, array $limits = [], array $overrides = []): Subscription
{
    $planInterval = $overrides['plan_interval'] ?? PlanInterval::MONTHLY;
    unset($overrides['plan_interval']);
    $plan = Plan::factory()->create([
        'interval' => $planInterval,
        'limits' => array_replace(array_fill_keys(array_keys(Plan::LIMITS), 0), $limits),
    ]);

    return Subscription::factory()->for($owner)->for($plan)->create(array_replace([
        'status' => SubscriptionStatus::ACTIVE,
        'starts_at' => now('UTC')->subDay(),
        'current_period_starts_at' => now('UTC')->subDay(),
        'current_period_ends_at' => now('UTC')->addMonth(),
        'plan_snapshot' => $plan->subscriptionSnapshot(),
    ], $overrides));
}

function committedAiUsage(User $owner, Bot $bot, Subscription $subscription, CarbonImmutable $committedAt): UsageLedger
{
    $ledger = new UsageLedger;
    $ledger->uuid = (string) Str::uuid();
    $ledger->user_id = $owner->id;
    $ledger->bot_id = $bot->id;
    $ledger->subscription_id = $subscription->id;
    $ledger->plan_id = $subscription->plan_id;
    $ledger->fill([
        'event_key' => hash('sha256', (string) Str::uuid()),
        'type' => UsageType::AI_ANSWER,
        'status' => UsageLedgerStatus::COMMITTED,
        'quantity' => 1,
        'period_starts_at' => $subscription->current_period_starts_at,
        'period_ends_at' => $subscription->current_period_ends_at,
        'committed_at' => $committedAt,
    ]);
    $ledger->save();

    return $ledger;
}

test('billing displays real tenant scoped plan usage and actual knowledge storage', function () {
    $owner = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create();
    $subscription = trackedUsageSubscription($owner, [
        'ai_answers_per_month' => 10,
        'chatbots_limit' => 2,
        'knowledge_bases_limit' => 2,
        'knowledge_sources_limit' => 5,
        'team_members_limit' => 0,
        'storage_mb_limit' => 5,
    ]);
    $firstBot = Bot::factory()->for($owner)->create();
    Bot::factory()->for($owner)->create();
    Bot::factory()->inactive()->for($owner)->create();
    $source = KnowledgeSource::factory()->create([
        'user_id' => $owner->id,
        'bot_id' => $firstBot->id,
        'created_by' => $owner->id,
        'file_size_bytes' => 1572864,
    ]);
    KnowledgeSource::factory()->create([
        'user_id' => $owner->id,
        'bot_id' => $firstBot->id,
        'created_by' => $owner->id,
        'file_size_bytes' => 0,
    ]);
    committedAiUsage($owner, $firstBot, $subscription, CarbonImmutable::now('UTC'));

    $foreignBot = Bot::factory()->for($other)->create();
    KnowledgeSource::factory()->create([
        'user_id' => $other->id,
        'bot_id' => $foreignBot->id,
        'created_by' => $other->id,
        'file_size_bytes' => 4194304,
    ]);

    $this->actingAs($owner)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('1 / 10')
        ->assertSee('2 / 2')
        ->assertSee('2 / 5')
        ->assertSee('0 / Unlimited')
        ->assertSee('1.50 MB / 5.00 MB')
        ->assertDontSee('4.00 MB / 5.00 MB');

    expect($source->fresh()->file_size_bytes)->toBe(1572864);
});

test('unlimited and over limit usage render safely with real totals', function () {
    $owner = User::factory()->subscriber()->create();
    trackedUsageSubscription($owner, ['chatbots_limit' => 1, 'knowledge_bases_limit' => 0]);
    Bot::factory()->count(2)->for($owner)->create();

    $this->actingAs($owner)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('2 / 1')
        ->assertSee('is-danger', false)
        ->assertSee('0 / Unlimited');
});

test('AI quota reservation is atomic idempotent and consumed exactly once', function () {
    $owner = User::factory()->subscriber()->create();
    trackedUsageSubscription($owner, ['ai_answers_per_month' => 1]);
    $bot = Bot::factory()->for($owner)->create();
    $conversation = Conversation::factory()->create(['user_id' => $owner->id, 'bot_id' => $bot->id]);
    $firstMessage = ConversationMessage::factory()->create([
        'user_id' => $owner->id,
        'bot_id' => $bot->id,
        'conversation_id' => $conversation->id,
        'actor_type' => MessageActor::VISITOR,
        'status' => MessageStatus::QUEUED,
    ]);
    $secondMessage = ConversationMessage::factory()->create([
        'user_id' => $owner->id,
        'bot_id' => $bot->id,
        'conversation_id' => $conversation->id,
        'actor_type' => MessageActor::VISITOR,
        'status' => MessageStatus::QUEUED,
    ]);
    $usage = app(AiAnswerUsageService::class);

    $first = $usage->reserve($owner, $bot, $conversation, $firstMessage);
    $duplicate = $usage->reserve($owner, $bot, $conversation, $firstMessage);

    expect($duplicate->id)->toBe($first->id)
        ->and(UsageLedger::query()->count())->toBe(1)
        ->and(UsageCounter::query()->sole()->reserved)->toBe(1);
    expect(fn () => $usage->reserve($owner, $bot, $conversation, $secondMessage))
        ->toThrow(PlanLimitExceededException::class);

    $usage->commit($first, 'test-model', 10, 5);
    $usage->commit($first, 'test-model', 10, 5);

    expect($first->fresh()->status)->toBe(UsageLedgerStatus::COMMITTED)
        ->and(UsageCounter::query()->sole()->used)->toBe(1)
        ->and(UsageCounter::query()->sole()->reserved)->toBe(0);
});

test('failed AI work releases the reservation and consumes no answer quota', function () {
    $owner = User::factory()->subscriber()->create();
    trackedUsageSubscription($owner, ['ai_answers_per_month' => 1]);
    $bot = Bot::factory()->for($owner)->create();
    $conversation = Conversation::factory()->create(['user_id' => $owner->id, 'bot_id' => $bot->id]);
    $firstMessage = ConversationMessage::factory()->create(['user_id' => $owner->id, 'bot_id' => $bot->id, 'conversation_id' => $conversation->id]);
    $secondMessage = ConversationMessage::factory()->create(['user_id' => $owner->id, 'bot_id' => $bot->id, 'conversation_id' => $conversation->id]);
    $usage = app(AiAnswerUsageService::class);

    $first = $usage->reserve($owner, $bot, $conversation, $firstMessage);
    $usage->release($first);
    $second = $usage->reserve($owner, $bot, $conversation, $secondMessage);

    expect($first->fresh()->status)->toBe(UsageLedgerStatus::RELEASED)
        ->and($second->status)->toBe(UsageLedgerStatus::RESERVED)
        ->and(UsageCounter::query()->sole()->used)->toBe(0)
        ->and(UsageCounter::query()->sole()->reserved)->toBe(1);
});

test('yearly subscriptions use monthly anniversary windows for AI usage', function () {
    $this->travelTo('2026-09-21 12:00:00');
    $owner = User::factory()->subscriber()->create();
    $subscription = trackedUsageSubscription($owner, ['ai_answers_per_month' => 10], [
        'plan_interval' => PlanInterval::YEARLY,
        'starts_at' => '2026-07-15 10:00:00',
        'current_period_starts_at' => '2026-07-15 10:00:00',
        'current_period_ends_at' => '2027-07-15 10:00:00',
    ]);
    $bot = Bot::factory()->for($owner)->create();
    committedAiUsage($owner, $bot, $subscription, CarbonImmutable::parse('2026-08-20 10:00:00', 'UTC'));
    committedAiUsage($owner, $bot, $subscription, CarbonImmutable::parse('2026-09-20 10:00:00', 'UTC'));

    $this->actingAs($owner)->get(route('billing.index'))->assertOk()->assertSee('1 / 10');
});

test('plan upgrades retain monthly AI usage and reuse the tenant period counter', function () {
    $owner = User::factory()->subscriber()->create();
    $old = trackedUsageSubscription($owner, ['ai_answers_per_month' => 2]);
    $bot = Bot::factory()->for($owner)->create();
    committedAiUsage($owner, $bot, $old, CarbonImmutable::now('UTC'));
    $conversation = Conversation::factory()->create(['user_id' => $owner->id, 'bot_id' => $bot->id]);
    $message = ConversationMessage::factory()->create(['user_id' => $owner->id, 'bot_id' => $bot->id, 'conversation_id' => $conversation->id]);
    $anchor = $old->current_period_starts_at;

    $old->forceFill(['status' => SubscriptionStatus::CANCELED, 'ends_at' => now('UTC')->subSecond()])->save();
    $new = trackedUsageSubscription($owner, ['ai_answers_per_month' => 2], [
        'metadata' => ['usage_period_anchor' => $anchor->toIso8601String()],
    ]);

    $ledger = app(AiAnswerUsageService::class)->reserve($owner, $bot, $conversation, $message);

    expect($ledger->subscription_id)->toBe($new->id)
        ->and(UsageCounter::query()->count())->toBe(1)
        ->and(UsageCounter::query()->sole()->subscription_id)->toBe($new->id)
        ->and(UsageCounter::query()->sole()->used)->toBe(1)
        ->and(UsageCounter::query()->sole()->reserved)->toBe(1);
});

test('usage reconciliation is tenant scoped and idempotent', function () {
    $owner = User::factory()->subscriber()->create();
    trackedUsageSubscription($owner, ['chatbots_limit' => 5]);
    Bot::factory()->count(2)->for($owner)->create();

    $this->artisan('usage:reconcile', ['--tenant' => $owner->id])->assertSuccessful();
    $first = UsageCounter::query()->where('user_id', $owner->id)->where('metric', 'chatbots_limit')->sole();
    $this->artisan('usage:reconcile', ['--tenant' => $owner->id])->assertSuccessful();

    expect($first->used)->toBe(2)
        ->and(UsageCounter::query()->where('user_id', $owner->id)->count())->toBe(count(Plan::LIMITS))
        ->and(UsageCounter::query()->where('user_id', $owner->id)->where('metric', 'chatbots_limit')->sole()->used)->toBe(2);
});

test('expired subscriptions preserve data but block new protected usage', function () {
    $owner = User::factory()->subscriber()->create();
    $subscription = trackedUsageSubscription($owner, ['chatbots_limit' => 2], [
        'status' => SubscriptionStatus::TRIALING,
        'trial_ends_at' => now('UTC')->subMinute(),
        'current_period_ends_at' => now('UTC')->subMinute(),
    ]);
    $existing = Bot::factory()->for($owner)->create();

    $this->actingAs($owner)->post(route('bots.store'), ['name' => 'Blocked bot'])
        ->assertRedirect(route('bots.index'))
        ->assertSessionHasErrors('plan_limit', errorBag: 'createBot');

    expect($existing->fresh())->not->toBeNull()
        ->and($subscription->fresh()->grantsEntitlements())->toBeFalse()
        ->and($owner->bots()->count())->toBe(1);
});

test('knowledge uploads cannot exceed remaining byte accurate storage quota', function () {
    Storage::fake('knowledge');
    Queue::fake();
    config()->set('neuraldesk.storage.knowledge_disk', 'knowledge');
    $owner = User::factory()->subscriber()->create();
    trackedUsageSubscription($owner, [
        'knowledge_sources_limit' => 10,
        'storage_mb_limit' => 1,
    ]);
    $bot = Bot::factory()->for($owner)->create();
    KnowledgeSource::factory()->create([
        'user_id' => $owner->id,
        'bot_id' => $bot->id,
        'created_by' => $owner->id,
        'file_size_bytes' => 921600,
    ]);

    $this->actingAs($owner)->post(route('bots.training.files.store', $bot), [
        'files' => [UploadedFile::fake()->create('too-large-for-plan.txt', 200, 'text/plain')],
    ])->assertRedirect()->assertSessionHasErrors('plan_limit');

    expect($owner->knowledgeSources()->count())->toBe(1)
        ->and(UsageLedger::query()->where('type', UsageType::KNOWLEDGE_STORAGE)->count())->toBe(0);
});

test('downgrading preserves existing resources and blocks additional creation', function () {
    $owner = User::factory()->subscriber()->create();
    $old = trackedUsageSubscription($owner, ['chatbots_limit' => 5, 'knowledge_bases_limit' => 5]);
    Bot::factory()->count(2)->for($owner)->create();
    $old->forceFill(['status' => SubscriptionStatus::CANCELED, 'ends_at' => now('UTC')->subSecond()])->save();
    trackedUsageSubscription($owner, ['chatbots_limit' => 1, 'knowledge_bases_limit' => 1]);

    $this->actingAs($owner)->post(route('bots.store'), ['name' => 'Over downgrade limit'])
        ->assertRedirect(route('bots.index'))
        ->assertSessionHasErrors('plan_limit', errorBag: 'createBot');

    expect($owner->bots()->count())->toBe(2);
    $this->actingAs($owner)->get(route('billing.index'))->assertOk()->assertSee('2 / 1');
});

test('soft deleted resources immediately leave authoritative displayed usage', function () {
    $owner = User::factory()->subscriber()->create();
    trackedUsageSubscription($owner, [
        'chatbots_limit' => 5,
        'knowledge_bases_limit' => 5,
        'knowledge_sources_limit' => 5,
        'storage_mb_limit' => 5,
    ]);
    $bot = Bot::factory()->for($owner)->create();
    $source = KnowledgeSource::factory()->create([
        'user_id' => $owner->id,
        'bot_id' => $bot->id,
        'created_by' => $owner->id,
        'file_size_bytes' => 1048576,
    ]);
    $source->delete();
    $bot->delete();

    $this->actingAs($owner)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('0 / 5')
        ->assertSee('0 B / 5.00 MB');
});
