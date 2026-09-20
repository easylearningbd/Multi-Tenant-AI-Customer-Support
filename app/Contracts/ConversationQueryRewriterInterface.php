<?php

namespace App\Contracts;

use App\DTOs\QueryRewriteResult;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\ConversationMessage;

interface ConversationQueryRewriterInterface
{
    public function rewrite(
        Bot $bot,
        Conversation $conversation,
        ConversationMessage $message,
        ChatCompletionProviderInterface $provider,
    ): QueryRewriteResult;
}
