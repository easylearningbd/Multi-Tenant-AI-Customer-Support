<?php

use App\Enums\UserRole;
use App\Exceptions\PlanLimitExceededException;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        $middleware->redirectGuestsTo(
            fn (Request $request): string => $request->is('admin/*')
                ? route('admin.login')
                : route('login')
        );

        $middleware->redirectUsersTo(
            fn (Request $request): string => $request->user()?->role === UserRole::ADMIN
                ? route('admin.dashboard')
                : route('dashboard')
        );

        $middleware->validateCsrfTokens(except: [
            'api/widgets/v1/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (PlanLimitExceededException $exception, Request $request): JsonResponse|RedirectResponse {
            $message = $exception->errors()['plan_limit'][0] ?? __('Your current plan limit has been reached.');
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'errors' => ['plan_limit' => [$message]],
                    'quota' => [
                        'metric' => $exception->metric->value,
                        'current' => $exception->current,
                        'limit' => $exception->limit,
                    ],
                    'billing_url' => route('billing.index'),
                ], 422);
            }

            return back()->withInput()->withErrors(['plan_limit' => $message])->with('toast', [
                'type' => 'warning',
                'title' => __('Plan limit reached'),
                'message' => $message,
                'action_url' => route('billing.index'),
                'action_label' => __('View plans'),
            ]);
        });
    })->create();
