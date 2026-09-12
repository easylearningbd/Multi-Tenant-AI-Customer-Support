<?php

namespace App\Http\Requests\Admin\Auth;

use App\Enums\UserRole;
use App\Http\Requests\Auth\PlatformLoginRequest;

class AdminLoginRequest extends PlatformLoginRequest
{
    /**
     * Get the only platform role accepted by this authentication surface.
     */
    protected function expectedRole(): UserRole
    {
        return UserRole::ADMIN;
    }
}
