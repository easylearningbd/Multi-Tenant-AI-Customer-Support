<?php

namespace App\Contracts;

use App\DTOs\RagRetrievalResult;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\ConversationMessage;

interface KnowledgeRetrieverInterface
{
    public function retrieve(Bot $bot, Conversation $conversation, ConversationMessage $message): RagRetrievalResult;
}
