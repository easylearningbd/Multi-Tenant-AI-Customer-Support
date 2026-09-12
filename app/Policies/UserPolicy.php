<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->role === UserRole::ADMIN;
    }

    public function view(User $actor, User $subscriber): bool
    {
        return $this->mayManageSubscriber($actor, $subscriber);
    }

    public function update(User $actor, User $subscriber): bool
    {
        return $this->mayManageSubscriber($actor, $subscriber);
    }

    public function delete(User $actor, User $subscriber): bool
    {
        return $this->mayManageSubscriber($actor, $subscriber);
    }

    private function mayManageSubscriber(User $actor, User $subscriber): bool
    {
        return $actor->role === UserRole::ADMIN
            && $subscriber->role === UserRole::USER;
    }
}
