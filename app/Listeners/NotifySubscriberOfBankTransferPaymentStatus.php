<?php

namespace App\Listeners;

use App\Events\BankTransferPaymentApproved;
use App\Notifications\BankTransferPaymentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifySubscriberOfBankTransferPaymentStatus implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(BankTransferPaymentApproved $event): void
    {
        $subscriber = $event->payment->user;

        if ($subscriber) {
            $subscriber->notify(new BankTransferPaymentStatusNotification($event->payment));
        }
    }
}
