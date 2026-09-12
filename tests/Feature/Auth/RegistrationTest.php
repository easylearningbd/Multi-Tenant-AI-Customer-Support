<?php

use App\Enums\UserRole;
use App\Models\User;

test('subscriber registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertOk();
});

test('guests can register a subscriber account', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::query()->where('email', 'test@example.com')->sole();

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
    expect($user->role)->toBe(UserRole::USER);
});

test('public registration cannot assign the admin role', function () {
    $response = $this->post('/register', [
        'name' => 'Malicious User',
        'email' => 'malicious@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => UserRole::ADMIN->value,
    ]);

    $user = User::query()->where('email', 'malicious@example.com')->sole();

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
    expect($user->role)->toBe(UserRole::USER);
    $this->assertDatabaseMissing('users', [
        'email' => 'malicious@example.com',
        'role' => UserRole::ADMIN->value,
    ]);
});
