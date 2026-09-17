<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class RetrievalRun extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid', 'query_checksum', 'embedding_model', 'embedding_dimensions',
        'top_k', 'minimum_score', 'selected_chunk_uuids', 'scores', 'duration_ms',
    ];

    protected $hidden = ['query_checksum', 'scores'];

    protected function casts(): array
    {
        return [
            'embedding_dimensions' => 'integer',
            'top_k' => 'integer',
            'minimum_score' => 'decimal:7',
            'selected_chunk_uuids' => 'array',
            'scores' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }
}
