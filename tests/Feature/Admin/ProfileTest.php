<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to admin login from every admin profile route', function () {
    $this->get(route('admin.profile.edit'))->assertRedirect(route('admin.login'));
    $this->patch(route('admin.profile.update'))->assertRedirect(route('admin.login'));
    $this->put(route('admin.profile.password.update'))->assertRedirect(route('admin.login'));
    $this->delete(route('admin.profile.avatar.destroy'))->assertRedirect(route('admin.login'));
});

test('subscribers cannot view or submit admin profile forms', function () {
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($subscriber)->get(route('admin.profile.edit'))->assertForbidden();
    $this->actingAs($subscriber)->patch(route('admin.profile.update'))->assertForbidden();
    $this->actingAs($subscriber)->put(route('admin.profile.password.update'))->assertForbidden();
    $this->actingAs($subscriber)->delete(route('admin.profile.avatar.destroy'))->assertForbidden();
});

test('an authenticated admin can view the profile page', function () {
    $admin = User::factory()->admin()->create([
        'name' => 'Platform Administrator',
        'phone' => '+880 1712 345678',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.profile.edit'))
        ->assertOk()
        ->assertSee('<title>Edit Profile', escape: false)
        ->assertSee('Profile Information')
        ->assertSee('Change Password')
        ->assertSee('Platform Administrator')
        ->assertSee('+880 1712 345678')
        ->assertSee(asset('theme/assets/js/pages/toast.init.js'))
        ->assertSee(asset('js/admin-profile.js'))
        ->assertDontSee('Two-Factor Authentication')
        ->assertDontSee('Active Sessions');
});

test('admin can update name email and phone and receives a success toast', function () {
    $admin = User::factory()->admin()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)->patch(route('admin.profile.update'), [
        'name' => '  Updated Administrator  ',
        'email' => '  UPDATED.ADMIN@EXAMPLE.COM ',
        'phone' => '  +44 20 7946 0958  ',
    ]);

    $response
        ->assertRedirect(route('admin.profile.edit').'#profile-information')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'Profile updated successfully.');

    $admin->refresh();

    expect($admin->name)->toBe('Updated Administrator')
        ->and($admin->email)->toBe('updated.admin@example.com')
        ->and($admin->phone)->toBe('+44 20 7946 0958')
        ->and($admin->email_verified_at)->toBeNull();
});

test('admin can keep their existing email address', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.profile.update'), [
        'name' => $admin->name,
        'email' => strtoupper($admin->email),
        'phone' => null,
    ])->assertSessionHasNoErrors();

    expect($admin->refresh()->email)->toBe(strtolower($admin->email));
});

test('admin profile validates required email uniqueness and international phone format', function () {
    $admin = User::factory()->admin()->create();
    $existing = User::factory()->create();

    $response = $this
        ->actingAs($admin)
        ->from(route('admin.profile.edit'))
        ->patch(route('admin.profile.update'), [
            'name' => '   ',
            'email' => $existing->email,
            'phone' => 'not-a-phone',
        ]);

    $response
        ->assertRedirect(route('admin.profile.edit').'#profile-information')
        ->assertSessionHasErrorsIn('profileUpdate', ['name', 'email', 'phone']);
});

test('admin profile rejects an invalid email address and renders every validation message in the toast', function () {
    $admin = User::factory()->admin()->create();

    $response = $this
        ->actingAs($admin)
        ->followingRedirects()
        ->patch(route('admin.profile.update'), [
            'name' => '',
            'email' => 'invalid-email',
            'phone' => 'invalid',
        ]);

    $response
        ->assertOk()
        ->assertSee('Please review the form')
        ->assertSee('The name field is required.')
        ->assertSee('The email field must be a valid email address.')
        ->assertSee('The phone field format is invalid.');
});

test('profile request cannot change privileges status or another admin account', function () {
    $admin = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create([
        'name' => 'Untouched Admin',
    ]);

    $this->actingAs($admin)->patch(route('admin.profile.update'), [
        'user_id' => $otherAdmin->id,
        'name' => 'Current Admin Updated',
        'email' => $admin->email,
        'phone' => null,
        'role' => UserRole::USER->value,
        'status' => 'suspended',
        'password' => 'attacker-controlled-password',
        'email_verified_at' => null,
    ])->assertSessionHasNoErrors();

    expect($admin->refresh()->name)->toBe('Current Admin Updated')
        ->and($admin->role)->toBe(UserRole::ADMIN)
        ->and(Hash::check('password', $admin->password))->toBeTrue()
        ->and($otherAdmin->refresh()->name)->toBe('Untouched Admin');
});

test('admin can upload a valid randomized profile image and header renders it', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->patch(route('admin.profile.update'), [
        'name' => $admin->name,
        'email' => $admin->email,
        'phone' => null,
        'avatar' => UploadedFile::fake()->image('personal-photo.jpg', 200, 200),
    ]);

    $response->assertSessionHasNoErrors();

    $avatarPath = $admin->refresh()->avatar_path;

    expect($avatarPath)->toStartWith('admin/profile/')
        ->and(basename($avatarPath))->not->toBe('personal-photo.jpg');
    Storage::disk('public')->assertExists($avatarPath);

    $this->actingAs($admin)
        ->get(route('admin.profile.edit'))
        ->assertOk()
        ->assertSee(Storage::disk('public')->url($avatarPath));
});

test('profile image rejects non-images and svg files', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();

    $profile = [
        'name' => $admin->name,
        'email' => $admin->email,
        'phone' => null,
    ];

    $this->actingAs($admin)
        ->patch(route('admin.profile.update'), $profile + [
            'avatar' => UploadedFile::fake()->create('payload.txt', 10, 'text/plain'),
        ])
        ->assertSessionHasErrorsIn('profileUpdate', 'avatar');

    $this->actingAs($admin)
        ->patch(route('admin.profile.update'), $profile + [
            'avatar' => UploadedFile::fake()->createWithContent('vector.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ])
        ->assertSessionHasErrorsIn('profileUpdate', 'avatar');

    expect($admin->refresh()->avatar_path)->toBeNull();
});

test('profile image rejects files larger than the configured maximum', function () {
    Storage::fake('public');
    config()->set('admin.profile.avatar_max_kilobytes', 2048);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.profile.update'), [
        'name' => $admin->name,
        'email' => $admin->email,
        'phone' => null,
        'avatar' => UploadedFile::fake()->create('large.jpg', 2049, 'image/jpeg'),
    ])->assertSessionHasErrorsIn('profileUpdate', 'avatar');

    expect($admin->refresh()->avatar_path)->toBeNull();
});

test('replacing an avatar stores the new image and removes the previous managed image', function () {
    Storage::fake('public');
    Storage::disk('public')->put('admin/profile/old-avatar.jpg', 'old-image');
    $admin = User::factory()->admin()->create([
        'avatar_path' => 'admin/profile/old-avatar.jpg',
    ]);

    $this->actingAs($admin)->patch(route('admin.profile.update'), [
        'name' => $admin->name,
        'email' => $admin->email,
        'phone' => null,
        'avatar' => UploadedFile::fake()->image('new-avatar.png', 200, 200),
    ])->assertSessionHasNoErrors();

    $newAvatarPath = $admin->refresh()->avatar_path;

    expect($newAvatarPath)->not->toBe('admin/profile/old-avatar.jpg');
    Storage::disk('public')->assertExists($newAvatarPath);
    Storage::disk('public')->assertMissing('admin/profile/old-avatar.jpg');
});

test('failed profile persistence removes the new upload and preserves the previous avatar', function () {
    Storage::fake('public');
    Storage::disk('public')->put('admin/profile/existing-avatar.jpg', 'existing-image');
    $admin = User::factory()->admin()->create([
        'avatar_path' => 'admin/profile/existing-avatar.jpg',
    ]);

    User::updating(function (User $updatingUser) use ($admin): void {
        if ($updatingUser->is($admin)) {
            throw new RuntimeException('Simulated persistence failure.');
        }
    });

    $this->actingAs($admin)->patch(route('admin.profile.update'), [
        'name' => 'Changed Name',
        'email' => $admin->email,
        'phone' => null,
        'avatar' => UploadedFile::fake()->image('replacement.jpg', 200, 200),
    ])->assertSessionHas('toast', fn (array $toast): bool => $toast['type'] === 'error');

    expect($admin->refresh()->avatar_path)->toBe('admin/profile/existing-avatar.jpg')
        ->and(Storage::disk('public')->allFiles('admin/profile'))->toBe(['admin/profile/existing-avatar.jpg']);
});

test('admin can remove a managed avatar', function () {
    Storage::fake('public');
    Storage::disk('public')->put('admin/profile/avatar.jpg', 'avatar');
    $admin = User::factory()->admin()->create([
        'avatar_path' => 'admin/profile/avatar.jpg',
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.profile.avatar.destroy'))
        ->assertRedirect(route('admin.profile.edit').'#profile-information')
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'Profile image removed successfully.');

    expect($admin->refresh()->avatar_path)->toBeNull();
    Storage::disk('public')->assertMissing('admin/profile/avatar.jpg');
});

test('removing an avatar never deletes unrelated storage or theme assets', function () {
    Storage::fake('public');
    Storage::disk('public')->put('unrelated/default-avatar.png', 'shared-image');
    $admin = User::factory()->admin()->create([
        'avatar_path' => 'unrelated/default-avatar.png',
    ]);
    $themeAsset = public_path('theme/assets/images/logo-sm.png');

    expect($themeAsset)->toBeFile();

    $this->actingAs($admin)
        ->delete(route('admin.profile.avatar.destroy'))
        ->assertSessionHasNoErrors();

    expect($admin->refresh()->avatar_path)->toBeNull()
        ->and($themeAsset)->toBeFile();
    Storage::disk('public')->assertExists('unrelated/default-avatar.png');
});

test('correct current password changes the hash and keeps the admin authenticated', function () {
    $admin = User::factory()->admin()->create();
    $newPassword = 'new-secure-password';

    $response = $this->actingAs($admin)->put(route('admin.profile.password.update'), [
        'current_password' => 'password',
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
    ]);

    $response
        ->assertRedirect(route('admin.profile.edit').'#change-password')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'Password changed successfully.');

    $this->assertAuthenticatedAs($admin);
    expect($admin->refresh()->password)->not->toBe($newPassword)
        ->and(Hash::check($newPassword, $admin->password))->toBeTrue();
});

test('incorrect current password is rejected', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.profile.password.update'), [
        'current_password' => 'incorrect-password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ])->assertSessionHasErrorsIn('passwordUpdate', 'current_password');

    expect(Hash::check('password', $admin->refresh()->password))->toBeTrue();
});

test('new password requires confirmation and the configured password strength', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.profile.password.update'), [
        'current_password' => 'password',
        'password' => 'new-secure-password',
    ])->assertSessionHasErrorsIn('passwordUpdate', 'password');

    $this->actingAs($admin)->put(route('admin.profile.password.update'), [
        'current_password' => 'password',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertSessionHasErrorsIn('passwordUpdate', 'password');
});

test('new password cannot equal the current password', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->put(route('admin.profile.password.update'), [
        'current_password' => 'password',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrorsIn('passwordUpdate', 'password');

    expect(Hash::check('password', $admin->refresh()->password))->toBeTrue();
});

test('password values are never flashed to old input after validation failure', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->put(route('admin.profile.password.update'), [
        'current_password' => 'incorrect-password',
        'password' => 'new-secure-password',
        'password_confirmation' => 'new-secure-password',
    ]);

    $response->assertSessionHasErrorsIn('passwordUpdate');

    expect(session('_old_input', []))->not->toHaveKeys([
        'current_password',
        'password',
        'password_confirmation',
    ]);
});

test('admin profile changes do not affect dashboard or subscriber dashboard isolation', function () {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    $this->actingAs($subscriber)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Subscriber Dashboard');
    $this->actingAs($admin)->get(route('dashboard'))->assertForbidden();
});
