<?php

use App\Models\Bot;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Widget;

function createEmbedPlanSubscription(User $subscriber): Subscription
{
    $plan = Plan::factory()->create([
        'limits' => array_fill_keys(array_keys(Plan::LIMITS), 0),
    ]);

    return Subscription::factory()
        ->for($subscriber)
        ->for($plan)
        ->create(['plan_snapshot' => $plan->subscriptionSnapshot()]);
}

test('guest is redirected and administrator is forbidden from bot embed settings', function () {
    $subscriber = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create();

    $this->get(route('bots.embed.edit', $bot))->assertRedirect(route('login'));
    $this->put(route('bots.embed.update', $bot), [])->assertRedirect(route('login'));

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get(route('bots.embed.edit', $bot))->assertForbidden();
    $this->actingAs($admin)->put(route('bots.embed.update', $bot), [])->assertForbidden();
});

test('subscriber can view an owner scoped embed page and receives one stable widget', function () {
    $subscriber = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create(['is_active' => true]);

    $first = $this->actingAs($subscriber)->get(route('bots.embed.edit', $bot));
    $widget = Widget::query()->sole();

    $first->assertOk()
        ->assertSee('Embed &amp; Share', escape: false)
        ->assertSee($widget->public_id)
        ->assertSee('data-neuraldesk-widget=&quot;'.$widget->public_id.'&quot;', escape: false)
        ->assertSee('Widget configured')
        ->assertSee('Open the chat page')
        ->assertSee('See it live on a demo site')
        ->assertDontSee('data-user')
        ->assertDontSee('data-bot-id')
        ->assertDontSee('OPENAI_API_KEY')
        ->assertDontSee('embedding');

    $this->actingAs($subscriber)->get(route('bots.embed.edit', $bot))->assertOk();

    $this->assertDatabaseCount('widgets', 1);
    expect(Widget::query()->sole()->public_id)->toBe($widget->public_id);
});

test('subscriber cannot view or update another tenants embed settings', function () {
    $subscriber = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create();
    $otherBot = Bot::factory()->for($other)->create();

    $this->actingAs($subscriber)
        ->get('/bots/'.$otherBot->public_id.'/embed')
        ->assertNotFound();

    $this->actingAs($subscriber)
        ->put('/bots/'.$otherBot->public_id.'/embed', [
            'is_enabled' => true,
            'accent_color' => '#6259E8',
            'position' => 'bottom_right',
            'welcome_message' => 'Compromised',
        ])
        ->assertNotFound();

    $this->assertDatabaseCount('widgets', 0);
});

test('subscriber updates only approved widget appearance fields', function () {
    $subscriber = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create();
    $widget = Widget::factory()->for($bot)->create(['user_id' => $subscriber->id]);
    $originalPublicId = $widget->public_id;

    $this->actingAs($subscriber)
        ->put(route('bots.embed.update', $bot), [
            'is_enabled' => '0',
            'accent_color' => '#08b9e8',
            'position' => 'bottom_left',
            'welcome_message' => '  Welcome to our support chat.  ',
            'user_id' => $other->id,
            'bot_id' => Bot::factory()->for($other)->create()->id,
            'public_id' => 'browser-controlled',
        ])
        ->assertRedirect(route('bots.embed.edit', $bot))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.type', 'success');

    $widget->refresh();
    expect($widget->is_enabled)->toBeFalse()
        ->and($widget->accent_color)->toBe('#08B9E8')
        ->and($widget->position->value)->toBe('bottom_left')
        ->and($widget->welcome_message)->toBe('Welcome to our support chat.')
        ->and($widget->user_id)->toBe($subscriber->id)
        ->and($widget->bot_id)->toBe($bot->id)
        ->and($widget->public_id)->toBe($originalPublicId);
});

test('widget appearance rejects unapproved values without changing the record', function () {
    $subscriber = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create();
    $widget = Widget::factory()->for($bot)->create(['user_id' => $subscriber->id]);

    $this->actingAs($subscriber)
        ->from(route('bots.embed.edit', $bot))
        ->put(route('bots.embed.update', $bot), [
            'is_enabled' => '1',
            'accent_color' => '#FFFFFF',
            'position' => 'center',
            'welcome_message' => '',
        ])
        ->assertRedirect(route('bots.embed.edit', $bot))
        ->assertSessionHasErrors(['accent_color', 'position', 'welcome_message'], null, 'widgetAppearance');

    expect($widget->fresh()->only(['accent_color', 'position', 'welcome_message']))
        ->toBe($widget->only(['accent_color', 'position', 'welcome_message']));
});

test('widget preview escapes subscriber controlled content', function () {
    $subscriber = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($subscriber)->create();
    Widget::factory()->for($bot)->create([
        'user_id' => $subscriber->id,
        'welcome_message' => '<img src=x onerror=alert(1)>',
    ]);

    $this->actingAs($subscriber)
        ->get(route('bots.embed.edit', $bot))
        ->assertOk()
        ->assertSee('&lt;img src=x onerror=alert(1)&gt;', escape: false)
        ->assertDontSee('<img src=x onerror=alert(1)>', escape: false);
});

test('bot creation provisions default settings and one widget atomically', function () {
    $subscriber = User::factory()->subscriber()->create();
    createEmbedPlanSubscription($subscriber);

    $this->actingAs($subscriber)
        ->post(route('bots.store'), ['name' => 'Public Support'])
        ->assertSessionHasNoErrors();

    $bot = Bot::query()->sole();
    $widget = Widget::query()->sole();

    expect($widget->user_id)->toBe($subscriber->id)
        ->and($widget->bot_id)->toBe($bot->id)
        ->and($widget->public_id)->not->toBeEmpty()
        ->and($bot->setting)->not->toBeNull();
});

test('bot creation uses safe widget defaults when newly deployed configuration is missing', function () {
    $subscriber = User::factory()->subscriber()->create();
    createEmbedPlanSubscription($subscriber);
    config()->set('neuraldesk.widgets.default_position');
    config()->set('neuraldesk.widgets.default_accent_color');
    config()->set('neuraldesk.widgets.default_welcome_message');

    $this->actingAs($subscriber)
        ->post(route('bots.store'), ['name' => 'Resilient Support'])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast.type', 'success');

    $widget = Widget::query()->sole();
    expect($widget->position->value)->toBe('bottom_right')
        ->and($widget->accent_color)->toBe('#6259E8')
        ->and($widget->welcome_message)->not->toBeEmpty();
});

test('bots list reports configured widget count and links to embed settings', function () {
    $subscriber = User::factory()->subscriber()->create();
    createEmbedPlanSubscription($subscriber);
    $bot = Bot::factory()->for($subscriber)->create();
    Widget::factory()->for($bot)->create(['user_id' => $subscriber->id]);

    $this->actingAs($subscriber)
        ->get(route('bots.index'))
        ->assertOk()
        ->assertSee(route('bots.embed.edit', $bot), escape: false)
        ->assertSeeInOrder(['Widgets', '1']);
});
