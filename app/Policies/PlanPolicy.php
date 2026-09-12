<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;

final class PlanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::ADMIN;
    }

    public function view(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::ADMIN;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::ADMIN;
    }

    public function update(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::ADMIN;
    }

    public function delete(User $user, Plan $plan): bool
    {
        return $user->role === UserRole::ADMIN;
    }
}
