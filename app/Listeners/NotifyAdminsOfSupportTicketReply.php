<?php

namespace App\Listeners;

use App\Enums\UserRole;
use App\Events\SupportTicketReplied;
use App\Models\User;
use App\Notifications\SupportTicketSubscriberRepliedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

final class NotifyAdminsOfSupportTicketReply implements ShouldQueue
{
    public string $queue = 'notifications';

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(SupportTicketReplied $event): void
    {
        $admins = User::query()->where('role', UserRole::ADMIN)->get();

        Notification::send($admins, new SupportTicketSubscriberRepliedNotification($event->ticket));
    }
}
