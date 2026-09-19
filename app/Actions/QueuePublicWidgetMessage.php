<?php

namespace App\Actions;

use App\DTOs\ConversationOrigin;
use App\DTOs\QueuedConversationMessage;
use App\Models\VisitorSession;
use Illuminate\Validation\ValidationException;

final class QueuePublicWidgetMessage
{
    public function __construct(private readonly QueueConversationMessage $messages) {}

    public function handle(VisitorSession $session, string $body, string $idempotencyKey, ?string $conversationUuid): QueuedConversationMessage
    {
        $session->loadMissing(['user', 'bot.setting', 'bot.prechatFields']);
        if ($session->bot->setting?->prechat_enabled
            && $session->bot->prechatFields->isNotEmpty()
            && $session->prechat_completed_at === null) {
            throw ValidationException::withMessages(['prechat' => __('Complete the pre-chat form before sending a message.')]);
        }

        return $this->messages->execute(
            $session->user,
            $session->bot,
            $body,
            $idempotencyKey,
            $conversationUuid,
            new ConversationOrigin($session->channel->value, $session->visitor_identifier, $session->id),
        );
    }
}
