<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\PlanInterval;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Events\BankTransferPaymentApproved;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ApproveBankTransferPayment
{
    /** @throws AuthorizationException */
    public function handle(Payment $payment, User $reviewer): Payment
    {
        if ($reviewer->role !== UserRole::ADMIN) {
            throw new AuthorizationException('Only an administrator may approve payments.');
        }

        $approved = false;

        $payment = DB::transaction(function () use ($payment, $reviewer, &$approved): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status === PaymentStatus::PAID) {
                return $lockedPayment;
            }

            if ($lockedPayment->status !== PaymentStatus::PENDING) {
                throw new DomainException('Only pending payments may be approved.');
            }

            if ($lockedPayment->subscription_id !== null) {
                throw new DomainException('The pending payment already references a subscription.');
            }

            $billingOwner = $lockedPayment->user_id
                ? User::query()->lockForUpdate()->find($lockedPayment->user_id)
                : null;
            $plan = Plan::query()->lockForUpdate()->find($lockedPayment->plan_id);
            $invoice = Invoice::query()->where('payment_id', $lockedPayment->id)->lockForUpdate()->first();

            if (! $billingOwner || ! $billingOwner->isBillingOwner()) {
                throw new DomainException('The payment billing owner is unavailable.');
            }

            if (! $plan || ! $plan->supportsBankTransferPayment()) {
                throw new DomainException('The referenced plan is no longer eligible for activation.');
            }

            if (! $invoice) {
                throw new DomainException('The payment invoice is unavailable.');
            }

            $now = CarbonImmutable::now('UTC');
            $current = Subscription::query()
                ->where('user_id', $billingOwner->id)
                ->lockForUpdate()
                ->latest('starts_at')
                ->latest('id')
                ->get()
                ->first(fn (Subscription $subscription): bool => $subscription->grantsEntitlements($now));
            $periodBase = $now;

            if ($current) {
                if ($current->plan_id === $plan->id
                    && $current->provider === 'bank_transfer'
                    && $current->current_period_ends_at?->greaterThan($periodBase)) {
                    $periodBase = CarbonImmutable::instance($current->current_period_ends_at);
                }

                $current->status = SubscriptionStatus::CANCELED;
                $current->canceled_at = $now;
                $current->ends_at = $now;
                $current->current_period_ends_at = $now;
                $current->save();
            }

            $periodEndsAt = match ($lockedPayment->plan_interval_snapshot) {
                PlanInterval::MONTHLY => $periodBase->addMonthNoOverflow(),
                PlanInterval::YEARLY => $periodBase->addYearNoOverflow(),
                PlanInterval::TRIAL => throw new DomainException('Trial payments cannot be approved.'),
            };

            $subscription = new Subscription;
            $subscription->user_id = $billingOwner->id;
            $subscription->plan_id = $plan->id;
            $subscription->status = SubscriptionStatus::ACTIVE;
            $subscription->starts_at = $now;
            $subscription->current_period_starts_at = $now;
            $subscription->current_period_ends_at = $periodEndsAt;
            $subscription->provider = 'bank_transfer';
            $subscription->provider_subscription_id = $lockedPayment->reference;
            $subscription->plan_snapshot = $lockedPayment->plan_snapshot;
            $subscription->metadata = ['payment_id' => $lockedPayment->id, 'manual_period' => true];
            $subscription->save();

            $lockedPayment->subscription_id = $subscription->id;
            $lockedPayment->status = PaymentStatus::PAID;
            $lockedPayment->paid_at = $now;
            $lockedPayment->reviewed_at = $now;
            $lockedPayment->reviewed_by = $reviewer->id;
            $lockedPayment->save();

            $invoice->status = PaymentStatus::PAID;
            $invoice->paid_at = $now;
            $invoice->save();

            $approved = true;

            return $lockedPayment;
        });

        if ($approved) {
            try {
                BankTransferPaymentApproved::dispatch($payment);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $payment->refresh();
    }
}
