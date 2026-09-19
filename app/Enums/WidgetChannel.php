<?php

namespace App\Enums;

enum WidgetChannel: string
{
    case EMBEDDED = 'widget';
    case HOSTED = 'hosted';
}
