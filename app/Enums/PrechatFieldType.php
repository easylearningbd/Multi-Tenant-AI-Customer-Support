<?php

namespace App\Enums;

enum PrechatFieldType: string
{
    case TEXT = 'text';
    case EMAIL = 'email';
    case PHONE = 'phone';
    case SELECT = 'select';
    case TEXTAREA = 'textarea';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => __('Text'),
            self::EMAIL => __('Email'),
            self::PHONE => __('Phone'),
            self::SELECT => __('Select'),
            self::TEXTAREA => __('Long text'),
        };
    }
}
