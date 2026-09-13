<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\BankTransferPaymentSubmitted;
use App\Models\User;
use App\Notifications\BankTransferPaymentSubmittedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

final class NotifyAdminsOfBankTransferPayment implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(BankTransferPaymentSubmitted $event): void
    {
        $event->payment->loadMissing('user:id,name');
        $admins = User::query()->where('role', UserRole::ADMIN)->get();

        Notification::send($admins, new BankTransferPaymentSubmittedNotification($event->payment));
    }
}
