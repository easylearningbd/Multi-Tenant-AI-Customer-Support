<?php

namespace App\Services;

use App\Enums\ConversationHandlingMode;
use App\Enums\ConversationStatus;
use App\Enums\MessageActor;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageConversation
{
    public function __construct(private readonly ConversationTransitionService $transitions) {}

    public function changeMode(Conversation $conversation, User $actor, ConversationHandlingMode $mode): Conversation
    {
        return $this->locked($conversation, $actor, function (Conversation $locked) use ($actor, $mode): void {
            if (in_array($locked->status, [ConversationStatus::RESOLVED, ConversationStatus::ARCHIVED, ConversationStatus::SPAM], true)) {
                throw ValidationException::withMessages(['mode' => __('Reopen this conversation before changing automatic replies.')]);
            }

            $next = $mode === ConversationHandlingMode::AI
                ? ConversationStatus::OPEN_AI
                : ConversationStatus::OPEN_MANUAL;
            if ($locked->status !== $next) {
                $this->transitions->transition($locked, $next, $actor);
            }
            $locked->forceFill(['assigned_to' => $mode === ConversationHandlingMode::MANUAL ? $actor->id : null])->save();
        });
    }

    public function resolve(Conversation $conversation, User $actor): Conversation
    {
        return $this->locked($conversation, $actor, function (Conversation $locked) use ($actor): void {
            if (in_array($locked->status, [ConversationStatus::ARCHIVED, ConversationStatus::SPAM], true)) {
                throw ValidationException::withMessages(['conversation' => __('Archived conversations cannot be resolved.')]);
            }
            $this->transitions->transition($locked, ConversationStatus::RESOLVED, $actor);
        });
    }

    public function reopen(Conversation $conversation, User $actor): Conversation
    {
        return $this->locked($conversation, $actor, function (Conversation $locked) use ($actor): void {
            if ($locked->status !== ConversationStatus::RESOLVED) {
                throw ValidationException::withMessages(['conversation' => __('Only resolved conversations can be reopened.')]);
            }
            $next = $locked->effectiveHandlingMode() === ConversationHandlingMode::MANUAL
                ? ConversationStatus::OPEN_MANUAL
                : ConversationStatus::OPEN_AI;
            $this->transitions->transition($locked, $next, $actor);
        });
    }

    public function archive(Conversation $conversation, User $actor): Conversation
    {
        return $this->locked($conversation, $actor, function (Conversation $locked) use ($actor): void {
            if ($locked->status !== ConversationStatus::ARCHIVED) {
                $this->transitions->transition($locked, ConversationStatus::ARCHIVED, $actor);
            }
        });
    }

    public function markRead(Conversation $conversation, User $actor): Conversation
    {
        return $this->locked($conversation, $actor, function (Conversation $locked): void {
            ConversationMessage::query()->forTenantBot($locked->user_id, $locked->bot_id)
                ->where('conversation_id', $locked->id)
                ->where('actor_type', MessageActor::VISITOR)
                ->whereNull('read_at')
                ->update(['read_at' => now('UTC'), 'updated_at' => now('UTC')]);
            $locked->forceFill(['unread_count' => 0])->save();
        });
    }

    public function delete(Conversation $conversation, User $actor): void
    {
        DB::transaction(function () use ($conversation, $actor): void {
            $locked = Conversation::query()->ownedBy($actor)->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $locked->delete();
        }, 3);
    }

    private function locked(Conversation $conversation, User $actor, callable $operation): Conversation
    {
        return DB::transaction(function () use ($conversation, $actor, $operation): Conversation {
            $locked = Conversation::query()->ownedBy($actor)->whereKey($conversation->id)->lockForUpdate()->firstOrFail();
            $operation($locked);

            return $locked->refresh();
        }, 3);
    }
}
