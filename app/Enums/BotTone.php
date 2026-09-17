<?php

namespace App\Enums;

enum BotTone: string
{
    case FRIENDLY = 'friendly';
    case PROFESSIONAL = 'professional';
    case CONCISE = 'concise';
    case EMPATHETIC = 'empathetic';

    public function label(): string
    {
        return match ($this) {
            self::FRIENDLY => __('Friendly'),
            self::PROFESSIONAL => __('Professional'),
            self::CONCISE => __('Concise'),
            self::EMPATHETIC => __('Empathetic'),
        };
    }
}
