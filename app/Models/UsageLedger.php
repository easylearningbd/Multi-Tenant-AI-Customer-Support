<?php

namespace App\Models;

use App\Enums\UsageLedgerStatus;
use App\Enums\UsageType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class UsageLedger extends Model
{
    protected $fillable = [
        'uuid', 'event_key', 'type', 'status', 'quantity', 'model',
        'input_tokens', 'output_tokens', 'period_starts_at', 'period_ends_at',
        'estimated_cost_minor', 'cost_currency', 'committed_at', 'released_at',
    ];

    protected $hidden = ['event_key'];

    protected function casts(): array
    {
        return [
            'type' => UsageType::class,
            'status' => UsageLedgerStatus::class,
            'quantity' => 'integer',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'estimated_cost_minor' => 'integer',
            'period_starts_at' => 'immutable_datetime',
            'period_ends_at' => 'immutable_datetime',
            'committed_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    public function scopeForPeriod(Builder $query, int $tenantId, UsageType $type, $start, $end): Builder
    {
        return $query->where('user_id', $tenantId)->where('type', $type)
            ->where('period_starts_at', $start)->where('period_ends_at', $end);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'source_message_id');
    }
}
