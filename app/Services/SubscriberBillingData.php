<?php

namespace App\Services;

use App\Enums\PlanInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class SubscriberBillingData
{
    public function __construct(
        private readonly CurrentSubscriptionResolver $subscriptions,
        private readonly BankTransferConfiguration $bankTransfer,
    ) {}

    /** @return array<string, mixed> */
    public function for(User $billingOwner): array
    {
        $subscription = $this->subscriptions->for($billingOwner);
        $usageValues = $this->usageFor($billingOwner, $subscription);

        return [
            'subscription' => $subscription,
            'current' => $this->currentPlan($subscription, $usageValues),
            'availablePlans' => $this->availablePlans($subscription, $billingOwner),
            'invoices' => $billingOwner->payments()
                ->with('invoice:id,payment_id,number,status,issued_at,paid_at')
                ->latest('submitted_at')
                ->latest('id')
                ->paginate(10, pageName: 'payments'),
            'invoiceSourceAvailable' => true,
            'checkoutAvailable' => $this->bankTransfer->isComplete(),
            'billingOwnerId' => $billingOwner->id,
        ];
    }

    /** @param array<string, int> $usage */
    private function currentPlan(?Subscription $subscription, array $usage): ?array
    {
        if (! $subscription) {
            return null;
        }

        $status = $subscription->effectiveStatus();

        return [
            'name' => $subscription->planName(),
            'status' => $status,
            'statusLabel' => $status->label(),
            'dateLabel' => $this->dateLabel($subscription, $status),
            'features' => $subscription->featureList(),
            'usage' => collect(Plan::LIMITS)->map(function (string $label, string $key) use ($subscription, $usage): array {
                $used = $usage[$key] ?? 0;
                $limit = $subscription->limitFor($key);

                return [
                    'key' => $key,
                    'label' => __($label),
                    'used' => $used,
                    'limit' => $limit,
                    'unlimited' => $limit === 0,
                    'percentage' => $limit === 0 ? 0 : min(100, (int) round(($used / $limit) * 100)),
                    'sourceAvailable' => false,
                ];
            })->values(),
        ];
    }

    /** @return array{label: string, date: ?CarbonInterface} */
    private function dateLabel(Subscription $subscription, SubscriptionStatus $status): array
    {
        if ($status === SubscriptionStatus::TRIALING) {
            return ['label' => __('Trial ends on'), 'date' => $subscription->trial_ends_at];
        }

        if ($status === SubscriptionStatus::ACTIVE) {
            return [
                'label' => $subscription->provider === 'bank_transfer' ? __('Active until') : __('Renews on'),
                'date' => $subscription->current_period_ends_at,
            ];
        }

        if ($status === SubscriptionStatus::CANCELED) {
            return ['label' => __('Access ends on'), 'date' => $subscription->ends_at ?? $subscription->current_period_ends_at];
        }

        if ($status === SubscriptionStatus::EXPIRED) {
            return [
                'label' => __('Expired on'),
                'date' => $subscription->trial_ends_at
                    ?? $subscription->ends_at
                    ?? $subscription->current_period_ends_at,
            ];
        }

        return ['label' => __('Current period ends on'), 'date' => $subscription->current_period_ends_at];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function availablePlans(?Subscription $subscription, User $billingOwner): Collection
    {
        return Plan::query()
            ->availableForSelection()
            ->ordered()
            ->get([
                'id', 'name', 'slug', 'description', 'price_minor', 'currency', 'interval',
                'trial_days', 'custom_pricing', 'is_active', 'sort_order', 'features', 'limits',
            ])
            ->map(function (Plan $plan) use ($subscription, $billingOwner): array {
                $isCurrent = $subscription?->plan_id === $plan->id && $subscription->grantsEntitlements();
                $trialUsed = $plan->interval === PlanInterval::TRIAL
                    && $billingOwner->trial_claimed_at !== null
                    && ! $isCurrent;
                $canPay = $this->bankTransfer->isComplete()
                    && $plan->supportsBankTransferPayment()
                    && $plan->currency === $this->bankTransfer->currency()
                    && ! $isCurrent;

                return [
                    'model' => $plan,
                    'isCurrent' => $isCurrent,
                    'trialUsed' => $trialUsed,
                    'canPay' => $canPay,
                    'priceLabel' => $plan->custom_pricing ? __('Custom') : $plan->formattedPrice(),
                    'intervalLabel' => $plan->custom_pricing ? __('Contact sales') : $plan->interval->label(),
                    'features' => array_values($plan->features ?? []),
                ];
            });
    }

    /** @return array<string, int> */
    private function usageFor(User $billingOwner, ?Subscription $subscription): array
    {
        // Product usage tables are not present yet. Keep explicit owner context here so each
        // future aggregate is added as an owner-scoped query rather than as a global count.
        return array_fill_keys(array_keys(Plan::LIMITS), 0);
    }
}
