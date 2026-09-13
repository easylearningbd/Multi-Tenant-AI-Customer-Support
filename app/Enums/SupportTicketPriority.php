<?php

namespace App\Enums;

enum SupportTicketPriority: string
{
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case URGENT = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::LOW => __('Low'),
            self::MEDIUM => __('Medium'),
            self::HIGH => __('High'),
            self::URGENT => __('Urgent'),
        };
    }
}
