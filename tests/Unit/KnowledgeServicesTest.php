<?php

use App\Exceptions\EmbeddingProviderException;
use App\Exceptions\UnsafeUrlException;
use App\Services\CosineSimilarity;
use App\Services\OpenAIEmbeddingService;
use App\Services\TextChunker;
use App\Services\UrlSafetyService;
use App\Services\WebsiteCrawler;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

test('cosine service validates dimensions finite values and zero vectors', function () {
    $service = new CosineSimilarity;
    expect($service->score([1.0, 0.0], [1.0, 0.0]))->toBe(1.0);
    expect(fn () => $service->score([1.0], [1.0, 2.0]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $service->score([0.0], [1.0]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $service->validatedVector([INF]))->toThrow(InvalidArgumentException::class);
});

test('text chunking is deterministic ordered and non empty', function () {
    config()->set('neuraldesk.knowledge.chunk_target_characters', 30);
    config()->set('neuraldesk.knowledge.chunk_overlap_characters', 5);
    $service = new TextChunker;
    $first = $service->chunk('First sentence. Second sentence. Third sentence.');
    $second = $service->chunk('First sentence. Second sentence. Third sentence.');

    expect($first)->toBe($second)->and(array_column($first, 'index'))->toBe(range(0, count($first) - 1));
});

test('openai embedding response dimensions are validated', function () {
    config()->set('neuraldesk.ai.openai.api_key', 'test-key');
    config()->set('neuraldesk.ai.openai.embedding_model', 'text-embedding-test');
    config()->set('neuraldesk.ai.openai.embedding_dimensions', 3);
    Http::fake(['*/embeddings' => Http::response(['data' => [['index' => 0, 'embedding' => [1.0, 2.0]]]], 200)]);

    expect(fn () => (new OpenAIEmbeddingService)->embedMany(['test']))->toThrow(EmbeddingProviderException::class);
});

test('openai embedding requests support an application scoped ca bundle', function () {
    config()->set('neuraldesk.ai.openai.api_key', 'test-key');
    config()->set('neuraldesk.ai.openai.embedding_model', 'text-embedding-test');
    config()->set('neuraldesk.ai.openai.embedding_dimensions', 3);
    config()->set('neuraldesk.ai.openai.ca_bundle', __FILE__);
    Http::fake(['*/embeddings' => Http::response([
        'data' => [['index' => 0, 'embedding' => [1.0, 2.0, 3.0]]],
    ], 200)]);

    $result = (new OpenAIEmbeddingService)->embedMany(['test']);

    expect($result->dimensions)->toBe(3)
        ->and($result->model)->toBe('text-embedding-test');
    Http::assertSentCount(1);
});

test('redirects to private addresses are rejected', function () {
    $safety = new class extends UrlSafetyService
    {
        protected function resolveAddresses(string $host): array
        {
            return $host === 'example.test' ? ['93.184.216.34'] : parent::resolveAddresses($host);
        }
    };
    Http::fake(['http://example.test/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/private'])]);

    expect(fn () => (new WebsiteCrawler($safety))->crawl('http://example.test/page', false, 1))->toThrow(UnsafeUrlException::class);
});
