<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use DomainException;

final class ConversationTransitionService
{
    /** @var array<string, list<ConversationStatus>> */
    private const ALLOWED = [
        'open_ai' => [ConversationStatus::NEEDS_HUMAN, ConversationStatus::OPEN_MANUAL, ConversationStatus::RESOLVED, ConversationStatus::SPAM],
        'needs_human' => [ConversationStatus::OPEN_MANUAL, ConversationStatus::OPEN_AI, ConversationStatus::RESOLVED, ConversationStatus::SPAM],
        'open_manual' => [ConversationStatus::OPEN_AI, ConversationStatus::RESOLVED, ConversationStatus::SPAM],
        'resolved' => [ConversationStatus::OPEN_AI, ConversationStatus::ARCHIVED],
        'archived' => [],
        'spam' => [ConversationStatus::ARCHIVED],
    ];

    public function transition(Conversation $conversation, ConversationStatus $next): void
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
        }
        if ($next === ConversationStatus::RESOLVED) {
            $attributes['resolved_at'] = now('UTC');
        }
        $conversation->forceFill($attributes)->save();
    }
}
