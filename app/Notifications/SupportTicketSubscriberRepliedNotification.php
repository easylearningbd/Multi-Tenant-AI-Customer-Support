<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

final class SupportTicketSubscriberRepliedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly SupportTicket $ticket) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, int|string> */
    public function toArray(object $notifiable): array
    {
        return [
            'activity' => 'support_ticket_subscriber_replied',
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference,
            'subject' => $this->ticket->subject,
            'priority' => $this->ticket->priority->value,
        ];
    }
}
