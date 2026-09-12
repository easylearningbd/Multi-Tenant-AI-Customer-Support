<?php

namespace App\Http\Requests\Auth;

use App\Enums\UserRole;

class LoginRequest extends PlatformLoginRequest
{
    /**
     * Get the only platform role accepted by this authentication surface.
     */
    protected function expectedRole(): UserRole
    {
        return UserRole::USER;
    }
}
