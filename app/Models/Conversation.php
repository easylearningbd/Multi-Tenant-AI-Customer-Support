<?php

namespace App\Models;

use App\Enums\ConversationStatus;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected $fillable = [
        'status', 'channel', 'visitor_identifier', 'subject', 'started_at',
        'last_message_at', 'handoff_requested_at', 'ai_resolved_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'started_at' => 'immutable_datetime',
            'last_message_at' => 'immutable_datetime',
            'handoff_requested_at' => 'immutable_datetime',
            'ai_resolved_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
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

    public function retrievalRuns(): HasMany
    {
        return $this->hasMany(RetrievalRun::class);
    }
}
