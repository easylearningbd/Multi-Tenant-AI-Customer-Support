<?php

namespace App\Enums;

enum SupportTicketStatus: string
{
    case OPEN = 'open';
    case AWAITING_SUPPORT = 'awaiting_support';
    case AWAITING_USER = 'awaiting_user';
    case RESOLVED = 'resolved';
    case CLOSED = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => __('Open'),
            self::AWAITING_SUPPORT => __('Awaiting support'),
            self::AWAITING_USER => __('Awaiting you'),
            self::RESOLVED => __('Resolved'),
            self::CLOSED => __('Closed'),
        };
    }

    public function acceptsSubscriberReplies(): bool
    {
        return ! in_array($this, [self::RESOLVED, self::CLOSED], true);
    }
}
