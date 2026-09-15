<?php

namespace App\Notifications;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class BankTransferPaymentStatusNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        $payment = $this->payment->loadMissing('subscription:id,current_period_ends_at');

        return [
            'activity' => 'bank_transfer_payment_'.$payment->status->value,
            'payment_id' => $payment->id,
            'reference' => $payment->reference,
            'plan_name' => $payment->plan_name_snapshot,
            'status' => $payment->status->value,
            'amount_minor' => $payment->expected_amount_minor,
            'currency' => $payment->currency,
            'activated_at' => $payment->paid_at?->toIso8601String(),
            'access_until' => $payment->subscription?->current_period_ends_at?->toIso8601String(),
            'billing_url' => route('billing.index'),
            'rejection_reason' => $payment->status === PaymentStatus::REJECTED
                ? $payment->rejection_reason
                : null,
        ];
    }
}
