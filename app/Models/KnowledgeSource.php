<?php

namespace App\Models;

use App\Enums\KnowledgeSourceStatus;
use App\Enums\KnowledgeSourceType;
use Database\Factories\KnowledgeSourceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class KnowledgeSource extends Model
{
    /** @use HasFactory<KnowledgeSourceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'type', 'name', 'original_filename', 'file_path', 'file_size_bytes',
        'source_url', 'sitemap_url', 'page_limit', 'raw_text', 'extracted_text', 'status',
        'failure_message', 'chunk_count', 'content_checksum',
        'current_generation_uuid', 'processing_token', 'last_trained_at',
    ];

    protected $hidden = ['raw_text', 'extracted_text', 'processing_token'];

    protected function casts(): array
    {
        return [
            'type' => KnowledgeSourceType::class,
            'status' => KnowledgeSourceStatus::class,
            'file_size_bytes' => 'integer',
            'chunk_count' => 'integer',
            'page_limit' => 'integer',
            'last_trained_at' => 'immutable_datetime',
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
        return $query->where('bot_id', $bot->id)->where('user_id', $bot->user_id);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class)->orderBy('chunk_index');
    }

    public function isProcessing(): bool
    {
        return $this->status->isProcessing();
    }
}
