<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

test('guests are redirected to admin login from subscriber management routes', function () {
    $subscriber = User::factory()->subscriber()->create();

    $this->get(route('admin.users.index'))->assertRedirect(route('admin.login'));
    $this->get(route('admin.users.show', $subscriber))->assertRedirect(route('admin.login'));
    $this->get(route('admin.users.edit', $subscriber))->assertRedirect(route('admin.login'));
    $this->put(route('admin.users.update', $subscriber))->assertRedirect(route('admin.login'));
    $this->delete(route('admin.users.destroy', $subscriber))->assertRedirect(route('admin.login'));
});

test('subscribers cannot access or mutate admin user management routes', function () {
    $actor = User::factory()->subscriber()->create();
    $target = User::factory()->subscriber()->create();

    $this->actingAs($actor)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($actor)->get(route('admin.users.show', $target))->assertForbidden();
    $this->actingAs($actor)->get(route('admin.users.edit', $target))->assertForbidden();
    $this->actingAs($actor)->put(route('admin.users.update', $target), validUserData($target))->assertForbidden();
    $this->actingAs($actor)->delete(route('admin.users.destroy', $target))->assertForbidden();
});

test('admin can view a paginated subscriber-only users list', function () {
    $admin = User::factory()->admin()->create(['name' => 'Hidden Platform Admin']);
    $subscribers = User::factory()->subscriber()->count(16)->create();

    $response = $this->actingAs($admin)->get(route('admin.users.index'));

    $response
        ->assertOk()
        ->assertSee('<title>Users', escape: false)
        ->assertSee($subscribers->last()->name)
        ->assertDontSee('Add User')
        ->assertViewHas('users', fn ($users): bool => $users->perPage() === 15
            && $users->total() === 16
            && $users->getCollection()->every(fn (User $user): bool => $user->role === UserRole::USER)
            && $users->getCollection()->doesntContain('id', $admin->id));
});

test('admin can search subscriber names emails and phone numbers', function () {
    $admin = User::factory()->admin()->create();
    $match = User::factory()->subscriber()->create([
        'name' => 'Unique Search Person',
        'email' => 'ordinary@example.test',
        'phone' => '+880 1712 345678',
    ]);
    User::factory()->subscriber()->create(['name' => 'Different Subscriber']);

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => 'Unique Search']))
        ->assertOk()
        ->assertSee($match->name)
        ->assertDontSee('Different Subscriber');

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['search' => '1712 345678']))
        ->assertOk()
        ->assertSee($match->email);
});

test('pagination preserves validated search sorting and page size filters', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->subscriber()->count(31)->create(['name' => 'Matching Subscriber']);

    $response = $this->actingAs($admin)->get(route('admin.users.index', [
        'search' => 'Matching',
        'sort' => 'name',
        'direction' => 'asc',
        'per_page' => 30,
    ]));

    $response
        ->assertOk()
        ->assertViewHas('users', fn ($users): bool => $users->perPage() === 30 && $users->total() === 31)
        ->assertSee('search=Matching', escape: false)
        ->assertSee('per_page=30', escape: false);
});

test('unsupported sorting input is rejected before reaching the query', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['sort' => 'password', 'direction' => 'sideways']))
        ->assertSessionHasErrors(['sort', 'direction']);
});

test('admin can view subscriber details with only supported profile facts', function () {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->unverified()->create([
        'name' => 'Taylor Subscriber',
        'phone' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.users.show', $subscriber))
        ->assertOk()
        ->assertSee('Taylor Subscriber')
        ->assertSee('Personal Information')
        ->assertSee('Security &amp; Verification', escape: false)
        ->assertSee('Not provided')
        ->assertSee('Unverified')
        ->assertDontSee('Login as User')
        ->assertDontSee('Two-Factor Authentication')
        ->assertDontSee('Account Status');
});

test('admin records return not found from subscriber show edit update and delete routes', function () {
    $actor = User::factory()->admin()->create();
    $otherAdmin = User::factory()->admin()->create();

    $this->actingAs($actor)->get('/admin/users/'.$otherAdmin->id)->assertNotFound();
    $this->actingAs($actor)->get('/admin/users/'.$otherAdmin->id.'/edit')->assertNotFound();
    $this->actingAs($actor)->put('/admin/users/'.$otherAdmin->id, validUserData($otherAdmin))->assertNotFound();
    $this->actingAs($actor)->delete('/admin/users/'.$otherAdmin->id)->assertNotFound();

    $this->assertDatabaseHas('users', ['id' => $otherAdmin->id, 'role' => UserRole::ADMIN->value]);
});

test('admin can open the subscriber edit page', function () {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($admin)
        ->get(route('admin.users.edit', $subscriber))
        ->assertOk()
        ->assertSee('<title>Edit User', escape: false)
        ->assertSee($subscriber->name)
        ->assertSee('Update User')
        ->assertDontSee('Password');
});

test('admin can update permitted subscriber fields and gets a success toast', function () {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($admin)->put(route('admin.users.update', $subscriber), [
        'name' => '  Updated Subscriber  ',
        'email' => '  UPDATED.SUBSCRIBER@EXAMPLE.COM ',
        'phone' => '  +44 20 7946 0958  ',
    ]);

    $response
        ->assertRedirect(route('admin.users.show', $subscriber))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'User updated successfully.');

    $subscriber->refresh();
    expect($subscriber->name)->toBe('Updated Subscriber')
        ->and($subscriber->email)->toBe('updated.subscriber@example.com')
        ->and($subscriber->phone)->toBe('+44 20 7946 0958')
        ->and($subscriber->email_verified_at)->toBeNull();
});

test('current subscriber email can be retained', function () {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($admin)
        ->put(route('admin.users.update', $subscriber), [
            'name' => $subscriber->name,
            'email' => strtoupper($subscriber->email),
            'phone' => null,
        ])
        ->assertSessionHasNoErrors();
});

test('subscriber updates validate required fields email uniqueness and phone format', function () {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();
    $existing = User::factory()->subscriber()->create();

    $this->actingAs($admin)
        ->from(route('admin.users.edit', $subscriber))
        ->put(route('admin.users.update', $subscriber), [
            'name' => ' ',
            'email' => $existing->email,
            'phone' => 'not-a-phone',
        ])
        ->assertRedirect(route('admin.users.edit', $subscriber))
        ->assertSessionHasErrorsIn('userUpdate', ['name', 'email', 'phone']);
});

test('subscriber update cannot change role password verification or another account', function () {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create(['name' => 'Untouched Subscriber']);
    $oldPassword = $subscriber->password;

    $this->actingAs($admin)->put(route('admin.users.update', $subscriber), validUserData($subscriber) + [
        'user_id' => $other->id,
        'role' => UserRole::ADMIN->value,
        'status' => 'active',
        'password' => 'attacker-password',
        'email_verified_at' => null,
    ])->assertSessionHasNoErrors();

    expect($subscriber->refresh()->role)->toBe(UserRole::USER)
        ->and($subscriber->password)->toBe($oldPassword)
        ->and($other->refresh()->name)->toBe('Untouched Subscriber');
});

test('admin can upload a valid randomized subscriber profile image', function () {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($admin)->put(route('admin.users.update', $subscriber), validUserData($subscriber) + [
        'avatar' => UploadedFile::fake()->image('personal-photo.jpg', 200, 200),
    ])->assertSessionHasNoErrors();

    $path = $subscriber->refresh()->avatar_path;
    expect($path)->toStartWith('admin/profile/')
        ->and(basename($path))->not->toBe('personal-photo.jpg');
    Storage::disk('public')->assertExists($path);
});

test('subscriber profile image rejects invalid and oversized files', function () {
    Storage::fake('public');
    config()->set('admin.profile.avatar_max_kilobytes', 2048);
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();

    $this->actingAs($admin)->put(route('admin.users.update', $subscriber), validUserData($subscriber) + [
        'avatar' => UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml'),
    ])->assertSessionHasErrorsIn('userUpdate', 'avatar');

    $this->actingAs($admin)->put(route('admin.users.update', $subscriber), validUserData($subscriber) + [
        'avatar' => UploadedFile::fake()->create('large.jpg', 2049, 'image/jpeg'),
    ])->assertSessionHasErrorsIn('userUpdate', 'avatar');

    expect($subscriber->refresh()->avatar_path)->toBeNull();
});

test('replacing subscriber avatar removes only the previous managed image', function () {
    Storage::fake('public');
    Storage::disk('public')->put('admin/profile/old-subscriber.jpg', 'old');
    Storage::disk('public')->put('unrelated/default-avatar.png', 'default');
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create(['avatar_path' => 'admin/profile/old-subscriber.jpg']);

    $this->actingAs($admin)->put(route('admin.users.update', $subscriber), validUserData($subscriber) + [
        'avatar' => UploadedFile::fake()->image('replacement.png', 200, 200),
    ])->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing('admin/profile/old-subscriber.jpg');
    Storage::disk('public')->assertExists($subscriber->refresh()->avatar_path);
    Storage::disk('public')->assertExists('unrelated/default-avatar.png');
});

test('admin can delete a subscriber and its authentication records', function () {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create();
    DB::table('sessions')->insert([
        'id' => 'subscriber-session',
        'user_id' => $subscriber->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Test',
        'payload' => 'payload',
        'last_activity' => now()->timestamp,
    ]);
    DB::table('password_reset_tokens')->insert([
        'email' => $subscriber->email,
        'token' => 'hashed-token',
        'created_at' => now(),
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $subscriber))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('toast', fn (array $toast): bool => $toast['message'] === 'User deleted successfully.');

    $this->assertDatabaseMissing('users', ['id' => $subscriber->id]);
    $this->assertDatabaseMissing('sessions', ['user_id' => $subscriber->id]);
    $this->assertDatabaseMissing('password_reset_tokens', ['email' => $subscriber->email]);
    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('deleting a subscriber removes its managed avatar', function () {
    Storage::fake('public');
    Storage::disk('public')->put('admin/profile/subscriber.jpg', 'image');
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->subscriber()->create(['avatar_path' => 'admin/profile/subscriber.jpg']);

    $this->actingAs($admin)->delete(route('admin.users.destroy', $subscriber))->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing('admin/profile/subscriber.jpg');
});

test('create and store routes are not defined', function () {
    expect(Route::has('admin.users.create'))->toBeFalse()
        ->and(Route::has('admin.users.store'))->toBeFalse();

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/admin/users/create')->assertNotFound();
    $this->actingAs($admin)->post('/admin/users')->assertMethodNotAllowed();
});

function validUserData(User $subscriber): array
{
    return [
        'name' => $subscriber->name,
        'email' => $subscriber->email,
        'phone' => $subscriber->phone,
    ];
}
