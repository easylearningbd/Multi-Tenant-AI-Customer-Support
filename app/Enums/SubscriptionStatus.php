<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case TRIALING = 'trialing';
    case ACTIVE = 'active';
    case PAST_DUE = 'past_due';
    case CANCELED = 'canceled';
    case EXPIRED = 'expired';
    case INCOMPLETE = 'incomplete';

    public function label(): string
    {
        return match ($this) {
            self::TRIALING => __('Trial'),
            self::ACTIVE => __('Active'),
            self::PAST_DUE => __('Past due'),
            self::CANCELED => __('Canceled'),
            self::EXPIRED => __('Expired'),
            self::INCOMPLETE => __('Incomplete'),
        };
    }
}
