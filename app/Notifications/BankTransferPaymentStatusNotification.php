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
        return [
            'activity' => 'bank_transfer_payment_'.$this->payment->status->value,
            'payment_id' => $this->payment->id,
            'reference' => $this->payment->reference,
            'plan_name' => $this->payment->plan_name_snapshot,
            'status' => $this->payment->status->value,
            'rejection_reason' => $this->payment->status === PaymentStatus::REJECTED
                ? $this->payment->rejection_reason
                : null,
        ];
    }
}
