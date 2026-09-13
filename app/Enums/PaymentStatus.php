<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case REJECTED = 'rejected';
    case CANCELED = 'canceled';
    case EXPIRED = 'expired';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => __('Pending'),
            self::PAID => __('Paid'),
            self::REJECTED => __('Rejected'),
            self::CANCELED => __('Canceled'),
            self::EXPIRED => __('Expired'),
            self::REFUNDED => __('Refunded'),
        };
    }
}
