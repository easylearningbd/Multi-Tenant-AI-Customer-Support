<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\KnowledgeSource;
use App\Models\User;

final class KnowledgeSourcePolicy
{
    public function view(User $user, KnowledgeSource $source): bool
    {
        return $this->owns($user, $source);
    }

    public function update(User $user, KnowledgeSource $source): bool
    {
        return $this->owns($user, $source);
    }

    public function delete(User $user, KnowledgeSource $source): bool
    {
        return $this->owns($user, $source);
    }

    private function owns(User $user, KnowledgeSource $source): bool
    {
        return $user->role === UserRole::USER && $source->user_id === $user->id;
    }
}
