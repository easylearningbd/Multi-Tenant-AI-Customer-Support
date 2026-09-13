<?php

namespace App\Actions;

use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use App\Events\BankTransferPaymentRejected;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class RejectBankTransferPayment
{
    /** @throws AuthorizationException */
    public function handle(Payment $payment, User $reviewer, string $reason): Payment
    {
        if ($reviewer->role !== UserRole::ADMIN) {
            throw new AuthorizationException('Only an administrator may reject payments.');
        }

        $reason = Str::limit(trim($reason), 2000, '');

        if ($reason === '') {
            throw new DomainException('A rejection reason is required.');
        }

        $rejected = false;

        $payment = DB::transaction(function () use ($payment, $reviewer, $reason, &$rejected): Payment {
            $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($lockedPayment->status === PaymentStatus::REJECTED) {
                return $lockedPayment;
            }

            if ($lockedPayment->status !== PaymentStatus::PENDING) {
                throw new DomainException('Only pending payments may be rejected.');
            }

            $invoice = Invoice::query()->where('payment_id', $lockedPayment->id)->lockForUpdate()->first();

            if (! $invoice) {
                throw new DomainException('The payment invoice is unavailable.');
            }

            $lockedPayment->status = PaymentStatus::REJECTED;
            $lockedPayment->rejection_reason = $reason;
            $lockedPayment->reviewed_at = now()->utc();
            $lockedPayment->reviewed_by = $reviewer->id;
            $lockedPayment->save();

            $invoice->status = PaymentStatus::REJECTED;
            $invoice->save();
            $rejected = true;

            return $lockedPayment;
        });

        if ($rejected) {
            try {
                BankTransferPaymentRejected::dispatch($payment);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $payment->refresh();
    }
}
