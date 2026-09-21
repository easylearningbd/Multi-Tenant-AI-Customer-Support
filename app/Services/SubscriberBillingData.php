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
        private readonly PlanUsageService $usage,
    ) {}

    /** @return array<string, mixed> */
    public function for(User $billingOwner): array
    {
        $subscription = $this->subscriptions->for($billingOwner);

        return [
            'subscription' => $subscription,
            'current' => $this->currentPlan($billingOwner, $subscription),
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

    private function currentPlan(User $billingOwner, ?Subscription $subscription): ?array
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
            'usage' => $this->usage->summary($billingOwner, $subscription),
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
}
