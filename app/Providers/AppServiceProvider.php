<?php

namespace App\Providers;

use App\Models\Plan;
use App\Models\SupportTicket;
use App\Models\User;
use App\Policies\PlanPolicy;
use App\Policies\SupportTicketPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Plan::class, PlanPolicy::class);
        Gate::policy(SupportTicket::class, SupportTicketPolicy::class);
        Gate::policy(User::class, UserPolicy::class);

        RateLimiter::for('support-ticket-create', fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('support-tickets.rate_limits.create_per_minute')),
        )->by('support-ticket-create:'.$request->user()?->id)->response(
            fn (): RedirectResponse => back()->with('toast', [
                'type' => 'warning',
                'title' => __('Too many tickets'),
                'message' => __('Please wait before submitting another support ticket.'),
            ]),
        ));

        RateLimiter::for('support-ticket-reply', fn (Request $request): Limit => Limit::perMinute(
            max(1, (int) config('support-tickets.rate_limits.reply_per_minute')),
        )->by('support-ticket-reply:'.$request->user()?->id)->response(
            fn (): RedirectResponse => back()->with('toast', [
                'type' => 'warning',
                'title' => __('Too many replies'),
                'message' => __('Please wait before sending another reply.'),
            ]),
        ));
    }
}
