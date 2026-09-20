<?php

namespace App\Models;

use App\Enums\ConversationHandlingMode;
use App\Enums\ConversationStatus;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'status', 'handling_mode', 'channel', 'visitor_identifier', 'subject', 'started_at',
        'last_message_at', 'last_message_preview', 'last_message_sender_type', 'unread_count',
        'handoff_requested_at', 'ai_resolved_at', 'resolved_at', 'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'handling_mode' => ConversationHandlingMode::class,
            'unread_count' => 'integer',
            'started_at' => 'immutable_datetime',
            'last_message_at' => 'immutable_datetime',
            'handoff_requested_at' => 'immutable_datetime',
            'ai_resolved_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeOwnedBy(Builder $query, User $owner): Builder
    {
        return $query->where('user_id', $owner->id);
    }

    public function scopeForBot(Builder $query, Bot $bot): Builder
    {
        return $query->where('user_id', $bot->user_id)->where('bot_id', $bot->id);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function visitorSession(): BelongsTo
    {
        return $this->belongsTo(VisitorSession::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ConversationAttachment::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function effectiveHandlingMode(): ConversationHandlingMode
    {
        if ($this->handling_mode instanceof ConversationHandlingMode) {
            return $this->handling_mode;
        }

        return in_array($this->status, [ConversationStatus::NEEDS_HUMAN, ConversationStatus::OPEN_MANUAL], true)
            ? ConversationHandlingMode::MANUAL
            : ConversationHandlingMode::AI;
    }

    public function acceptsAiReplies(): bool
    {
        return $this->status === ConversationStatus::OPEN_AI
            && $this->effectiveHandlingMode() === ConversationHandlingMode::AI
            && $this->deleted_at === null;
    }

    public function acceptsAgentReplies(): bool
    {
        return in_array($this->status, [ConversationStatus::NEEDS_HUMAN, ConversationStatus::OPEN_MANUAL], true)
            && $this->effectiveHandlingMode() === ConversationHandlingMode::MANUAL
            && $this->deleted_at === null;
    }

    public function visitorDisplayName(): string
    {
        $prechat = $this->relationLoaded('visitorSession')
            ? ($this->visitorSession?->prechat_data ?? [])
            : [];

        foreach (['name', 'full_name', 'fullName'] as $key) {
            if (is_string($prechat[$key] ?? null) && trim($prechat[$key]) !== '') {
                return Str::limit(Str::squish($prechat[$key]), 100, '');
            }
        }

        $reference = preg_replace('/[^a-z0-9]/i', '', (string) $this->visitor_identifier);
        $suffix = Str::upper(Str::substr($reference ?: $this->uuid, -4));

        return __('Visitor :reference', ['reference' => $suffix]);
    }

    public function visitorInitials(): string
    {
        $parts = Str::of($this->visitorDisplayName())->squish()->explode(' ')->filter();
        $initials = $parts->take(2)->map(fn (string $part): string => Str::upper(Str::substr($part, 0, 1)))->implode('');

        return $initials !== '' ? $initials : 'V';
    }

    public function retrievalRuns(): HasMany
    {
        return $this->hasMany(RetrievalRun::class);
    }
}
