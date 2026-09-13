<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class BankTransferPaymentSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Payment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toArray(object $notifiable): array
    {
        return [
            'activity' => 'bank_transfer_payment_submitted',
            'payment_id' => $this->payment->id,
            'reference' => $this->payment->reference,
            'subscriber_name' => $this->payment->user?->name ?? __('Deleted subscriber'),
            'plan_name' => $this->payment->plan_name_snapshot,
            'expected_amount_minor' => $this->payment->expected_amount_minor,
            'submitted_amount_minor' => $this->payment->submitted_amount_minor,
            'currency' => $this->payment->currency,
            'transaction_reference' => $this->payment->transaction_reference,
        ];
    }
}
