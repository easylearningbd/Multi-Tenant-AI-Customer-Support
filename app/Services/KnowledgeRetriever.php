<?php

namespace App\Services;

use App\Contracts\EmbeddingProviderInterface;
use App\Contracts\KnowledgeRetrieverInterface;
use App\Contracts\VectorStoreInterface;
use App\DTOs\RagRetrievalResult;
use App\DTOs\VectorSearchQuery;
use App\Exceptions\EmbeddingProviderException;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\RetrievalRun;
use Illuminate\Support\Str;

final class KnowledgeRetriever implements KnowledgeRetrieverInterface
{
    public function __construct(
        private readonly EmbeddingProviderInterface $embeddings,
        private readonly VectorStoreInterface $vectors,
    ) {}

    public function retrieve(
        Bot $bot,
        Conversation $conversation,
        ConversationMessage $message,
        string $query,
    ): RagRetrievalResult {
        abort_unless($conversation->user_id === $bot->user_id && $conversation->bot_id === $bot->id, 404);
        abort_unless($message->user_id === $bot->user_id && $message->bot_id === $bot->id && $message->conversation_id === $conversation->id, 404);

        $started = hrtime(true);
        $query = trim($query);
        $batch = $this->embeddings->embedMany([$query]);
        $configuredModel = trim((string) config('neuraldesk.ai.openai.embedding_model'));
        if ($configuredModel !== '' && $batch->model !== $configuredModel) {
            throw new EmbeddingProviderException('The query embedding model does not match the configured knowledge embedding model.');
        }
        if (! isset($batch->vectors[0]) || count($batch->vectors[0]) !== $batch->dimensions) {
            throw new EmbeddingProviderException('The query embedding dimensions are invalid.');
        }

        $topK = 5;
        $matches = $this->vectors->search(new VectorSearchQuery(
            $bot->user_id,
            $bot->id,
            $batch->vectors[0],
            $batch->model,
            $topK,
            null,
        ));
        $duration = max(0, (int) round((hrtime(true) - $started) / 1_000_000));

        $run = new RetrievalRun;
        $run->uuid = (string) Str::uuid();
        $run->user_id = $bot->user_id;
        $run->bot_id = $bot->id;
        $run->conversation_id = $conversation->id;
        $run->conversation_message_id = $message->id;
        $run->fill([
            'query_checksum' => hash('sha256', $query),
            'embedding_model' => $batch->model,
            'embedding_dimensions' => $batch->dimensions,
            'top_k' => $topK,
            // The column predates score-free retrieval. -1 is the inclusive cosine floor.
            'minimum_score' => -1,
            'selected_chunk_uuids' => array_map(fn ($match): string => $match->chunkUuid, $matches),
            'scores' => array_map(fn ($match): float => round($match->score, 7), $matches),
            'duration_ms' => $duration,
        ]);
        $run->save();

        return new RagRetrievalResult($matches, $batch->model, $batch->dimensions, null, $duration);
    }
}
