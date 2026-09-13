<?php

namespace App\Services;

use App\Exceptions\BankTransferUnavailable;
use App\Models\Plan;
use App\Models\User;

final class BankTransferPlanEligibility
{
    public function __construct(
        private readonly BankTransferConfiguration $configuration,
        private readonly CurrentSubscriptionResolver $subscriptions,
    ) {}

    public function assertEligible(User $billingOwner, Plan $plan): void
    {
        if (! $billingOwner->isBillingOwner()) {
            throw new BankTransferUnavailable('Only a billing owner may submit a payment.');
        }

        if (! $this->configuration->isComplete()) {
            throw new BankTransferUnavailable('Bank transfer is not configured.');
        }

        if (! $plan->supportsBankTransferPayment()) {
            throw new BankTransferUnavailable('This plan is not eligible for bank transfer.');
        }

        if ($plan->currency !== $this->configuration->currency()) {
            throw new BankTransferUnavailable('This plan currency is not supported for bank transfer.');
        }

        $current = $this->subscriptions->for($billingOwner);

        if ($current?->plan_id === $plan->id && $current->grantsEntitlements()) {
            throw new BankTransferUnavailable('The selected plan is already active.');
        }
    }

    public function canStart(User $billingOwner, Plan $plan): bool
    {
        try {
            $this->assertEligible($billingOwner, $plan);

            return true;
        } catch (BankTransferUnavailable) {
            return false;
        }
    }
}
