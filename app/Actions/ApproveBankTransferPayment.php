<?php

namespace App\Actions;

use App\Enums\PaymentMethod;
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
use App\Services\UsagePeriodResolver;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ApproveBankTransferPayment
{
    public function __construct(private readonly UsagePeriodResolver $usagePeriods) {}

    /** @throws AuthorizationException */
    public function handle(
        Payment $payment,
        User $reviewer,
        bool $amountMismatchAcknowledged = false,
        ?string $reviewNote = null,
    ): Payment {
        if ($reviewer->role !== UserRole::ADMIN) {
            throw new AuthorizationException('Only an administrator may approve payments.');
        }

        $approved = false;

        $payment = DB::transaction(function () use ($payment, $reviewer, $amountMismatchAcknowledged, $reviewNote, &$approved): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status === PaymentStatus::PAID) {
                $activatedSubscriptionExists = $lockedPayment->subscription_id !== null
                    && Subscription::query()
                        ->whereKey($lockedPayment->subscription_id)
                        ->where('user_id', $lockedPayment->user_id)
                        ->where('plan_id', $lockedPayment->plan_id)
                        ->exists();

                if (! $activatedSubscriptionExists) {
                    throw new DomainException('The paid payment does not reference its activated subscription.');
                }

                return $lockedPayment;
            }

            if ($lockedPayment->status !== PaymentStatus::PENDING) {
                throw new DomainException('Only pending payments may be approved.');
            }

            if ($lockedPayment->payment_method !== PaymentMethod::BANK_TRANSFER) {
                throw new DomainException('Only bank-transfer payments may be approved through this workflow.');
            }

            if ($lockedPayment->hasAmountMismatch() && ! $amountMismatchAcknowledged) {
                throw new DomainException('The submitted amount differs from the expected amount and requires explicit acknowledgement.');
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

            $snapshotPrice = $lockedPayment->plan_snapshot['price_minor'] ?? null;
            $snapshotCurrency = strtoupper((string) ($lockedPayment->plan_snapshot['currency'] ?? ''));
            $snapshotInterval = (string) ($lockedPayment->plan_snapshot['interval'] ?? '');

            if (! is_int($snapshotPrice) && ! ctype_digit((string) $snapshotPrice)) {
                throw new DomainException('The payment price snapshot is invalid.');
            }

            if ((int) $snapshotPrice !== $lockedPayment->expected_amount_minor
                || $snapshotCurrency !== strtoupper($lockedPayment->currency)
                || $snapshotInterval !== $lockedPayment->plan_interval_snapshot->value
                || $invoice->subtotal_minor !== $lockedPayment->expected_amount_minor
                || $invoice->total_minor !== $lockedPayment->expected_amount_minor
                || strtoupper($invoice->currency) !== strtoupper($lockedPayment->currency)) {
                throw new DomainException('The payment currency or amount snapshot does not match its invoice.');
            }

            $proof = $lockedPayment->proof()->first();

            if (! $proof || ! $proof->hasManagedPath()) {
                throw new DomainException('A payment proof is required before approval.');
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
            $usagePeriodAnchor = $now;

            if ($current) {
                $usagePeriodAnchor = $this->usagePeriods->monthly($current, $now)->startsAt;
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
            $subscription->metadata = [
                'payment_id' => $lockedPayment->id,
                'manual_period' => true,
                'usage_period_anchor' => $usagePeriodAnchor->toIso8601String(),
            ];
            $subscription->save();

            $lockedPayment->subscription_id = $subscription->id;
            $lockedPayment->status = PaymentStatus::PAID;
            $lockedPayment->paid_at = $now;
            $lockedPayment->reviewed_at = $now;
            $lockedPayment->reviewed_by = $reviewer->id;
            $lockedPayment->metadata = array_replace($lockedPayment->metadata ?? [], [
                'admin_review_note' => $this->cleanReviewNote($reviewNote),
                'amount_mismatch_acknowledged' => $lockedPayment->hasAmountMismatch(),
                'previous_subscription_id' => $current?->id,
                'activated_subscription_id' => $subscription->id,
                'review_transition' => PaymentStatus::PENDING->value.'->'.PaymentStatus::PAID->value,
            ]);
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

    private function cleanReviewNote(?string $note): ?string
    {
        $note = Str::limit(trim((string) $note), 2000, '');

        return $note !== '' ? $note : null;
    }
}
