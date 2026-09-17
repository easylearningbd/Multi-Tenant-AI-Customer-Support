<?php

use App\Contracts\EmbeddingProviderInterface;
use App\DTOs\EmbeddingBatch;
use App\DTOs\VectorSearchQuery;
use App\Enums\KnowledgeSourceStatus;
use App\Jobs\ProcessKnowledgeSource;
use App\Models\Bot;
use App\Models\KnowledgeChunk;
use App\Models\User;
use App\Services\MySqlVectorStore;
use App\Services\SourceTextExtractor;
use App\Services\TextChunker;
use App\Services\TextNormalizer;
use Illuminate\Support\Str;

function fakeEmbeddingProvider(?Throwable $failure = null, int $dimensions = 3): EmbeddingProviderInterface
{
    return new class($failure, $dimensions) implements EmbeddingProviderInterface
    {
        public function __construct(private readonly ?Throwable $failure, private readonly int $dimensions) {}

        public function embedMany(array $inputs): EmbeddingBatch
        {
            if ($this->failure) {
                throw $this->failure;
            }
            $vectors = array_map(fn (string $input): array => array_pad([1.0, (float) (strlen($input) % 7 + 1)], $this->dimensions, 0.5), $inputs);

            return new EmbeddingBatch($vectors, 'test-embedding-model', $this->dimensions);
        }
    };
}

function runKnowledgeJob(ProcessKnowledgeSource $job, EmbeddingProviderInterface $provider): void
{
    $job->handle(
        app(SourceTextExtractor::class),
        app(TextNormalizer::class),
        app(TextChunker::class),
        $provider,
        app(MySqlVectorStore::class),
    );
}

test('job is idempotent and retry does not duplicate chunks', function () {
    $owner = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($owner)->create();
    $token = (string) Str::uuid();
    $source = knowledgeSource($owner, $bot, ['raw_text' => 'One factual paragraph. Another reliable paragraph.', 'processing_token' => $token]);
    $job = new ProcessKnowledgeSource($source->id, $owner->id, $bot->id, $token);

    runKnowledgeJob($job, fakeEmbeddingProvider());
    $count = $source->chunks()->count();
    runKnowledgeJob($job, fakeEmbeddingProvider());

    expect($source->fresh()->status)->toBe(KnowledgeSourceStatus::TRAINED)
        ->and($source->chunks()->count())->toBe($count)
        ->and($count)->toBeGreaterThan(0);
});

test('empty extracted content fails safely without becoming trained', function () {
    $owner = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($owner)->create();
    $token = (string) Str::uuid();
    $source = knowledgeSource($owner, $bot, ['raw_text' => " \n\0 ", 'processing_token' => $token]);
    $job = new ProcessKnowledgeSource($source->id, $owner->id, $bot->id, $token);

    try {
        runKnowledgeJob($job, fakeEmbeddingProvider());
    } catch (Throwable $exception) {
        $job->failed($exception);
    }

    expect($source->fresh()->status)->toBe(KnowledgeSourceStatus::FAILED)
        ->and($source->fresh()->failure_message)->not->toBeEmpty()
        ->and($source->chunks()->count())->toBe(0);
});

test('failed embedding request keeps prior trained generation active', function () {
    $owner = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($owner)->create();
    $oldGeneration = (string) Str::uuid();
    $token = (string) Str::uuid();
    $source = knowledgeSource($owner, $bot, [
        'raw_text' => 'Replacement facts.', 'processing_token' => $token,
        'current_generation_uuid' => $oldGeneration, 'status' => KnowledgeSourceStatus::QUEUED,
    ]);
    $old = KnowledgeChunk::factory()->create([
        'knowledge_source_id' => $source->id, 'user_id' => $owner->id, 'bot_id' => $bot->id,
        'generation_uuid' => $oldGeneration, 'content' => 'Prior trained facts.', 'is_active' => true,
    ]);
    $job = new ProcessKnowledgeSource($source->id, $owner->id, $bot->id, $token);

    try {
        runKnowledgeJob($job, fakeEmbeddingProvider(new RuntimeException('provider unavailable')));
    } catch (Throwable $exception) {
        $job->failed($exception);
    }

    expect($source->fresh()->status)->toBe(KnowledgeSourceStatus::FAILED);
    $this->assertDatabaseHas('knowledge_chunks', ['id' => $old->id, 'is_active' => true]);
});

test('successful retraining atomically replaces stale chunks', function () {
    $owner = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($owner)->create();
    $token = (string) Str::uuid();
    $source = knowledgeSource($owner, $bot, ['raw_text' => 'New current knowledge.', 'processing_token' => $token]);
    $stale = KnowledgeChunk::factory()->create([
        'knowledge_source_id' => $source->id, 'user_id' => $owner->id, 'bot_id' => $bot->id,
        'generation_uuid' => (string) Str::uuid(), 'content' => 'Stale knowledge', 'is_active' => true,
    ]);

    runKnowledgeJob(new ProcessKnowledgeSource($source->id, $owner->id, $bot->id, $token), fakeEmbeddingProvider());

    $this->assertDatabaseMissing('knowledge_chunks', ['id' => $stale->id]);
    expect($source->fresh()->chunks()->where('is_active', true)->count())->toBe($source->fresh()->chunk_count)
        ->and($source->fresh()->chunks()->where('content', 'New current knowledge.')->exists())->toBeTrue();
});

test('mysql similarity is ordered thresholded and isolated by tenant and bot', function () {
    $owner = User::factory()->subscriber()->create();
    $other = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($owner)->create();
    $otherBot = Bot::factory()->for($owner)->create();
    $foreignBot = Bot::factory()->for($other)->create();
    $source = knowledgeSource($owner, $bot, ['status' => KnowledgeSourceStatus::TRAINED]);
    $otherBotSource = knowledgeSource($owner, $otherBot, ['status' => KnowledgeSourceStatus::TRAINED]);
    $foreignSource = knowledgeSource($other, $foreignBot, ['status' => KnowledgeSourceStatus::TRAINED]);
    foreach ([
        [$source, $bot, $owner, [1.0, 0.0, 0.0], 'strong'],
        [$source, $bot, $owner, [0.8, 0.2, 0.0], 'second'],
        [$source, $bot, $owner, [0.0, 1.0, 0.0], 'weak'],
        [$otherBotSource, $otherBot, $owner, [1.0, 0.0, 0.0], 'other bot'],
        [$foreignSource, $foreignBot, $other, [1.0, 0.0, 0.0], 'other tenant'],
    ] as [$forSource, $forBot, $forUser, $vector, $content]) {
        KnowledgeChunk::factory()->create([
            'knowledge_source_id' => $forSource->id, 'user_id' => $forUser->id, 'bot_id' => $forBot->id,
            'embedding' => $vector, 'embedding_norm' => sqrt(array_sum(array_map(fn ($v) => $v * $v, $vector))), 'content' => $content,
        ]);
    }

    $matches = app(MySqlVectorStore::class)->search(new VectorSearchQuery($owner->id, $bot->id, [1.0, 0.0, 0.0], 'test-embedding-model', 10, .5));

    expect(array_column($matches, 'content'))->toBe(['strong', 'second'])
        ->and($matches[0]->score)->toBeGreaterThanOrEqual($matches[1]->score);
});

test('malformed stored embeddings are skipped safely', function () {
    $owner = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($owner)->create();
    $source = knowledgeSource($owner, $bot, ['status' => KnowledgeSourceStatus::TRAINED]);
    $chunk = KnowledgeChunk::factory()->make(['knowledge_source_id' => $source->id, 'user_id' => $owner->id, 'bot_id' => $bot->id]);
    $attributes = $chunk->getAttributes();
    $attributes['embedding'] = json_encode(['not-a-number']);
    $attributes['created_at'] = $attributes['updated_at'] = now();
    KnowledgeChunk::query()->insert($attributes);

    $matches = app(MySqlVectorStore::class)->search(new VectorSearchQuery($owner->id, $bot->id, [1.0, 0.0, 0.0], 'test-embedding-model', 5, 0));
    expect($matches)->toBe([]);
});

test('job payload contains identifiers and no source content', function () {
    $job = new ProcessKnowledgeSource(10, 20, 30, 'token');
    $serialized = serialize($job);

    expect($serialized)->not->toContain('document content')->not->toContain('OPENAI_API_KEY');
});
