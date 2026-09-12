<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;

test('subscriber login screen can be rendered', function () {
    $response = $this->get('/login');

    $response
        ->assertOk()
        ->assertSee('Sign in to NeuralDesk');
});

test('subscribers can authenticate using the subscriber login screen', function () {
    $user = User::factory()->subscriber()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('subscriber login preserves remember me support', function () {
    $user = User::factory()->subscriber()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => true,
    ]);

    $response->assertCookie(Auth::guard('web')->getRecallerName());
});

test('subscribers cannot authenticate with invalid credentials', function () {
    $user = User::factory()->subscriber()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
});

test('admin credentials are rejected by the subscriber login screen', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors(['email' => trans('auth.failed')]);
});

test('subscriber login is throttled after repeated failures', function () {
    $user = User::factory()->subscriber()->create();
    Event::fake([Lockout::class]);

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors('email');
    Event::assertDispatched(Lockout::class);
});

test('authenticated subscribers can access the subscriber dashboard', function () {
    $user = User::factory()->subscriber()->create([
        'name' => '<script>alert("subscriber")</script>',
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response
        ->assertOk()
        ->assertSee('Subscriber Dashboard')
        ->assertSee(e($user->name), escape: false)
        ->assertDontSee($user->name, escape: false);
});

test('guests are redirected from the subscriber dashboard to subscriber login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('admins are forbidden from the subscriber dashboard and profile routes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/dashboard')->assertForbidden();
    $this->actingAs($admin)->get('/profile')->assertForbidden();
    $this->actingAs($admin)->patch('/profile')->assertForbidden();
    $this->actingAs($admin)->delete('/profile')->assertForbidden();
});

test('authenticated accounts are redirected away from subscriber login to their role home', function () {
    $subscriber = User::factory()->subscriber()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($subscriber)->get('/login')->assertRedirect('/dashboard');
    $this->actingAs($admin)->get('/login')->assertRedirect('/admin/dashboard');
});

test('subscribers can logout and cannot revisit protected pages', function () {
    $user = User::factory()->subscriber()->create();

    $response = $this
        ->actingAs($user)
        ->withSession(['private-marker' => 'present'])
        ->post('/logout');

    $this->assertGuest();
    $response
        ->assertRedirect('/login')
        ->assertSessionMissing('private-marker');
    $this->get('/dashboard')->assertRedirect('/login');
});

test('subscriber logout rejects get requests', function () {
    $user = User::factory()->subscriber()->create();

    $this->actingAs($user)->get('/logout')->assertMethodNotAllowed();
});
