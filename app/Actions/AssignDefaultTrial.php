<?php

namespace App\Actions;

use App\Enums\PlanInterval;
use App\Enums\SubscriptionStatus;
use App\Exceptions\DefaultTrialUnavailable;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class AssignDefaultTrial
{
    public function handle(User $billingOwner): ?Subscription
    {
        if (! $billingOwner->isBillingOwner()) {
            return null;
        }

        return DB::transaction(function () use ($billingOwner): ?Subscription {
            $lockedOwner = User::query()->lockForUpdate()->findOrFail($billingOwner->id);

            if ($lockedOwner->trial_claimed_at !== null) {
                return $lockedOwner->subscriptions()
                    ->whereNotNull('trial_claim_key')
                    ->latest('id')
                    ->first();
            }

            $existingSubscription = $lockedOwner->subscriptions()->latest('id')->first();

            if ($existingSubscription) {
                return $existingSubscription;
            }

            $slug = (string) config('billing.default_trial_plan_slug');
            $plan = Plan::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();

            if (! $plan
                || $plan->interval !== PlanInterval::TRIAL
                || ! is_int($plan->trial_days)
                || $plan->trial_days < 1) {
                throw new DefaultTrialUnavailable('The configured default trial plan is missing, inactive, or invalid.');
            }

            $startsAt = now();
            $trialEndsAt = $startsAt->copy()->addDays($plan->trial_days);

            $subscription = $lockedOwner->subscriptions()->create([
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::TRIALING,
                'starts_at' => $startsAt,
                'trial_ends_at' => $trialEndsAt,
                'current_period_starts_at' => $startsAt,
                'current_period_ends_at' => $trialEndsAt,
                'trial_claim_key' => 'user:'.$lockedOwner->id.':default-trial',
                'plan_snapshot' => $plan->subscriptionSnapshot(),
                'metadata' => ['source' => 'public_registration'],
            ]);

            $lockedOwner->trial_claimed_at = $startsAt;
            $lockedOwner->save();

            return $subscription;
        });
    }
}
