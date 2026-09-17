<?php

use App\Enums\SubscriptionStatus;
use App\Models\Bot;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Str;

function createBotPlanSubscription(User $subscriber, int $botLimit = 0): Subscription
{
    $plan = Plan::factory()->create([
        'limits' => array_replace(
            array_fill_keys(array_keys(Plan::LIMITS), 0),
            ['chatbots_limit' => $botLimit],
        ),
    ]);

    return Subscription::factory()
        ->for($subscriber)
        ->for($plan)
        ->create(['plan_snapshot' => $plan->subscriptionSnapshot()]);
}

test('guest is redirected and administrator is forbidden from subscriber bot routes', function () {
    $subscriber = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create();

    $this->get(route('bots.index'))->assertRedirect(route('login'));
    $this->post(route('bots.store'), ['name' => 'Visitor Bot'])->assertRedirect(route('login'));

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('bots.index'))->assertForbidden();
    $this->actingAs($admin)->post(route('bots.store'), ['name' => 'Admin Bot'])->assertForbidden();
    $this->actingAs($admin)->get(route('bots.setup', $bot))->assertForbidden();
});

test('subscriber bot list is tenant scoped and escapes bot identity', function () {
    $subscriber = User::factory()->subscriber()->create();
    $otherSubscriber = User::factory()->subscriber()->create();
    createBotPlanSubscription($subscriber, 5);
    $ownedBot = Bot::factory()->for($subscriber)->create([
        'name' => '<script>alert("bot")</script>',
        'slug' => 'owned-bot',
    ]);
    $otherBot = Bot::factory()->for($otherSubscriber)->create([
        'name' => 'Other Tenant Bot',
        'slug' => 'other-tenant-bot',
    ]);

    $this->actingAs($subscriber)
        ->get(route('bots.index'))
        ->assertOk()
        ->assertSee(e($ownedBot->name), escape: false)
        ->assertDontSee($ownedBot->name, escape: false)
        ->assertSee($ownedBot->slug)
        ->assertDontSee($otherBot->name)
        ->assertDontSee($otherBot->slug)
        ->assertSee('Workspace bot capacity')
        ->assertSee('1 of 5 used')
        ->assertSee('aria-current="page"', escape: false);
});

test('empty bot list renders a professional safe state', function () {
    $subscriber = User::factory()->subscriber()->create();
    $subscription = createBotPlanSubscription($subscriber, 2);

    $this->actingAs($subscriber)
        ->get(route('bots.index'))
        ->assertOk()
        ->assertSee('Create your first support bot')
        ->assertSee('New bot')
        ->assertSee($subscription->planName())
        ->assertSee('0 of 2 used');
});

test('subscriber creates an inactive draft with a unique public id and default settings', function () {
    $subscriber = User::factory()->subscriber()->create();
    createBotPlanSubscription($subscriber, 2);

    $response = $this->actingAs($subscriber)->post(route('bots.store'), [
        'name' => '  Returns   Assistant  ',
        'user_id' => User::factory()->subscriber()->create()->id,
        'public_id' => 'browser-controlled',
        'slug' => 'browser-controlled',
        'is_active' => true,
    ]);

    $bot = Bot::query()->sole();

    $response->assertRedirect(route('bots.settings.edit', $bot));
    expect($bot->user_id)->toBe($subscriber->id)
        ->and($bot->name)->toBe('Returns Assistant')
        ->and($bot->display_name)->toBe('Returns Assistant')
        ->and($bot->slug)->toBe('returns-assistant')
        ->and($bot->public_id)->not->toBe('browser-controlled')
        ->and(Str::isUlid($bot->public_id))->toBeTrue()
        ->and($bot->is_active)->toBeFalse()
        ->and($bot->setting)->not->toBeNull()
        ->and($bot->setting->answer_only_from_knowledge_base)->toBeTrue();

    $this->assertDatabaseCount('bot_settings', 1);
});

test('same bot name receives a deterministic unique owner scoped slug', function () {
    $subscriber = User::factory()->subscriber()->create();
    createBotPlanSubscription($subscriber);

    $this->actingAs($subscriber)->post(route('bots.store'), ['name' => 'Support Assistant']);
    $this->actingAs($subscriber)->post(route('bots.store'), ['name' => 'Support Assistant']);

    expect($subscriber->bots()->orderBy('id')->pluck('slug')->all())
        ->toBe(['support-assistant', 'support-assistant-2']);
});

test('bot creation validates the name and preserves the original database state', function () {
    $subscriber = User::factory()->subscriber()->create();
    createBotPlanSubscription($subscriber, 2);

    $this->actingAs($subscriber)
        ->from(route('bots.index'))
        ->post(route('bots.store'), ['name' => '   '])
        ->assertRedirect(route('bots.index'))
        ->assertSessionHasErrors(['name'], null, 'createBot');

    $this->assertDatabaseCount('bots', 0);
    $this->assertDatabaseCount('bot_settings', 0);
});

test('bot creation requires a subscription that currently grants entitlements', function () {
    $withoutPlan = User::factory()->subscriber()->create();

    $this->actingAs($withoutPlan)
        ->post(route('bots.store'), ['name' => 'No Plan Bot'])
        ->assertRedirect(route('bots.index'))
        ->assertSessionHasErrors(['plan_limit'], null, 'createBot');

    $expiredSubscriber = User::factory()->subscriber()->create();
    $expired = createBotPlanSubscription($expiredSubscriber, 2);
    $expired->update([
        'status' => SubscriptionStatus::EXPIRED,
        'current_period_ends_at' => now()->subDay(),
    ]);

    $this->actingAs($expiredSubscriber)
        ->post(route('bots.store'), ['name' => 'Expired Plan Bot'])
        ->assertSessionHasErrors(['plan_limit'], null, 'createBot');

    $this->assertDatabaseCount('bots', 0);
});

test('bot plan limit is enforced on the server inside the creation action', function () {
    $subscriber = User::factory()->subscriber()->create();
    createBotPlanSubscription($subscriber, 1);
    Bot::factory()->for($subscriber)->create();

    $this->actingAs($subscriber)
        ->post(route('bots.store'), ['name' => 'Over Limit Bot'])
        ->assertRedirect(route('bots.index'))
        ->assertSessionHasErrors(['plan_limit'], null, 'createBot');

    expect($subscriber->bots()->count())->toBe(1);
});

test('zero bot limit remains unlimited and another tenants usage is excluded', function () {
    $subscriber = User::factory()->subscriber()->create();
    $otherSubscriber = User::factory()->subscriber()->create();
    createBotPlanSubscription($subscriber, 0);
    Bot::factory()->for($subscriber)->count(3)->create();
    Bot::factory()->for($otherSubscriber)->count(4)->create();

    $this->actingAs($subscriber)
        ->post(route('bots.store'), ['name' => 'Unlimited Capacity Bot'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($subscriber->bots()->count())->toBe(4)
        ->and($otherSubscriber->bots()->count())->toBe(4);
});

test('another tenants bot does not consume a limited subscribers capacity', function () {
    $subscriber = User::factory()->subscriber()->create();
    $otherSubscriber = User::factory()->subscriber()->create();
    createBotPlanSubscription($subscriber, 1);
    Bot::factory()->for($otherSubscriber)->create();

    $this->actingAs($subscriber)
        ->post(route('bots.store'), ['name' => 'Tenant Safe Bot'])
        ->assertSessionHasNoErrors();

    expect($subscriber->bots()->count())->toBe(1);
});

test('soft deleted bots release capacity while their old slugs remain reserved', function () {
    $subscriber = User::factory()->subscriber()->create();
    createBotPlanSubscription($subscriber, 1);
    $deleted = Bot::factory()->for($subscriber)->create(['slug' => 'archived-helper']);
    $deleted->delete();

    $this->actingAs($subscriber)
        ->post(route('bots.store'), ['name' => 'Archived Helper'])
        ->assertSessionHasNoErrors();

    expect($subscriber->bots()->sole()->slug)->toBe('archived-helper-2');
});

test('bot setup route redirects to the owner scoped settings page', function () {
    $subscriber = User::factory()->subscriber()->create();
    $otherSubscriber = User::factory()->subscriber()->create();
    createBotPlanSubscription($subscriber, 2);
    $owned = Bot::factory()->for($subscriber)->create();
    $other = Bot::factory()->for($otherSubscriber)->create();

    $this->actingAs($subscriber)
        ->get(route('bots.setup', $owned))
        ->assertRedirect(route('bots.settings.edit', $owned));

    $this->actingAs($subscriber)
        ->get(route('bots.setup', $other))
        ->assertNotFound();

    $this->actingAs($subscriber)
        ->get('/bots/'.$owned->id.'/setup')
        ->assertNotFound();
});
