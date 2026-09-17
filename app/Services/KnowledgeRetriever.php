<?php

namespace App\Services;

use App\Contracts\EmbeddingProviderInterface;
use App\Contracts\KnowledgeRetrieverInterface;
use App\Contracts\VectorStoreInterface;
use App\DTOs\RagRetrievalResult;
use App\DTOs\VectorSearchQuery;
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

    public function retrieve(Bot $bot, Conversation $conversation, ConversationMessage $message): RagRetrievalResult
    {
        abort_unless($conversation->user_id === $bot->user_id && $conversation->bot_id === $bot->id, 404);
        abort_unless($message->user_id === $bot->user_id && $message->bot_id === $bot->id && $message->conversation_id === $conversation->id, 404);

        $started = hrtime(true);
        $batch = $this->embeddings->embedMany([(string) $message->body]);
        $setting = $bot->setting()->firstOrFail();
        $topK = max(1, min(20, (int) config('neuraldesk.rag.retrieval_top_k', 5)));
        $minimum = (float) $setting->kb_confidence;
        $matches = $this->vectors->search(new VectorSearchQuery(
            $bot->user_id,
            $bot->id,
            $batch->vectors[0],
            $batch->model,
            $topK,
            $minimum,
        ));
        $duration = max(0, (int) round((hrtime(true) - $started) / 1_000_000));

        $run = new RetrievalRun;
        $run->uuid = (string) Str::uuid();
        $run->user_id = $bot->user_id;
        $run->bot_id = $bot->id;
        $run->conversation_id = $conversation->id;
        $run->conversation_message_id = $message->id;
        $run->fill([
            'query_checksum' => hash('sha256', (string) $message->body),
            'embedding_model' => $batch->model,
            'embedding_dimensions' => $batch->dimensions,
            'top_k' => $topK,
            'minimum_score' => $minimum,
            'selected_chunk_uuids' => array_map(fn ($match): string => $match->chunkUuid, $matches),
            'scores' => array_map(fn ($match): float => round($match->score, 7), $matches),
            'duration_ms' => $duration,
        ]);
        $run->save();

        return new RagRetrievalResult($matches, $batch->model, $batch->dimensions, $minimum, $duration);
    }
}
