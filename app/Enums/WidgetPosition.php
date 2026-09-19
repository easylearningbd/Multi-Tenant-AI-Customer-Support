<?php

namespace App\Enums;

enum WidgetPosition: string
{
    case BOTTOM_LEFT = 'bottom_left';
    case BOTTOM_RIGHT = 'bottom_right';

    public function label(): string
    {
        return match ($this) {
            self::BOTTOM_LEFT => __('Bottom left'),
            self::BOTTOM_RIGHT => __('Bottom right'),
        };
    }
}
