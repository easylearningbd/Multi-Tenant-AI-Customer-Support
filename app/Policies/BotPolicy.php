<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Bot;
use App\Models\User;

final class BotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::USER;
    }

    public function view(User $user, Bot $bot): bool
    {
        return $this->owns($user, $bot);
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::USER && $user->isBillingOwner();
    }

    public function update(User $user, Bot $bot): bool
    {
        return $this->owns($user, $bot);
    }

    public function delete(User $user, Bot $bot): bool
    {
        return $this->owns($user, $bot);
    }

    public function train(User $user, Bot $bot): bool
    {
        return $this->owns($user, $bot);
    }

    public function manageEmbed(User $user, Bot $bot): bool
    {
        return $this->owns($user, $bot);
    }

    private function owns(User $user, Bot $bot): bool
    {
        return $user->role === UserRole::USER && $bot->user_id === $user->id;
    }
}
