<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

test('admin login screen can be rendered', function () {
    $response = $this->get('/admin/login');

    $response
        ->assertOk()
        ->assertSee('Secure admin access')
        ->assertDontSee('Register');
});

test('admins can authenticate using the admin login screen', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($admin);
    $response->assertRedirect('/admin/dashboard');
});

test('admin login preserves remember me support', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $response->assertCookie(Auth::guard('web')->getRecallerName());
});

test('admins cannot authenticate with invalid credentials', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
});

test('subscriber credentials are rejected by the admin login screen', function () {
    $subscriber = User::factory()->subscriber()->create();

    $response = $this->post('/admin/login', [
        'email' => $subscriber->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
});

test('admin login is throttled after repeated failures', function () {
    $admin = User::factory()->admin()->create();
    Event::fake([Lockout::class]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post('/admin/login', [
        'email' => $admin->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    Event::assertDispatched(Lockout::class);
});

test('authenticated admins can access the admin dashboard', function () {
    $admin = User::factory()->admin()->create([
        'name' => '<script>alert("admin")</script>',
    ]);

    $response = $this->actingAs($admin)->get('/admin/dashboard');

    $response
        ->assertOk()
        ->assertSee('Super Admin Dashboard')
        ->assertSee(e($admin->name), escape: false)
        ->assertDontSee($admin->name, escape: false);
});

test('guests are redirected from the admin dashboard to admin login', function () {
    $this->get('/admin/dashboard')->assertRedirect('/admin/login');
});

test('subscribers are forbidden from admin routes for every available method', function () {
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)->get('/admin/dashboard')->assertForbidden();
    $this->actingAs($subscriber)->post('/admin/logout')->assertForbidden();
});

test('authenticated accounts are redirected away from admin login to their role home', function () {
    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($subscriber)->get('/admin/login')->assertRedirect('/dashboard');
    $this->actingAs($admin)->get('/admin/login')->assertRedirect('/admin/dashboard');
});

test('admins can logout and cannot revisit protected pages', function () {
    $admin = User::factory()->admin()->create();

    $response = $this
        ->actingAs($admin)
        ->withSession(['private-marker' => 'present'])
        ->post('/admin/logout');

    $this->assertGuest();
    $response
        ->assertRedirect('/admin/login')
        ->assertSessionMissing('private-marker');
    $this->get('/admin/dashboard')->assertRedirect('/admin/login');
});

test('admin logout rejects get requests', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/logout')->assertMethodNotAllowed();
});

test('admin registration endpoints do not exist', function () {
    $this->get('/admin/register')->assertNotFound();
    $this->post('/admin/register')->assertNotFound();
});
