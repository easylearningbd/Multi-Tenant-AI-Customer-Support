<?php

namespace App\Enums;

enum ConversationMessageType: string
{
    case TEXT = 'text';
    case ATTACHMENT = 'attachment';
    case SYSTEM = 'system';
}
