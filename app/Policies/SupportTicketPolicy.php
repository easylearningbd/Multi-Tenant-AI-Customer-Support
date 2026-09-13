<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\SupportTicket;
use App\Models\SupportTicketAttachment;
use App\Models\User;

final class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::USER;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::USER;
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $user->role === UserRole::USER
            && $ticket->requester_id === $user->id;
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $this->view($user, $ticket)
            && $ticket->status->acceptsSubscriberReplies();
    }

    public function downloadAttachment(User $user, SupportTicket $ticket, SupportTicketAttachment $attachment): bool
    {
        return $this->view($user, $ticket)
            && $attachment->message?->support_ticket_id === $ticket->id;
    }
}
