<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class MessageCitation extends Model
{
    protected $fillable = [
        'source_uuid', 'chunk_uuid', 'source_name', 'rank', 'similarity_score',
    ];

    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'similarity_score' => 'decimal:7',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(ConversationMessage::class, 'conversation_message_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    public function chunk(): BelongsTo
    {
        return $this->belongsTo(KnowledgeChunk::class, 'knowledge_chunk_id');
    }
}
