<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial Super Admin account.
     */
    public function run(): void
    {
        $name = trim((string) config('admin.seed.name'));
        $email = trim((string) config('admin.seed.email'));
        $password = config('admin.seed.password');

        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Valid ADMIN_SEED_NAME and ADMIN_SEED_EMAIL values are required.');
        }

        if (! is_string($password) || $password === '') {
            if (! app()->environment(['local', 'testing'])) {
                throw new RuntimeException('ADMIN_SEED_PASSWORD must be configured outside local and testing environments.');
            }

            $password = 'password';
        }

        $admin = User::query()->firstOrNew(['email' => $email]);
        $admin->name = $name;
        $admin->role = UserRole::ADMIN;
        $admin->password = Hash::make($password);
        $admin->save();
    }
}
