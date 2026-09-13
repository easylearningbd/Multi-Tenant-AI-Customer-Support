<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\SupportTicketCreated;
use App\Models\User;
use App\Notifications\NewSupportTicketNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

final class NotifyAdminsOfNewSupportTicket implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(SupportTicketCreated $event): void
    {
        $admins = User::query()->where('role', UserRole::ADMIN)->get();

        Notification::send($admins, new NewSupportTicketNotification($event->ticket));
    }
}
