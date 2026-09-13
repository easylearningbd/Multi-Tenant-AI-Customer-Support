<?php

namespace App\Events;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SupportTicketReplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly SupportTicket $ticket,
        public readonly SupportTicketMessage $message,
    ) {}
}
