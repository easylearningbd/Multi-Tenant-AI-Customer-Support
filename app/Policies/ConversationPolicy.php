<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\User;

final class ConversationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::USER;
    }

    public function view(User $user, Conversation $conversation): bool
    {
        return $user->role === UserRole::USER && $conversation->user_id === $user->id;
    }

    public function reply(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function changeMode(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function changeStatus(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function archive(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function delete(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    public function downloadAttachment(User $user, Conversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }
}
