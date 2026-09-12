<?php

use App\Models\Plan;
use App\Models\User;

test('guest is redirected from the subscriber dashboard to subscriber login', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('subscriber can access the themed dashboard and sees their escaped identity', function () {
    $subscriber = User::factory()->subscriber()->create([
        'name' => '<script>alert("subscriber")</script>',
    ]);

    $this->actingAs($subscriber)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<title>Overview', escape: false)
        ->assertSee(e($subscriber->name), escape: false)
        ->assertDontSee($subscriber->name, escape: false)
        ->assertSee('Subscriber Dashboard navigation')
        ->assertSee(asset('theme/assets/css/app.min.css'))
        ->assertSee(asset('css/subscriber-dashboard.css'))
        ->assertSee(asset('theme/assets/libs/apexcharts/apexcharts.min.js'));
});

test('administrator cannot access the subscriber dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('dashboard'))->assertForbidden();
});

test('dashboard never renders another subscribers identity or global plan as tenant data', function () {
    $subscriber = User::factory()->subscriber()->create(['name' => 'Current Subscriber']);
    $otherSubscriber = User::factory()->subscriber()->create(['name' => 'Other Tenant Person']);
    $globalPlan = Plan::factory()->create(['name' => 'Unassigned Global Plan']);

    $this->actingAs($subscriber)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Current Subscriber')
        ->assertDontSee($otherSubscriber->name)
        ->assertDontSee($otherSubscriber->email)
        ->assertDontSee($globalPlan->name)
        ->assertSee('No active plan');
});

test('empty dashboard renders safe zero metrics without division errors', function () {
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('0%')
        ->assertSee('0 open')
        ->assertSee('0 resolved')
        ->assertSee('No conversations yet')
        ->assertSee('No knowledge gaps recorded')
        ->assertSee('No AI answers recorded yet')
        ->assertViewHas('dashboard', function (array $dashboard): bool {
            return count($dashboard['chart']['labels']) === 14
                && count($dashboard['chart']['values']) === 14
                && array_sum($dashboard['chart']['values']) === 0
                && $dashboard['aiResolution']['percentage'] === 0
                && $dashboard['chatbots']['percentage'] === 0;
        });
});

test('unfinished subscriber navigation has no broken module links', function () {
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Live visitors')
        ->assertSee('Bots')
        ->assertSee('Conversations')
        ->assertSee('Team')
        ->assertSee('Billing')
        ->assertDontSee('href="http://localhost:8000/bots"', escape: false)
        ->assertDontSee('href="http://localhost:8000/conversations"', escape: false);
});

test('dashboard uses the existing post-only subscriber logout and session is invalidated', function () {
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('action="'.route('logout').'"', escape: false)
        ->assertSee('method="POST"', escape: false);

    $this->actingAs($subscriber)
        ->withSession(['subscriber-marker' => 'private'])
        ->post(route('logout'))
        ->assertRedirect(route('login'))
        ->assertSessionMissing('subscriber-marker');

    $this->assertGuest();
    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->get(route('logout'))->assertMethodNotAllowed();
});
