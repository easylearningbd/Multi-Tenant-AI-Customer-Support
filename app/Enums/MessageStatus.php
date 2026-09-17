<?php

namespace App\Enums;

enum MessageStatus: string
{
    case RECEIVED = 'received';
    case QUEUED = 'queued';
    case COMPLETED = 'completed';
    case FALLBACK = 'fallback';
    case FAILED = 'failed';
}
