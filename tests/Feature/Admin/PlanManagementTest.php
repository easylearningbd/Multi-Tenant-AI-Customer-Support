<?php

use App\Enums\PlanInterval;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;

test('guests are redirected to admin login from plan management routes', function () {
    $plan = Plan::factory()->create();

    $this->get(route('admin.plans.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.plans.create'))->assertRedirect(route('admin.login'));
    $this->post(route('admin.plans.store'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.plans.show', $plan))->assertRedirect(route('admin.login'));
    $this->get(route('admin.plans.edit', $plan))->assertRedirect(route('admin.login'));
    $this->put(route('admin.plans.update', $plan))->assertRedirect(route('admin.login'));
    $this->patch(route('admin.plans.status.update', $plan))->assertRedirect(route('admin.login'));
    $this->delete(route('admin.plans.destroy', $plan))->assertRedirect(route('admin.login'));
});

test('subscribers receive forbidden responses from every plan management route', function () {
    $subscriber = User::factory()->subscriber()->create();
    $plan = Plan::factory()->create();

    $this->actingAs($subscriber)->get(route('admin.plans.index'))->assertForbidden();
    $this->actingAs($subscriber)->get(route('admin.plans.create'))->assertForbidden();
    $this->actingAs($subscriber)->post(route('admin.plans.store'), planPayload())->assertForbidden();
    $this->actingAs($subscriber)->get(route('admin.plans.show', $plan))->assertForbidden();
    $this->actingAs($subscriber)->get(route('admin.plans.edit', $plan))->assertForbidden();
    $this->actingAs($subscriber)->put(route('admin.plans.update', $plan), planPayload())->assertForbidden();
    $this->actingAs($subscriber)->patch(route('admin.plans.status.update', $plan), ['is_active' => false])->assertForbidden();
    $this->actingAs($subscriber)->delete(route('admin.plans.destroy', $plan))->assertForbidden();
});

test('admin can view an empty plan list', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.plans.index'))
        ->assertOk()
        ->assertSee('<title>Subscription Plans', escape: false)
        ->assertSee('No subscription plans')
        ->assertSee('Add Plan');
});

test('plan list is ordered by sort order then name', function () {
    $admin = User::factory()->admin()->create();
    $later = Plan::factory()->create(['name' => 'Later Plan', 'sort_order' => 20]);
    $alpha = Plan::factory()->create(['name' => 'Alpha Plan', 'sort_order' => 10]);
    $zulu = Plan::factory()->create(['name' => 'Zulu Plan', 'sort_order' => 10]);

    $this->actingAs($admin)
        ->get(route('admin.plans.index'))
        ->assertOk()
        ->assertSeeInOrder([$alpha->name, $zulu->name, $later->name]);
});

test('admin can view plan details', function () {
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->trial()->create([
        'name' => 'Evaluation Plan',
        'features' => ['Grounded answers'],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.plans.show', $plan))
        ->assertOk()
        ->assertSee('Evaluation Plan')
        ->assertSee('Grounded answers')
        ->assertSee('Trial duration')
        ->assertSee('Usage Limits');
});

test('admin can create trial monthly and yearly plans', function (string $interval, string $price, ?int $trialDays) {
    $admin = User::factory()->admin()->create();
    $payload = planPayload([
        'name' => ucfirst($interval).' Plan',
        'slug' => $interval.'-plan',
        'interval' => $interval,
        'price' => $price,
        'trial_days' => $trialDays,
    ]);

    $response = $this->actingAs($admin)->post(route('admin.plans.store'), $payload);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'Plan created successfully.');

    $plan = Plan::query()->where('slug', $interval.'-plan')->firstOrFail();
    expect($plan->interval)->toBe(PlanInterval::from($interval))
        ->and($plan->price_minor)->toBe((int) str_replace('.', '', str_pad($price, 5, '0')));

    if ($interval === PlanInterval::TRIAL->value) {
        expect($plan->trial_days)->toBe($trialDays);
    } else {
        expect($plan->trial_days)->toBeNull();
    }
})->with([
    'trial' => [PlanInterval::TRIAL->value, '0.00', 14],
    'monthly' => [PlanInterval::MONTHLY->value, '29.00', null],
    'yearly' => [PlanInterval::YEARLY->value, '290.00', null],
]);

test('blank slugs are generated uniquely from the plan name', function () {
    $admin = User::factory()->admin()->create();
    Plan::factory()->create(['slug' => 'growth-plan']);

    $this->actingAs($admin)->post(route('admin.plans.store'), planPayload([
        'name' => 'Growth Plan',
        'slug' => '',
    ]))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('plans', ['slug' => 'growth-plan-2']);
});

test('features are sanitized and limits are stored as structured data', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.plans.store'), planPayload([
        'features' => " Priority support \n\npriority support\n Analytics ",
        'chatbots_limit' => 5,
        'knowledge_bases_limit' => 0,
        'storage_mb_limit' => 1024,
    ]))->assertSessionHasNoErrors();

    $plan = Plan::query()->where('slug', 'starter-monthly')->firstOrFail();
    expect($plan->features)->toBe(['Priority support', 'Analytics'])
        ->and($plan->limitFor('chatbots_limit'))->toBe(5)
        ->and($plan->limitFor('storage_mb_limit'))->toBe(1024)
        ->and($plan->hasUnlimitedLimit('knowledge_bases_limit'))->toBeTrue();
});

test('custom pricing normalizes the payable amount and disables automated checkout', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.plans.store'), planPayload([
        'price' => '999.00',
        'custom_pricing' => '1',
    ]))->assertSessionHasNoErrors();

    $plan = Plan::query()->where('slug', 'starter-monthly')->firstOrFail();
    expect($plan->price_minor)->toBe(0)
        ->and($plan->formattedPrice())->toBe('Contact Sales')
        ->and($plan->supportsAutomatedCheckout())->toBeFalse();
});

test('duplicate plan slugs are rejected', function () {
    $admin = User::factory()->admin()->create();
    Plan::factory()->create(['slug' => 'taken']);

    $this->actingAs($admin)
        ->post(route('admin.plans.store'), planPayload(['slug' => 'taken']))
        ->assertSessionHasErrorsIn('planForm', 'slug');
});

test('plan validation rejects invalid pricing intervals currencies and limits', function (array $changes, array $errors) {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.plans.store'), planPayload($changes))
        ->assertSessionHasErrorsIn('planForm', $errors);

    expect(Plan::query()->count())->toBe(0);
})->with([
    'invalid interval' => [['interval' => 'weekly'], ['interval']],
    'negative price' => [['price' => '-1.00'], ['price']],
    'negative limit' => [['chatbots_limit' => -1], ['chatbots_limit']],
    'decimal limit' => [['storage_mb_limit' => '1.5'], ['storage_mb_limit']],
    'trial nonzero price' => [['interval' => 'trial', 'trial_days' => 14, 'price' => '1.00'], ['price']],
    'trial missing days' => [['interval' => 'trial', 'trial_days' => null, 'price' => '0.00'], ['trial_days']],
    'unsupported currency' => [['currency' => 'XYZ'], ['currency']],
]);

test('excessive feature payloads are rejected', function () {
    $admin = User::factory()->admin()->create();
    $features = collect(range(1, 31))->map(fn (int $number): string => 'Feature '.$number)->implode("\n");

    $this->actingAs($admin)
        ->post(route('admin.plans.store'), planPayload(['features' => $features]))
        ->assertSessionHasErrorsIn('planForm', 'features');
});

test('admin can update a plan without changing its slug', function () {
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->create(['slug' => 'stable-slug']);

    $this->actingAs($admin)->put(route('admin.plans.update', $plan), planPayload([
        'name' => 'Updated Name',
        'slug' => '',
        'price' => '49.00',
    ]))->assertSessionHasNoErrors();

    expect($plan->refresh()->name)->toBe('Updated Name')
        ->and($plan->slug)->toBe('stable-slug')
        ->and($plan->price_minor)->toBe(4900);
});

test('invalid updates preserve the original record and unrelated input is ignored', function () {
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->create(['name' => 'Original Name']);

    $this->actingAs($admin)->put(route('admin.plans.update', $plan), planPayload([
        'name' => '',
        'role' => 'admin',
        'provider_price_id' => 'malicious',
    ]))->assertSessionHasErrorsIn('planForm', 'name');

    expect($plan->refresh()->name)->toBe('Original Name')
        ->and(array_key_exists('role', $plan->getAttributes()))->toBeFalse()
        ->and(array_key_exists('provider_price_id', $plan->getAttributes()))->toBeFalse();
});

test('admin can activate and deactivate a plan without removing it', function () {
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.plans.status.update', $plan), ['is_active' => false])
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'Plan deactivated successfully.');

    expect($plan->refresh()->is_active)->toBeFalse()
        ->and($plan->supportsAutomatedCheckout())->toBeFalse()
        ->and(Plan::query()->availableForSelection()->whereKey($plan->id)->exists())->toBeFalse();

    $this->actingAs($admin)->patch(route('admin.plans.status.update', $plan), ['is_active' => true]);
    expect($plan->refresh()->is_active)->toBeTrue();
});

test('trial plans never qualify for recurring checkout', function () {
    $trial = Plan::factory()->trial()->create();
    $monthly = Plan::factory()->create();

    expect($trial->supportsAutomatedCheckout())->toBeFalse()
        ->and($monthly->supportsAutomatedCheckout())->toBeTrue()
        ->and(Plan::query()->checkoutEligible()->pluck('id')->all())->toBe([$monthly->id]);
});

test('unused plans can be deleted', function () {
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.plans.destroy', $plan))
        ->assertRedirect(route('admin.plans.index'))
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'Plan deleted successfully.');

    $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
});

test('plans referenced by subscription history cannot be deleted or have commercial terms changed', function () {
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->create();
    $subscription = Subscription::factory()->for($plan)->create([
        'plan_snapshot' => $plan->subscriptionSnapshot(),
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.plans.destroy', $plan))
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'This plan has subscription or billing history. Deactivate it instead of deleting it.');

    $this->actingAs($admin)->put(route('admin.plans.update', $plan), planPayload([
        'name' => $plan->name,
        'slug' => $plan->slug,
        'price' => '99.00',
    ]))->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'warning');

    $this->assertDatabaseHas('plans', ['id' => $plan->id, 'price_minor' => 2900]);
    $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'plan_id' => $plan->id]);
});

test('historical payment references block hard deletion', function () {
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->create();
    Payment::factory()->for($plan)->create(['plan_snapshot' => $plan->subscriptionSnapshot()]);

    $this->actingAs($admin)->delete(route('admin.plans.destroy', $plan));

    $this->assertDatabaseHas('plans', ['id' => $plan->id]);
    $this->assertDatabaseHas('payments', ['plan_id' => $plan->id]);
});

function planPayload(array $overrides = []): array
{
    return array_replace([
        'name' => 'Starter Monthly',
        'slug' => 'starter-monthly',
        'description' => 'For growing support teams.',
        'features' => "Grounded answers\nEmail support",
        'price' => '29.00',
        'currency' => 'USD',
        'interval' => PlanInterval::MONTHLY->value,
        'trial_days' => null,
        'custom_pricing' => '0',
        'is_active' => '1',
        'sort_order' => 10,
        'ai_answers_per_month' => 1000,
        'chatbots_limit' => 1,
        'knowledge_bases_limit' => 1,
        'knowledge_sources_limit' => 25,
        'team_members_limit' => 0,
        'storage_mb_limit' => 0,
    ], $overrides);
}
