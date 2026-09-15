<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;

final class CurrentSubscriptionResolver
{
    public function for(User $billingOwner): ?Subscription
    {
        $now = CarbonImmutable::now('UTC');
        $withPlan = 'plan:id,name,slug,description,price_minor,currency,interval,trial_days,custom_pricing,is_active,sort_order,features,limits';

        $current = $billingOwner->subscriptions()
            ->with($withPlan)
            ->where(function ($query) use ($now): void {
                $query->where(function ($query) use ($now): void {
                    $query->where('status', SubscriptionStatus::TRIALING)
                        ->where('trial_ends_at', '>', $now);
                })->orWhere(function ($query) use ($now): void {
                    $query->where('status', SubscriptionStatus::ACTIVE)
                        ->where(function ($query) use ($now): void {
                            $query->where(function ($query) use ($now): void {
                                $query->whereNotNull('ends_at')->where('ends_at', '>', $now);
                            })->orWhere(function ($query) use ($now): void {
                                $query->whereNull('ends_at')
                                    ->where(function ($query) use ($now): void {
                                        $query->whereNull('current_period_ends_at')
                                            ->orWhere('current_period_ends_at', '>', $now);
                                    });
                            });
                        });
                })->orWhere(function ($query) use ($now): void {
                    $query->where('status', SubscriptionStatus::CANCELED)
                        ->where(function ($query) use ($now): void {
                            $query->where('ends_at', '>', $now)
                                ->orWhere(function ($query) use ($now): void {
                                    $query->whereNull('ends_at')->where('current_period_ends_at', '>', $now);
                                });
                        });
                });
            })
            ->latest('id')
            ->first();

        if ($current) {
            return $current;
        }

        // Preserve expired-plan visibility on Billing when no subscription grants access.
        return $billingOwner->subscriptions()->with($withPlan)->latest('id')->first();
    }
}
