<?php

namespace App\Services;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

final class ConversationInboxQuery
{
    /** @return array{conversations: LengthAwarePaginator, counts: array<string, int>} */
    public function for(User $subscriber, string $filter, ?string $search): array
    {
        $base = Conversation::query()
            ->ownedBy($subscriber)
            ->with([
                'bot:id,name,display_name',
                'visitorSession:id,prechat_data',
                'assignedAgent:id,name',
            ]);

        $this->search($base, $search);
        $counts = [
            'all' => (clone $base)->whereNotIn('status', [ConversationStatus::ARCHIVED, ConversationStatus::SPAM])->count(),
            'open' => (clone $base)->whereIn('status', [ConversationStatus::OPEN_AI, ConversationStatus::NEEDS_HUMAN, ConversationStatus::OPEN_MANUAL])->count(),
            'manual' => (clone $base)->whereIn('status', [ConversationStatus::NEEDS_HUMAN, ConversationStatus::OPEN_MANUAL])->count(),
            'resolved' => (clone $base)->where('status', ConversationStatus::RESOLVED)->count(),
            'archived' => (clone $base)->where('status', ConversationStatus::ARCHIVED)->count(),
        ];

        $this->filter($base, $filter);
        $conversations = $base->orderByDesc('last_message_at')->orderByDesc('id')
            ->paginate(max(5, (int) config('neuraldesk.conversations.page_size', 20)))
            ->withQueryString();

        return compact('conversations', 'counts');
    }

    private function search(Builder $query, ?string $search): void
    {
        $search = trim((string) $search);
        if ($search === '') {
            return;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $like = '%'.$escaped.'%';
        $query->where(function (Builder $nested) use ($like): void {
            $nested->where('visitor_identifier', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhere('last_message_preview', 'like', $like)
                ->orWhereHas('bot', fn (Builder $bots) => $bots
                    ->where('name', 'like', $like)
                    ->orWhere('display_name', 'like', $like));
        });
    }

    private function filter(Builder $query, string $filter): void
    {
        match ($filter) {
            'open' => $query->whereIn('status', [ConversationStatus::OPEN_AI, ConversationStatus::NEEDS_HUMAN, ConversationStatus::OPEN_MANUAL]),
            'manual' => $query->whereIn('status', [ConversationStatus::NEEDS_HUMAN, ConversationStatus::OPEN_MANUAL]),
            'resolved' => $query->where('status', ConversationStatus::RESOLVED),
            'archived' => $query->where('status', ConversationStatus::ARCHIVED),
            default => $query->whereNotIn('status', [ConversationStatus::ARCHIVED, ConversationStatus::SPAM]),
        };
    }
}
