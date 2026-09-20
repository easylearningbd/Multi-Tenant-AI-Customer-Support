<?php

namespace App\Services;

use App\Contracts\EmbeddingProviderInterface;
use App\DTOs\EmbeddingBatch;
use App\Exceptions\EmbeddingProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class OpenAIEmbeddingService implements EmbeddingProviderInterface
{
    public function embedMany(array $inputs): EmbeddingBatch
    {
        $key = (string) config('neuraldesk.ai.openai.api_key');
        $model = (string) config('neuraldesk.ai.openai.embedding_model');
        $expectedDimensions = config('neuraldesk.ai.openai.embedding_dimensions');
        if ($key === '' || $model === '') {
            throw new EmbeddingProviderException('OpenAI embedding configuration is incomplete.');
        }
        if ($inputs === []) {
            throw new EmbeddingProviderException('At least one embedding input is required.');
        }

        $payload = ['model' => $model, 'input' => array_values($inputs), 'encoding_format' => 'float'];
        if (is_int($expectedDimensions) && $expectedDimensions > 0) {
            $payload['dimensions'] = $expectedDimensions;
        }

        $attempts = max(1, (int) config('neuraldesk.ai.openai.max_retries', 3));
        $last = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $request = Http::baseUrl(rtrim((string) config('neuraldesk.ai.openai.base_url'), '/'))
                    ->withToken($key)
                    ->acceptJson()
                    ->connectTimeout((int) config('neuraldesk.ai.openai.connect_timeout', 10))
                    ->timeout((int) config('neuraldesk.ai.openai.request_timeout', 60));
                $caBundle = trim((string) config('neuraldesk.ai.openai.ca_bundle'));
                if ($caBundle !== '') {
                    $request->withOptions(['verify' => $caBundle]);
                }
                $response = $request->post('/embeddings', $payload);
                if ($response->successful()) {
                    $data = collect($response->json('data', []))->sortBy('index')->values();
                    $vectors = $data->pluck('embedding')->all();
                    if (count($vectors) !== count($inputs)) {
                        throw new EmbeddingProviderException('The embedding provider returned an incomplete response.');
                    }
                    $dimensions = count($vectors[0] ?? []);
                    if ($dimensions < 1 || ($expectedDimensions && $dimensions !== (int) $expectedDimensions)) {
                        throw new EmbeddingProviderException('The embedding provider returned unexpected dimensions.');
                    }
                    foreach ($vectors as $vector) {
                        if (! is_array($vector) || count($vector) !== $dimensions) {
                            throw new EmbeddingProviderException('The embedding provider returned inconsistent dimensions.');
                        }
                    }

                    return new EmbeddingBatch($vectors, $model, $dimensions);
                }
                if (! in_array($response->status(), [408, 409, 429, 500, 502, 503, 504], true)) {
                    throw new EmbeddingProviderException('The embedding provider rejected the request.');
                }
                $last = new EmbeddingProviderException('The embedding provider is temporarily unavailable.');
            } catch (ConnectionException $exception) {
                $last = $exception;
            } catch (EmbeddingProviderException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $last = $exception;
            }
            if ($attempt < $attempts) {
                usleep(100000 * (2 ** ($attempt - 1)));
            }
        }

        throw new EmbeddingProviderException('The embedding provider is temporarily unavailable.', previous: $last);
    }
}
