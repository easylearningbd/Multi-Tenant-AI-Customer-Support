<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\User;

final class ConversationPolicy
{
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->role === UserRole::USER && $conversation->user_id === $user->id;
    }
}
