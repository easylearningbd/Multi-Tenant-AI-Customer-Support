<?php

namespace App\Services;

use App\Enums\ConversationHandlingMode;
use App\Enums\ConversationMessageType;
use App\Enums\ConversationStatus;
use App\Enums\MessageActor;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

final class ConversationTransitionService
{
    /** @var array<string, list<ConversationStatus>> */
    private const ALLOWED = [
        'open_ai' => [ConversationStatus::NEEDS_HUMAN, ConversationStatus::OPEN_MANUAL, ConversationStatus::RESOLVED, ConversationStatus::ARCHIVED, ConversationStatus::SPAM],
        'needs_human' => [ConversationStatus::OPEN_MANUAL, ConversationStatus::OPEN_AI, ConversationStatus::RESOLVED, ConversationStatus::ARCHIVED, ConversationStatus::SPAM],
        'open_manual' => [ConversationStatus::OPEN_AI, ConversationStatus::RESOLVED, ConversationStatus::ARCHIVED, ConversationStatus::SPAM],
        'resolved' => [ConversationStatus::OPEN_AI, ConversationStatus::OPEN_MANUAL, ConversationStatus::ARCHIVED],
        'archived' => [],
        'spam' => [ConversationStatus::ARCHIVED],
    ];

    public function transition(Conversation $conversation, ConversationStatus $next, ?User $actor = null): void
    {
        $current = $conversation->status;
        if ($current === $next) {
            return;
        }
        if (! in_array($next, self::ALLOWED[$current->value] ?? [], true)) {
            throw new DomainException("Conversation cannot transition from {$current->value} to {$next->value}.");
        }

        $attributes = ['status' => $next];
        if ($next === ConversationStatus::NEEDS_HUMAN) {
            $attributes['handoff_requested_at'] = $conversation->handoff_requested_at ?? now('UTC');
            $attributes['handling_mode'] = ConversationHandlingMode::MANUAL;
            $attributes['unread_count'] = $conversation->unread_count + 1;
        }
        if ($next === ConversationStatus::OPEN_MANUAL) {
            $attributes['handling_mode'] = ConversationHandlingMode::MANUAL;
        }
        if ($next === ConversationStatus::OPEN_AI) {
            $attributes['handling_mode'] = ConversationHandlingMode::AI;
            $attributes['handoff_requested_at'] = null;
            $attributes['resolved_at'] = null;
            $attributes['resolved_by'] = null;
            $attributes['archived_at'] = null;
        }
        if ($next === ConversationStatus::RESOLVED) {
            $attributes['resolved_at'] = now('UTC');
            $attributes['resolved_by'] = $actor?->id;
        }
        if ($next === ConversationStatus::ARCHIVED) {
            $attributes['archived_at'] = now('UTC');
        }
        $conversation->forceFill($attributes)->save();

        $this->recordSystemMessage($conversation, $current, $next, $actor);
    }

    private function recordSystemMessage(Conversation $conversation, ConversationStatus $from, ConversationStatus $to, ?User $actor): void
    {
        $labels = [
            ConversationStatus::NEEDS_HUMAN->value => __('A visitor requested help from a person.'),
            ConversationStatus::OPEN_MANUAL->value => __(':name took over this conversation.', ['name' => $actor?->name ?? __('An agent')]),
            ConversationStatus::OPEN_AI->value => __('Automatic replies were turned on.'),
            ConversationStatus::RESOLVED->value => __(':name resolved this conversation.', ['name' => $actor?->name ?? __('An agent')]),
            ConversationStatus::ARCHIVED->value => __(':name archived this conversation.', ['name' => $actor?->name ?? __('An agent')]),
            ConversationStatus::SPAM->value => __('This conversation was marked as spam.'),
        ];
        $body = $labels[$to->value] ?? __('Conversation status changed from :from to :to.', [
            'from' => $from->value,
            'to' => $to->value,
        ]);

        $message = new ConversationMessage;
        $message->uuid = (string) Str::uuid();
        $message->user_id = $conversation->user_id;
        $message->bot_id = $conversation->bot_id;
        $message->conversation_id = $conversation->id;
        $message->fill([
            'sender_id' => $actor?->id,
            'actor_type' => MessageActor::SYSTEM,
            'message_type' => ConversationMessageType::SYSTEM,
            'status' => MessageStatus::COMPLETED,
            'body' => $body,
            'delivered_at' => now('UTC'),
        ]);
        $message->save();

        $conversation->forceFill([
            'last_message_at' => $message->created_at,
            'last_message_preview' => Str::limit(Str::squish($body), 500, ''),
            'last_message_sender_type' => MessageActor::SYSTEM->value,
        ])->save();
    }
}
