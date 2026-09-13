<?php

namespace App\Listeners;

use App\Events\BankTransferPaymentRejected;
use App\Notifications\BankTransferPaymentStatusNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

final class NotifySubscriberOfRejectedBankTransferPayment implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(BankTransferPaymentRejected $event): void
    {
        $event->payment->user?->notify(new BankTransferPaymentStatusNotification($event->payment));
    }
}
