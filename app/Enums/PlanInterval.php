<?php

namespace App\Enums;

enum PlanInterval: string
{
    case TRIAL = 'trial';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::TRIAL => __('Trial'),
            self::MONTHLY => __('Month'),
            self::YEARLY => __('Year'),
        };
    }

    public function isRecurring(): bool
    {
        return $this !== self::TRIAL;
    }
}
