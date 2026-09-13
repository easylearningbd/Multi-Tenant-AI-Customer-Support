<?php

namespace App\Events;

use App\Models\SupportTicket;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SupportTicketCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly SupportTicket $ticket) {}
}
