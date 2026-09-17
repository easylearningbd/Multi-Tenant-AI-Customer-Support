<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case OPEN_AI = 'open_ai';
    case NEEDS_HUMAN = 'needs_human';
    case OPEN_MANUAL = 'open_manual';
    case RESOLVED = 'resolved';
    case ARCHIVED = 'archived';
    case SPAM = 'spam';

    public function acceptsAiReplies(): bool
    {
        return $this === self::OPEN_AI;
    }
}
