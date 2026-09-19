<?php

namespace App\Actions;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\VisitorSession;
use App\Services\ConversationTransitionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RequestPublicHandoff
{
    public function __construct(private readonly ConversationTransitionService $transitions) {}

    public function handle(VisitorSession $session, string $conversationUuid): Conversation
    {
        abort_unless($session->bot()->with('setting')->firstOrFail()->setting?->offer_human_handoff === true, 404);

        return DB::transaction(function () use ($session, $conversationUuid): Conversation {
            $conversation = Conversation::query()->where('uuid', $conversationUuid)
                ->where('user_id', $session->user_id)->where('bot_id', $session->bot_id)
                ->where('visitor_session_id', $session->id)->lockForUpdate()->firstOrFail();
            if ($conversation->status === ConversationStatus::NEEDS_HUMAN
                || $conversation->status === ConversationStatus::OPEN_MANUAL) {
                return $conversation;
            }
            if (! $conversation->status->acceptsAiReplies()) {
                throw ValidationException::withMessages(['conversation' => __('This conversation cannot request a handoff.')]);
            }

            $this->transitions->transition($conversation, ConversationStatus::NEEDS_HUMAN);

            return $conversation->refresh();
        }, 3);
    }
}
