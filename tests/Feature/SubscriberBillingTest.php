<?php

use App\Actions\AssignDefaultTrial;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanLimitService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Carbon;

function billingSubscription(User $user, Plan $plan, array $overrides = []): Subscription
{
    return Subscription::factory()
        ->for($user)
        ->for($plan)
        ->create(array_replace([
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now()->subDay(),
            'current_period_starts_at' => now()->subDay(),
            'current_period_ends_at' => now()->addMonth(),
            'provider' => 'test',
            'provider_subscription_id' => fake()->uuid(),
            'plan_snapshot' => $plan->subscriptionSnapshot(),
        ], $overrides));
}

test('billing page enforces subscriber authentication and role access', function () {
    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();

    $this->get(route('billing.index'))->assertRedirect(route('login'));
    $this->actingAs($admin)->get(route('billing.index'))->assertForbidden();
    $this->actingAs($subscriber)->get(route('billing.index'))->assertOk();
});

test('registration assigns the configured active trial by slug and configured duration', function () {
    $this->travelTo(Carbon::parse('2026-09-14 10:00:00'));
    config()->set('billing.default_trial_plan_slug', 'configured-trial');
    $wrongPlan = Plan::factory()->trial()->create(['slug' => 'free-trial', 'trial_days' => 3]);
    $configuredPlan = Plan::factory()->trial()->create([
        'slug' => 'configured-trial',
        'trial_days' => 21,
        'is_active' => true,
    ]);

    $this->post('/register', [
        'name' => 'Billing Owner',
        'email' => 'billing-owner@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect('/dashboard');

    $user = User::query()->where('email', 'billing-owner@example.test')->sole();
    $subscription = $user->subscriptions()->sole();

    expect($subscription->plan_id)->toBe($configuredPlan->id)
        ->and($subscription->plan_id)->not->toBe($wrongPlan->id)
        ->and($subscription->status)->toBe(SubscriptionStatus::TRIALING)
        ->and($subscription->starts_at->toDateTimeString())->toBe('2026-09-14 10:00:00')
        ->and($subscription->trial_ends_at->toDateTimeString())->toBe('2026-10-05 10:00:00')
        ->and($subscription->current_period_ends_at->equalTo($subscription->trial_ends_at))->toBeTrue()
        ->and($user->trial_claimed_at)->not->toBeNull();
});

test('default trial assignment is idempotent and survives deleted trial history', function () {
    $plan = Plan::factory()->trial()->create([
        'slug' => config('billing.default_trial_plan_slug'),
        'is_active' => true,
    ]);
    $subscriber = User::factory()->subscriber()->create();
    $assign = app(AssignDefaultTrial::class);

    $first = $assign->handle($subscriber);
    $second = $assign->handle($subscriber->refresh());

    expect($first?->id)->toBe($second?->id)
        ->and($subscriber->subscriptions()->count())->toBe(1)
        ->and($first?->plan_id)->toBe($plan->id);

    $first?->delete();
    expect($assign->handle($subscriber->refresh()))->toBeNull()
        ->and($subscriber->subscriptions()->count())->toBe(0);
});

test('expired trial history cannot grant a second trial', function () {
    $plan = Plan::factory()->trial()->create(['slug' => config('billing.default_trial_plan_slug')]);
    $subscriber = User::factory()->subscriber()->create(['trial_claimed_at' => now()->subMonth()]);
    $expired = billingSubscription($subscriber, $plan, [
        'status' => SubscriptionStatus::TRIALING,
        'trial_ends_at' => now()->subDay(),
        'current_period_ends_at' => now()->subDay(),
        'trial_claim_key' => 'user:'.$subscriber->id.':default-trial',
    ]);

    $result = app(AssignDefaultTrial::class)->handle($subscriber);

    expect($result?->id)->toBe($expired->id)
        ->and($subscriber->subscriptions()->count())->toBe(1);
});

test('admins do not receive subscriber trials from the registration event', function () {
    Plan::factory()->trial()->create(['slug' => config('billing.default_trial_plan_slug')]);
    $admin = User::factory()->admin()->create();

    event(new Registered($admin));

    expect($admin->subscriptions()->count())->toBe(0)
        ->and($admin->refresh()->trial_claimed_at)->toBeNull();
});

test('missing inactive or paid default plans roll registration back safely', function (string $state) {
    if ($state === 'inactive') {
        Plan::factory()->trial()->inactive()->create(['slug' => config('billing.default_trial_plan_slug')]);
    }

    if ($state === 'paid') {
        Plan::factory()->create(['slug' => config('billing.default_trial_plan_slug'), 'is_active' => true]);
    }

    $this->from('/register')->post('/register', [
        'name' => 'No Partial Account',
        'email' => 'no-partial@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect('/register')->assertSessionHasErrors('email');

    $this->assertDatabaseMissing('users', ['email' => 'no-partial@example.test']);
    $this->assertDatabaseCount('subscriptions', 0);
})->with(['missing', 'inactive', 'paid']);

test('billing renders the real trial plan snapshot features limits and expiration label', function () {
    $subscriber = User::factory()->subscriber()->create(['trial_claimed_at' => now()]);
    $plan = Plan::factory()->trial()->create([
        'name' => 'Research Trial',
        'slug' => 'research-trial',
        'features' => ['Private source citations', '<script>unsafe</script>'],
        'limits' => [
            'ai_answers_per_month' => 750,
            'chatbots_limit' => 2,
            'knowledge_bases_limit' => 0,
            'knowledge_sources_limit' => 15,
            'team_members_limit' => 3,
            'storage_mb_limit' => 512,
        ],
    ]);
    billingSubscription($subscriber, $plan, [
        'status' => SubscriptionStatus::TRIALING,
        'trial_ends_at' => now()->addDays(14),
        'current_period_ends_at' => now()->addDays(14),
        'trial_claim_key' => 'user:'.$subscriber->id.':default-trial',
    ]);

    $this->actingAs($subscriber)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('<title>Billing', false)
        ->assertSee('Research Trial')
        ->assertSee('Trial ends on')
        ->assertSee('Private source citations')
        ->assertSee('&lt;script&gt;unsafe&lt;/script&gt;', false)
        ->assertDontSee('<script>unsafe</script>', false)
        ->assertSee('0 / Unlimited')
        ->assertSee('0 / 750')
        ->assertSee('Current plan');
});

test('active canceled and expired subscriptions use accurate effective status and date labels', function (SubscriptionStatus $storedStatus, array $dates, string $label, string $statusLabel) {
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create(['name' => 'Lifecycle Plan']);
    $dates = collect($dates)
        ->map(fn (mixed $value): mixed => $value instanceof Closure ? $value() : $value)
        ->all();
    billingSubscription($subscriber, $plan, [
        'status' => $storedStatus,
        ...$dates,
    ]);

    $this->actingAs($subscriber)->get(route('billing.index'))
        ->assertOk()
        ->assertSee($label)
        ->assertSee($statusLabel);
})->with([
    'active' => [SubscriptionStatus::ACTIVE, ['current_period_ends_at' => fn () => now()->addMonth()], 'Renews on', 'Active'],
    'canceled with access' => [SubscriptionStatus::CANCELED, ['current_period_ends_at' => fn () => now()->addWeek(), 'canceled_at' => fn () => now()], 'Access ends on', 'Canceled'],
    'stale active is expired' => [SubscriptionStatus::ACTIVE, ['current_period_ends_at' => fn () => now()->subDay()], 'Expired on', 'Expired'],
]);

test('missing subscription and invoices render safe empty states without screenshot data', function () {
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('No active plan')
        ->assertSee('No invoices yet.')
        ->assertSee('Your payment history will appear here after your first successful purchase.')
        ->assertDontSee('Growth plan subscription');
});

test('billing resolves only the authenticated owners subscription', function () {
    $subscriber = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create();
    $ownPlan = Plan::factory()->inactive()->create(['name' => 'Private Owner Plan']);
    $otherPlan = Plan::factory()->inactive()->create(['name' => 'Other Tenant Secret Plan']);
    billingSubscription($subscriber, $ownPlan);
    billingSubscription($other, $otherPlan);

    $response = $this->actingAs($subscriber)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Private Owner Plan')
        ->assertDontSee('Other Tenant Secret Plan');

    expect($response->viewData('billing')['billingOwnerId'])->toBe($subscriber->id)
        ->and($response->viewData('billing')['subscription']->user_id)->toBe($subscriber->id);
});

test('available plans are active ordered database records with safe checkout states', function () {
    $subscriber = User::factory()->subscriber()->create();
    $later = Plan::factory()->create(['name' => 'Later Paid Plan', 'sort_order' => 20, 'price_minor' => 4500]);
    $first = Plan::factory()->create(['name' => 'First Paid Plan', 'sort_order' => 5, 'price_minor' => 1900]);
    $custom = Plan::factory()->create(['name' => 'Enterprise Contact', 'sort_order' => 10, 'custom_pricing' => true]);
    $inactive = Plan::factory()->inactive()->create(['name' => 'Hidden Inactive Plan']);

    $this->actingAs($subscriber)->get(route('billing.index'))
        ->assertOk()
        ->assertSeeInOrder([$first->name, $custom->name, $later->name])
        ->assertSee('USD 19.00')
        ->assertSee('Bank transfer is not available for this plan.')
        ->assertSee('Contact sales')
        ->assertDontSee($inactive->name);

    $this->actingAs($subscriber)->post('/billing', ['plan_id' => $first->id])->assertMethodNotAllowed();
    expect(Subscription::query()->count())->toBe(0);
});

test('a previously used non-current trial is disabled on the plan list', function () {
    $subscriber = User::factory()->subscriber()->create(['trial_claimed_at' => now()->subMonth()]);
    Plan::factory()->trial()->create(['name' => 'One Time Trial']);

    $this->actingAs($subscriber)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Trial used')
        ->assertSee('The introductory trial can only be used once.');
});

test('subscription snapshots preserve terms after an administrator edits the plan', function () {
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create([
        'name' => 'Original Plan Name',
        'features' => ['Original entitlement'],
        'limits' => array_replace(array_fill_keys(array_keys(Plan::LIMITS), 0), ['chatbots_limit' => 4]),
    ]);
    billingSubscription($subscriber, $plan);
    $plan->update(['name' => 'Renamed Plan', 'features' => ['Changed later']]);

    $this->actingAs($subscriber)->get(route('billing.index'))
        ->assertOk()
        ->assertSee('Original Plan Name')
        ->assertSee('Original entitlement')
        ->assertSee('0 / 4');
});

test('plan limit service denies expired subscriptions and safely supports unlimited active limits', function () {
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create();
    $expired = billingSubscription($subscriber, $plan, [
        'status' => SubscriptionStatus::TRIALING,
        'trial_ends_at' => now()->subSecond(),
        'current_period_ends_at' => now()->subSecond(),
    ]);
    $active = billingSubscription($subscriber, $plan, [
        'provider_subscription_id' => fake()->uuid(),
        'plan_snapshot' => array_replace_recursive($plan->subscriptionSnapshot(), [
            'limits' => ['chatbots_limit' => 0],
        ]),
    ]);
    $limits = app(PlanLimitService::class);

    expect($limits->allows($expired, 'chatbots_limit', 0))->toBeFalse()
        ->and($limits->allows($active, 'chatbots_limit', 999999))->toBeTrue();
});
