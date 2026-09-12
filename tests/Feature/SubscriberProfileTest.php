<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('subscriber profile routes require subscriber authentication', function () {
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->patch(route('profile.update'))->assertRedirect(route('login'));
    $this->put(route('password.update'))->assertRedirect(route('login'));
    $this->delete(route('profile.avatar.destroy'))->assertRedirect(route('login'));

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('profile.edit'))->assertForbidden();
    $this->actingAs($admin)->patch(route('profile.update'))->assertForbidden();
    $this->actingAs($admin)->put(route('password.update'))->assertForbidden();
    $this->actingAs($admin)->delete(route('profile.avatar.destroy'))->assertForbidden();
});

test('subscriber can view the themed profile settings page', function () {
    $subscriber = User::factory()->subscriber()->create([
        'name' => 'Subscriber Person',
        'phone' => '+880 1712 345678',
        'avatar_path' => 'admin/profile/subscriber-person.jpg',
    ]);

    $this->actingAs($subscriber)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('<title>Settings', escape: false)
        ->assertSee('Your account details and security.')
        ->assertSee('Subscriber Person')
        ->assertSee('+880 1712 345678')
        ->assertSee('Change password')
        ->assertSee(route('profile.avatar.destroy'))
        ->assertSee(asset('theme/assets/js/pages/toast.init.js'))
        ->assertSee(asset('js/subscriber-profile.js'));
});

test('subscriber self-service profile routes do not accept user identifiers', function () {
    $subscriber = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)
        ->get('/profile/'.$other->id)
        ->assertNotFound();
});

test('subscriber can update normalized profile details and sees a success toast', function () {
    $subscriber = User::factory()->subscriber()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($subscriber)->patch(route('profile.update'), [
        'name' => '  Updated Subscriber  ',
        'email' => '  SUBSCRIBER.NEW@EXAMPLE.COM ',
        'phone' => '  +44 20 7946 0958  ',
    ]);

    $response
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'Profile updated successfully.');

    $subscriber->refresh();

    expect($subscriber->name)->toBe('Updated Subscriber')
        ->and($subscriber->email)->toBe('subscriber.new@example.com')
        ->and($subscriber->phone)->toBe('+44 20 7946 0958')
        ->and($subscriber->email_verified_at)->toBeNull();
});

test('unchanged email preserves verification and the subscriber can keep their own email', function () {
    $subscriber = User::factory()->subscriber()->create([
        'email_verified_at' => now(),
    ]);

    $this->actingAs($subscriber)->patch(route('profile.update'), [
        'name' => $subscriber->name,
        'email' => strtoupper($subscriber->email),
        'phone' => null,
    ])->assertSessionHasNoErrors();

    expect($subscriber->refresh()->email)->toBe(strtolower($subscriber->email))
        ->and($subscriber->email_verified_at)->not->toBeNull();
});

test('subscriber profile validates unique email and international phone format in its own error bag', function () {
    $subscriber = User::factory()->subscriber()->create();
    $existing = User::factory()->admin()->create();

    $response = $this->actingAs($subscriber)->patch(route('profile.update'), [
        'name' => '   ',
        'email' => $existing->email,
        'phone' => 'invalid-phone',
    ]);

    $response->assertSessionHasErrorsIn('updateProfile', ['name', 'email', 'phone']);

    $this->actingAs($subscriber)
        ->followingRedirects()
        ->patch(route('profile.update'), [
            'name' => '',
            'email' => 'invalid-email',
            'phone' => 'invalid-phone',
        ])
        ->assertOk()
        ->assertSee('Please review the form')
        ->assertSee('The name field is required.')
        ->assertSee('The email field must be a valid email address.')
        ->assertSee('The phone field format is invalid.');
});

test('profile request cannot update another account or protected attributes', function () {
    $subscriber = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create(['name' => 'Untouched Subscriber']);

    $this->actingAs($subscriber)->patch(route('profile.update'), [
        'user_id' => $other->id,
        'name' => 'Current Subscriber',
        'email' => $subscriber->email,
        'phone' => '+1 415 555 0100',
        'role' => UserRole::ADMIN->value,
        'status' => 'suspended',
        'avatar_path' => '../theme/assets/images/logo-sm.png',
        'password' => 'attacker-password',
        'email_verified_at' => null,
        'plan_id' => 999,
        'workspace_id' => 999,
    ])->assertSessionHasNoErrors();

    expect($subscriber->refresh()->role)->toBe(UserRole::USER)
        ->and($subscriber->avatar_path)->toBeNull()
        ->and(Hash::check('password', $subscriber->password))->toBeTrue()
        ->and($other->refresh()->name)->toBe('Untouched Subscriber');
});

test('subscriber can upload a randomized valid avatar and immediately see it in the layout', function () {
    Storage::fake('public');
    $subscriber = User::factory()->subscriber()->create();

    $response = $this->actingAs($subscriber)->patch(route('profile.update'), [
        'name' => $subscriber->name,
        'email' => $subscriber->email,
        'phone' => null,
        'avatar' => UploadedFile::fake()->image('personal-photo.jpg', 200, 200),
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'Avatar updated successfully.');

    $avatarPath = $subscriber->refresh()->avatar_path;

    expect($avatarPath)->toStartWith('admin/profile/')
        ->and(basename($avatarPath))->not->toBe('personal-photo.jpg');
    Storage::disk('public')->assertExists($avatarPath);

    $this->actingAs($subscriber)
        ->get(route('profile.edit'))
        ->assertSee(Storage::disk('public')->url($avatarPath));
});

test('subscriber avatar rejects unsafe and oversized file types', function () {
    Storage::fake('public');
    config()->set('admin.profile.avatar_max_kilobytes', 2048);
    $subscriber = User::factory()->subscriber()->create();
    $profile = ['name' => $subscriber->name, 'email' => $subscriber->email, 'phone' => null];

    $this->actingAs($subscriber)->patch(route('profile.update'), $profile + [
        'avatar' => UploadedFile::fake()->create('payload.txt', 10, 'text/plain'),
    ])->assertSessionHasErrorsIn('updateProfile', 'avatar');

    $this->actingAs($subscriber)->patch(route('profile.update'), $profile + [
        'avatar' => UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
    ])->assertSessionHasErrorsIn('updateProfile', 'avatar');

    $this->actingAs($subscriber)->patch(route('profile.update'), $profile + [
        'avatar' => UploadedFile::fake()->create('large.jpg', 2049, 'image/jpeg'),
    ])->assertSessionHasErrorsIn('updateProfile', 'avatar');

    expect($subscriber->refresh()->avatar_path)->toBeNull();
});

test('replacing an avatar deletes the previous managed image only after persistence succeeds', function () {
    Storage::fake('public');
    Storage::disk('public')->put('admin/profile/old-subscriber.jpg', 'old-image');
    $subscriber = User::factory()->subscriber()->create([
        'avatar_path' => 'admin/profile/old-subscriber.jpg',
    ]);

    $this->actingAs($subscriber)->patch(route('profile.update'), [
        'name' => $subscriber->name,
        'email' => $subscriber->email,
        'phone' => null,
        'avatar' => UploadedFile::fake()->image('replacement.png', 200, 200),
    ])->assertSessionHasNoErrors();

    $newPath = $subscriber->refresh()->avatar_path;
    Storage::disk('public')->assertExists($newPath);
    Storage::disk('public')->assertMissing('admin/profile/old-subscriber.jpg');
});

test('failed profile persistence cleans the new upload and preserves the old avatar', function () {
    Storage::fake('public');
    Storage::disk('public')->put('admin/profile/existing-subscriber.jpg', 'existing-image');
    $subscriber = User::factory()->subscriber()->create([
        'avatar_path' => 'admin/profile/existing-subscriber.jpg',
    ]);

    User::updating(function (User $updatingUser) use ($subscriber): void {
        if ($updatingUser->is($subscriber)) {
            throw new RuntimeException('Simulated persistence failure.');
        }
    });

    $this->actingAs($subscriber)->patch(route('profile.update'), [
        'name' => 'Changed Subscriber',
        'email' => $subscriber->email,
        'phone' => null,
        'avatar' => UploadedFile::fake()->image('replacement.jpg', 200, 200),
    ])->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'error');

    expect($subscriber->refresh()->avatar_path)->toBe('admin/profile/existing-subscriber.jpg')
        ->and(Storage::disk('public')->allFiles('admin/profile'))->toBe(['admin/profile/existing-subscriber.jpg']);
});

test('subscriber can remove a custom avatar without deleting unrelated files', function () {
    Storage::fake('public');
    Storage::disk('public')->put('admin/profile/subscriber.jpg', 'avatar');
    Storage::disk('public')->put('shared/default-avatar.png', 'shared');
    $subscriber = User::factory()->subscriber()->create([
        'avatar_path' => 'admin/profile/subscriber.jpg',
    ]);

    $this->actingAs($subscriber)
        ->delete(route('profile.avatar.destroy'))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'Avatar removed successfully.');

    expect($subscriber->refresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing('admin/profile/subscriber.jpg');
    Storage::disk('public')->assertExists('shared/default-avatar.png');
});

test('removing an unmanaged avatar reference never deletes a shared default asset', function () {
    Storage::fake('public');
    Storage::disk('public')->put('shared/default-avatar.png', 'shared');
    $subscriber = User::factory()->subscriber()->create([
        'avatar_path' => 'shared/default-avatar.png',
    ]);

    $this->actingAs($subscriber)
        ->delete(route('profile.avatar.destroy'))
        ->assertSessionHasNoErrors();

    expect($subscriber->refresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertExists('shared/default-avatar.png');
});

test('correct current password updates the hash and keeps the subscriber authenticated', function () {
    $subscriber = User::factory()->subscriber()->create();
    $newPassword = 'new-secure-password';

    $response = $this->actingAs($subscriber)->put(route('password.update'), [
        'current_password' => 'password',
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ]);

    $response
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'Password updated successfully.');

    $this->assertAuthenticatedAs($subscriber);
    expect(Hash::check($newPassword, $subscriber->refresh()->password))->toBeTrue();
});

test('password update rejects incorrect current password weak values missing confirmation and reuse', function () {
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)->put(route('password.update'), [
        'current_password' => 'incorrect-password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertSessionHasErrorsIn('updatePassword', 'current_password');

    $this->actingAs($subscriber)->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrorsIn('updatePassword', 'password');

    $this->actingAs($subscriber)->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'new-secure-password',
    ])->assertSessionHasErrorsIn('updatePassword', 'password');

    $this->actingAs($subscriber)->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrorsIn('updatePassword', 'password');

    expect(Hash::check('password', $subscriber->refresh()->password))->toBeTrue();
});

test('password values are not flashed and profile data is untouched by password validation', function () {
    $subscriber = User::factory()->subscriber()->create([
        'name' => 'Original Name',
        'phone' => '+1 415 555 0100',
    ]);

    $response = $this->actingAs($subscriber)->put(route('password.update'), [
        'current_password' => 'incorrect-password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
        'name' => 'Injected Name',
        'phone' => '+1 000 000 0000',
    ]);

    $response->assertSessionHasErrorsIn('updatePassword');
    expect(session('_old_input', []))->not->toHaveKeys(['current_password', 'password', 'password_confirmation'])
        ->and($subscriber->refresh()->name)->toBe('Original Name')
        ->and($subscriber->phone)->toBe('+1 415 555 0100');
});

test('new password works for a future subscriber login', function () {
    $subscriber = User::factory()->subscriber()->create([
        'email' => 'future-login@example.com',
    ]);

    $this->actingAs($subscriber)->put(route('password.update'), [
        'current_password' => 'password',
        'password' => 'future-login-password',
        'password_confirmation' => 'future-login-password',
    ])->assertSessionHasNoErrors();

    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->assertGuest();

    $this->post(route('login'), [
        'email' => 'future-login@example.com',
        'password' => 'future-login-password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($subscriber);
});
