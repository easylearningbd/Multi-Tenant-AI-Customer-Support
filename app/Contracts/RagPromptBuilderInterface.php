<?php

namespace App\Contracts;

use App\DTOs\RagPrompt;
use App\DTOs\RagRetrievalResult;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\ConversationMessage;

interface RagPromptBuilderInterface
{
    public function build(Bot $bot, Conversation $conversation, ConversationMessage $message, RagRetrievalResult $retrieval): RagPrompt;
}
