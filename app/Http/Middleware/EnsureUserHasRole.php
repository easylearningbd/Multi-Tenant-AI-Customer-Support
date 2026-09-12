<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $expectedRole = UserRole::tryFrom($role);

        abort_if($expectedRole === null, Response::HTTP_INTERNAL_SERVER_ERROR, 'Invalid role middleware configuration.');
        abort_unless($user->role === $expectedRole, Response::HTTP_FORBIDDEN);

        return $next($request);
    }
}
