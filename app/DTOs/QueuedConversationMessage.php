<?php

namespace App\DTOs;

use App\Models\Conversation;
use App\Models\ConversationMessage;

final readonly class QueuedConversationMessage
{
    public function __construct(
        public Conversation $conversation,
        public ConversationMessage $message,
        public bool $created,
    ) {}
}
