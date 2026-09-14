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
            && $ticket->requester_id === $user->id
            && $ticket->archived_at === null;
    }

    public function manageAny(User $user): bool
    {
        return $user->role === UserRole::ADMIN;
    }

    public function manage(User $user, SupportTicket $ticket): bool
    {
        return $user->role === UserRole::ADMIN;
    }

    public function replyAsAdmin(User $user, SupportTicket $ticket): bool
    {
        return $this->manage($user, $ticket)
            && $ticket->archived_at === null
            && $ticket->status->acceptsAdminReplies();
    }

    public function updateStatus(User $user, SupportTicket $ticket): bool
    {
        return $this->manage($user, $ticket) && $ticket->archived_at === null;
    }

    public function archive(User $user, SupportTicket $ticket): bool
    {
        return $this->manage($user, $ticket) && $ticket->archived_at === null;
    }

    public function downloadAttachmentAsAdmin(User $user, SupportTicket $ticket, SupportTicketAttachment $attachment): bool
    {
        return $this->manage($user, $ticket)
            && $attachment->message?->support_ticket_id === $ticket->id;
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
