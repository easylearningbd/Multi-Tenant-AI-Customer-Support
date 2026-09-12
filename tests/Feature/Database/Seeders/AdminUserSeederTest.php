<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

test('admin seeder creates an administrator with a hashed password', function () {
    config()->set('admin.seed.name', 'Seeded Administrator');
    config()->set('admin.seed.email', 'seeded-admin@example.com');
    config()->set('admin.seed.password', 'secure-test-password');

    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('email', 'seeded-admin@example.com')->sole();
    expect($admin->name)->toBe('Seeded Administrator');
    expect($admin->role)->toBe(UserRole::ADMIN);
    expect($admin->password)->not->toBe('secure-test-password');
    expect(Hash::check('secure-test-password', $admin->password))->toBeTrue();
});

test('admin seeder can run twice without creating duplicate administrators', function () {
    config()->set('admin.seed.name', 'Seeded Administrator');
    config()->set('admin.seed.email', 'seeded-admin@example.com');
    config()->set('admin.seed.password', 'secure-test-password');

    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::query()->where('email', 'seeded-admin@example.com')->count())->toBe(1);
    expect(User::query()->where('email', 'seeded-admin@example.com')->sole()->role)->toBe(UserRole::ADMIN);
});
