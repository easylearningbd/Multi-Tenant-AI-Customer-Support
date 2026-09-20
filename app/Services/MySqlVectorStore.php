<?php

namespace App\Services;

use App\Contracts\VectorStoreInterface;
use App\DTOs\VectorMatch;
use App\DTOs\VectorSearchQuery;
use App\Enums\KnowledgeSourceStatus;
use App\Models\KnowledgeChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

final class MySqlVectorStore implements VectorStoreInterface
{
    public function __construct(private readonly CosineSimilarity $similarity) {}

    public function upsertChunkEmbedding(array $record): void
    {
        $this->upsertMany([$record]);
    }

    public function upsertMany(array $records): void
    {
        foreach ($records as &$record) {
            $vector = $this->similarity->validatedVector($record['embedding']);
            $record['embedding'] = json_encode($vector, JSON_THROW_ON_ERROR);
            $record['embedding_norm'] = $this->similarity->norm($vector);
        }
        unset($record);

        if ($records !== []) {
            KnowledgeChunk::query()->upsert($records, ['knowledge_source_id', 'generation_uuid', 'chunk_index'], [
                'uuid', 'content', 'token_count', 'content_checksum', 'embedding', 'embedding_model',
                'embedding_dimensions', 'embedding_norm', 'is_active', 'embedded_at', 'updated_at',
            ]);
        }
    }

    public function search(VectorSearchQuery $query): array
    {
        $startedAt = hrtime(true);
        if ($query->topK < 1 || $query->topK > 100) {
            throw new InvalidArgumentException('Top K must be between 1 and 100.');
        }

        $needle = $this->similarity->validatedVector($query->embedding);
        $matches = [];
        $scanned = 0;
        KnowledgeChunk::query()
            ->select(['id', 'uuid', 'knowledge_source_id', 'content', 'embedding', 'embedding_model', 'embedding_dimensions', 'embedding_norm'])
            ->with('source:id,uuid,name')
            ->forTenantBot($query->tenantId, $query->botId)
            ->where('is_active', true)
            ->where('embedding_model', $query->embeddingModel)
            ->where('embedding_dimensions', count($needle))
            ->when($query->sourceIds !== [], fn ($builder) => $builder->whereIn('knowledge_source_id', $query->sourceIds))
            ->whereHas('source', fn ($builder) => $builder
                ->where('user_id', $query->tenantId)
                ->where('bot_id', $query->botId)
                ->where('status', KnowledgeSourceStatus::TRAINED))
            ->lazyById((int) config('neuraldesk.knowledge.vector_scan_batch', 250))
            ->each(function (KnowledgeChunk $chunk) use (&$matches, &$scanned, $needle, $query): void {
                $scanned++;
                try {
                    $score = $this->similarity->score($needle, $chunk->embedding ?? [], $chunk->embedding_norm);
                } catch (Throwable) {
                    return;
                }

                if (($query->minimumScore !== null && $score < $query->minimumScore) || ! $chunk->source) {
                    return;
                }

                $matches[] = new VectorMatch(
                    $chunk->uuid,
                    $chunk->source->uuid,
                    $chunk->source->name,
                    $chunk->content,
                    $score,
                    $chunk->id,
                    $chunk->knowledge_source_id,
                );
                usort($matches, fn (VectorMatch $left, VectorMatch $right): int => $right->score <=> $left->score);
                if (count($matches) > $query->topK) {
                    array_pop($matches);
                }
            });

        Log::info('MySQL vector search completed.', [
            'tenant_id' => $query->tenantId,
            'bot_id' => $query->botId,
            'candidates_scanned' => $scanned,
            'matches_returned' => count($matches),
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
        ]);

        return $matches;
    }

    public function deleteBySource(int $tenantId, int $botId, int $sourceId): void
    {
        KnowledgeChunk::query()->forTenantBot($tenantId, $botId)->where('knowledge_source_id', $sourceId)->delete();
    }

    public function deleteGeneration(int $tenantId, int $botId, int $sourceId, string $generationUuid): void
    {
        KnowledgeChunk::query()->forTenantBot($tenantId, $botId)->where('knowledge_source_id', $sourceId)->where('generation_uuid', $generationUuid)->delete();
    }

    public function deleteByBot(int $tenantId, int $botId): void
    {
        KnowledgeChunk::query()->forTenantBot($tenantId, $botId)->delete();
    }

    public function deleteByTenant(int $tenantId): void
    {
        KnowledgeChunk::query()->where('user_id', $tenantId)->delete();
    }

    public function healthCheck(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
