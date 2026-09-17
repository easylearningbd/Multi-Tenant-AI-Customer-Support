<?php

namespace App\Models;

use App\Enums\MessageActor;
use App\Enums\MessageStatus;
use Database\Factories\ConversationMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ConversationMessage extends Model
{
    /** @use HasFactory<ConversationMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'reply_to_message_id', 'actor_type', 'status', 'idempotency_key', 'body',
        'model', 'provider_request_id', 'input_tokens', 'output_tokens',
        'estimated_cost_minor', 'cost_currency', 'latency_ms', 'finish_reason', 'confidence', 'error_code',
    ];

    protected $hidden = ['provider_request_id', 'error_code'];

    protected function casts(): array
    {
        return [
            'actor_type' => MessageActor::class,
            'status' => MessageStatus::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_minor' => 'integer',
            'latency_ms' => 'integer',
            'confidence' => 'decimal:7',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeForTenantBot(Builder $query, int $tenantId, int $botId): Builder
    {
        return $query->where('user_id', $tenantId)->where('bot_id', $botId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function citations(): HasMany
    {
        return $this->hasMany(MessageCitation::class);
    }
}
