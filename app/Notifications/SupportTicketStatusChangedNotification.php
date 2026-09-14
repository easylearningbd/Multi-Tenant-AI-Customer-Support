<?php

namespace App\Notifications;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class SupportTicketStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SupportTicket $ticket,
        private readonly SupportTicketStatus $previousStatus,
        private readonly User $admin,
    ) {
        $this->afterCommit();
        $this->onQueue('notifications');
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toArray(object $notifiable): array
    {
        return [
            'activity' => 'support_ticket_status_changed',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'subject' => $this->ticket->subject,
            'previous_status' => $this->previousStatus->value,
            'status' => $this->ticket->status->value,
            'staff_name' => $this->admin->name,
            'url' => route('support-tickets.show', $this->ticket),
        ];
    }
}
