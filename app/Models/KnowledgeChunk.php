<?php

namespace App\Models;

use Database\Factories\KnowledgeChunkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KnowledgeChunk extends Model
{
    /** @use HasFactory<KnowledgeChunkFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid', 'generation_uuid', 'chunk_index', 'content', 'token_count',
        'content_checksum', 'embedding', 'embedding_model', 'embedding_dimensions',
        'embedding_norm', 'is_active', 'embedded_at',
    ];

    protected $hidden = ['embedding'];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'chunk_index' => 'integer',
            'token_count' => 'integer',
            'embedding_dimensions' => 'integer',
            'embedding_norm' => 'float',
            'is_active' => 'boolean',
            'embedded_at' => 'immutable_datetime',
        ];
    }

    public function scopeForTenantBot(Builder $query, int $tenantId, int $botId): Builder
    {
        return $query->where('user_id', $tenantId)->where('bot_id', $botId);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
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
