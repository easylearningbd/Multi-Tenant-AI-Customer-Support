<?php

namespace App\Actions\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class UpdateAdminPassword
{
    public function handle(User $admin, string $password): void
    {
        $admin->password = Hash::make($password);
        $admin->save();
    }
}
