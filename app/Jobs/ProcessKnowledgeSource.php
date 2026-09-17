<?php

namespace App\Jobs;

use App\Contracts\EmbeddingProviderInterface;
use App\Contracts\VectorStoreInterface;
use App\Enums\KnowledgeSourceStatus;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Services\SourceTextExtractor;
use App\Services\TextChunker;
use App\Services\TextNormalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ProcessKnowledgeSource implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public int $uniqueFor = 900;

    /** @var list<int> */
    public array $backoff = [10, 30, 90];

    public function __construct(
        public readonly int $sourceId,
        public readonly int $tenantId,
        public readonly int $botId,
        public readonly string $processingToken,
    ) {
        $this->onQueue((string) config('neuraldesk.queues.knowledge_ingestion'));
    }

    public function uniqueId(): string
    {
        return $this->tenantId.':'.$this->botId.':'.$this->sourceId.':'.$this->processingToken;
    }

    public function handle(
        SourceTextExtractor $extractor,
        TextNormalizer $normalizer,
        TextChunker $chunker,
        EmbeddingProviderInterface $embeddings,
        VectorStoreInterface $vectors,
    ): void {
        $source = $this->source();
        if (! $source || $source->processing_token !== $this->processingToken) {
            return;
        }

        $generation = $this->processingToken;
        $source->forceFill(['status' => KnowledgeSourceStatus::EXTRACTING, 'failure_message' => null])->save();
        $text = $normalizer->normalize($extractor->extract($source));
        $source->forceFill([
            'status' => KnowledgeSourceStatus::CHUNKING,
            'extracted_text' => $text,
            'content_checksum' => hash('sha256', $text),
        ])->save();
        $chunks = $chunker->chunk($text);
        if ($chunks === []) {
            throw new \RuntimeException('The source did not produce any usable chunks.');
        }

        $source->forceFill(['status' => KnowledgeSourceStatus::EMBEDDING])->save();
        $vectors->deleteGeneration($this->tenantId, $this->botId, $source->id, $generation);
        $batchSize = max(1, min(128, (int) config('neuraldesk.knowledge.embedding_batch_size', 32)));
        foreach (array_chunk($chunks, $batchSize) as $batch) {
            $result = $embeddings->embedMany(array_column($batch, 'content'));
            if (count($result->vectors) !== count($batch)) {
                throw new \RuntimeException('The embedding provider returned an incomplete batch.');
            }
            $now = now('UTC');
            $records = [];
            foreach ($batch as $offset => $chunk) {
                $records[] = [
                    'uuid' => (string) Str::uuid(),
                    'user_id' => $this->tenantId,
                    'bot_id' => $this->botId,
                    'knowledge_source_id' => $source->id,
                    'generation_uuid' => $generation,
                    'chunk_index' => $chunk['index'],
                    'content' => $chunk['content'],
                    'token_count' => $chunk['token_count'],
                    'content_checksum' => $chunk['checksum'],
                    'embedding' => $result->vectors[$offset],
                    'embedding_model' => $result->model,
                    'embedding_dimensions' => $result->dimensions,
                    'is_active' => false,
                    'embedded_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            $vectors->upsertMany($records);
        }

        $expected = count($chunks);
        $stored = KnowledgeChunk::query()->forTenantBot($this->tenantId, $this->botId)
            ->where('knowledge_source_id', $source->id)->where('generation_uuid', $generation)
            ->whereNotNull('embedding')->count();
        if ($stored !== $expected) {
            throw new \RuntimeException('The complete replacement index could not be verified.');
        }

        DB::transaction(function () use ($source, $generation, $expected): void {
            $locked = KnowledgeSource::query()->whereKey($source->id)->where('user_id', $this->tenantId)
                ->where('bot_id', $this->botId)->where('processing_token', $this->processingToken)->lockForUpdate()->first();
            if (! $locked) {
                return;
            }
            KnowledgeChunk::query()->forTenantBot($this->tenantId, $this->botId)
                ->where('knowledge_source_id', $locked->id)->where('generation_uuid', '!=', $generation)->delete();
            KnowledgeChunk::query()->forTenantBot($this->tenantId, $this->botId)
                ->where('knowledge_source_id', $locked->id)->where('generation_uuid', $generation)->update(['is_active' => true]);
            $locked->forceFill([
                'status' => KnowledgeSourceStatus::TRAINED,
                'failure_message' => null,
                'chunk_count' => $expected,
                'current_generation_uuid' => $generation,
                'processing_token' => null,
                'last_trained_at' => now('UTC'),
            ])->save();
        }, 3);
    }

    public function failed(Throwable $exception): void
    {
        $source = $this->source();
        if (! $source || $source->processing_token !== $this->processingToken) {
            return;
        }
        KnowledgeChunk::query()->forTenantBot($this->tenantId, $this->botId)
            ->where('knowledge_source_id', $this->sourceId)->where('generation_uuid', $this->processingToken)->delete();
        $source->forceFill([
            'status' => KnowledgeSourceStatus::FAILED,
            'failure_message' => $this->safeMessage($exception),
            'processing_token' => null,
        ])->save();
    }

    private function source(): ?KnowledgeSource
    {
        return KnowledgeSource::query()->whereKey($this->sourceId)->where('user_id', $this->tenantId)
            ->where('bot_id', $this->botId)->first();
    }

    private function safeMessage(Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());

        return match (true) {
            str_contains($message, 'configuration') => 'AI embedding is not configured. Please contact the platform administrator.',
            str_contains($message, 'readable'), str_contains($message, 'empty') => 'No readable knowledge content was found.',
            str_contains($message, 'website'), str_contains($message, 'url') => 'The website could not be retrieved safely.',
            default => 'Training failed safely. Retry the source or contact support if the issue continues.',
        };
    }
}
