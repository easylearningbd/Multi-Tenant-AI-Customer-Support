<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ConversationAttachment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'disk', 'path', 'original_name', 'mime_type', 'size',
    ];

    protected $hidden = ['disk', 'path'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeForTenantConversation(Builder $query, int $tenantId, int $botId, int $conversationId): Builder
    {
        return $query->where('user_id', $tenantId)
            ->where('bot_id', $botId)
            ->where('conversation_id', $conversationId);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }
}
