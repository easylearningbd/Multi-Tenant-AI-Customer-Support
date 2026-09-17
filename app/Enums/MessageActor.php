<?php

namespace App\Enums;

enum MessageActor: string
{
    case VISITOR = 'visitor';
    case AI = 'ai';
    case AGENT = 'agent';
    case SYSTEM = 'system';
}
