<?php

use App\Models\User;

test('guests are redirected from the admin dashboard to admin login', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.login'));
});

test('subscribers cannot access the admin dashboard', function () {
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('admins can access the dashboard successfully', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('admin dashboard renders its title and reusable theme shell', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('<title>Super Admin Dashboard', escape: false)
        ->assertSee('Revenue Overview')
        ->assertSee('Payment Health')
        ->assertSee('Recent Activity')
        ->assertSee('Support Tickets')
        ->assertSee(asset('theme/assets/css/app.min.css'))
        ->assertSee(asset('css/admin.css'));
});

test('admin dashboard contains the secure admin logout form', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('action="'.route('admin.logout').'"', escape: false)
        ->assertSee('method="POST"', escape: false);
});

test('admin dashboard safely renders empty unimplemented module states', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('No revenue data yet')
        ->assertSee('No payment activity')
        ->assertSee('No login activity source')
        ->assertSee('No support ticket data');
});

test('admin names are escaped in the dashboard header', function () {
    $admin = User::factory()->admin()->create([
        'name' => '<script>alert("admin")</script>',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee(e($admin->name), escape: false)
        ->assertDontSee($admin->name, escape: false);
});

test('subscriber dashboard behavior remains isolated from the admin theme', function () {
    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($subscriber)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Subscriber Dashboard');

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertForbidden();
});
