<?php

use App\Contracts\EmbeddingProviderInterface;
use App\DTOs\EmbeddingBatch;
use App\DTOs\VectorSearchQuery;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\KnowledgeSourceType;
use App\Jobs\ProcessKnowledgeSource;
use App\Models\Bot;
use App\Models\KnowledgeChunk;
use App\Models\User;
use App\Services\MySqlVectorStore;
use App\Services\SourceTextExtractor;
use App\Services\TextChunker;
use App\Services\TextNormalizer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function knowledgeTestPdf(string $text): string
{
    $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    $stream = "BT /F1 12 Tf 72 720 Td ({$escaped}) Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $number = $index + 1;
        $pdf .= "{$number} 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach (array_slice($offsets, 1) as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }

    return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
}

function knowledgeTestDocx(string $text): string
{
    $path = tempnam(sys_get_temp_dir(), 'neuraldesk-test-docx-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.$escaped.'</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();
    $contents = file_get_contents($path);
    unlink($path);

    return is_string($contents) ? $contents : '';
}

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

test('completed training makes text txt markdown pdf and docx sources immediately searchable', function () {
    Storage::fake('knowledge');
    config()->set('neuraldesk.storage.knowledge_disk', 'knowledge');
    $owner = User::factory()->subscriber()->create();
    $bot = Bot::factory()->for($owner)->create();
    $formats = [
        ['type' => KnowledgeSourceType::TEXT, 'content' => 'Direct text includes the Aurora support policy.', 'extension' => null],
        ['type' => KnowledgeSourceType::TXT, 'content' => 'TXT includes the Beacon support policy.', 'extension' => 'txt'],
        ['type' => KnowledgeSourceType::MARKDOWN, 'content' => "# Guide\n\nMarkdown includes the Comet support policy.", 'extension' => 'md'],
        ['type' => KnowledgeSourceType::PDF, 'content' => knowledgeTestPdf('PDF includes the Delta support policy.'), 'extension' => 'pdf'],
        ['type' => KnowledgeSourceType::DOCX, 'content' => knowledgeTestDocx('DOCX includes the Ember support policy.'), 'extension' => 'docx'],
    ];

    foreach ($formats as $index => $format) {
        $token = (string) Str::uuid();
        $attributes = [
            'type' => $format['type'],
            'status' => KnowledgeSourceStatus::QUEUED,
            'processing_token' => $token,
            'raw_text' => $format['extension'] === null ? $format['content'] : null,
        ];
        if ($format['extension'] !== null) {
            $path = "user-{$owner->id}/bot-{$bot->id}/source-{$index}.{$format['extension']}";
            Storage::disk('knowledge')->put($path, $format['content']);
            $attributes['file_path'] = $path;
            $attributes['original_filename'] = "source-{$index}.{$format['extension']}";
        }
        $source = knowledgeSource($owner, $bot, $attributes);

        runKnowledgeJob(new ProcessKnowledgeSource($source->id, $owner->id, $bot->id, $token), fakeEmbeddingProvider());

        $source->refresh();
        $chunk = $source->chunks()->where('is_active', true)->firstOrFail();
        $matches = app(MySqlVectorStore::class)->search(new VectorSearchQuery(
            $owner->id,
            $bot->id,
            $chunk->embedding,
            $chunk->embedding_model,
            5,
            0.99,
            [$source->id],
        ));

        expect($source->status)->toBe(KnowledgeSourceStatus::TRAINED)
            ->and($source->chunk_count)->toBeGreaterThan(0)
            ->and($matches)->not->toBeEmpty()
            ->and($matches[0]->sourceId)->toBe($source->id);
    }
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
