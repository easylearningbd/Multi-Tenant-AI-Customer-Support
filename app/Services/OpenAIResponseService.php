<?php

namespace App\Services;

use App\Contracts\ChatCompletionProviderInterface;
use App\DTOs\ChatCompletionRequest;
use App\DTOs\ChatCompletionResult;
use App\Exceptions\ChatProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class OpenAIResponseService implements ChatCompletionProviderInterface
{
    public function respond(ChatCompletionRequest $request): ChatCompletionResult
    {
        $key = (string) config('neuraldesk.ai.openai.api_key');
        if ($key === '') {
            throw new ChatProviderException('OpenAI chat configuration is incomplete.');
        }

        $payload = [
            'model' => $request->model,
            'instructions' => $request->instructions,
            'input' => $request->input,
            'temperature' => $request->temperature,
            'max_output_tokens' => $request->maxOutputTokens,
            'safety_identifier' => $request->safetyIdentifier,
            'store' => false,
        ];
        $attempts = max(1, min(3, (int) config('neuraldesk.ai.openai.chat_max_retries', 2)));
        $last = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $started = hrtime(true);
            try {
                $response = Http::baseUrl(rtrim((string) config('neuraldesk.ai.openai.base_url'), '/'))
                    ->withToken($key)->acceptJson()
                    ->withHeaders(['Idempotency-Key' => $request->idempotencyKey])
                    ->connectTimeout((int) config('neuraldesk.ai.openai.connect_timeout', 10))
                    ->timeout((int) config('neuraldesk.ai.openai.request_timeout', 60))
                    ->post('/responses', $payload);
                if ($response->successful()) {
                    $text = trim((string) $response->json('output_text', ''));
                    if ($text === '') {
                        foreach ($response->json('output', []) as $item) {
                            foreach ($item['content'] ?? [] as $content) {
                                if (($content['type'] ?? null) === 'output_text') {
                                    $text .= (string) ($content['text'] ?? '');
                                }
                            }
                        }
                        $text = trim($text);
                    }
                    if ($text === '') {
                        throw new ChatProviderException('The chat provider returned no answer.');
                    }

                    return new ChatCompletionResult(
                        $text,
                        (string) $response->json('id'),
                        (string) $response->json('model', $request->model),
                        (int) $response->json('usage.input_tokens', 0),
                        (int) $response->json('usage.output_tokens', 0),
                        (string) ($response->json('incomplete_details.reason') ?: $response->json('status', 'completed')),
                        max(0, (int) round((hrtime(true) - $started) / 1_000_000)),
                    );
                }
                if (! in_array($response->status(), [408, 409, 429, 500, 502, 503, 504], true)) {
                    throw new ChatProviderException('The chat provider rejected the request.');
                }
                $last = new ChatProviderException('The chat provider is temporarily unavailable.', true);
            } catch (ConnectionException $exception) {
                $last = new ChatProviderException('The chat provider could not be reached.', true, $exception);
            } catch (ChatProviderException $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                $last = new ChatProviderException('The chat provider failed safely.', true, $exception);
            }
            if ($attempt < $attempts) {
                usleep(150000 * (2 ** ($attempt - 1)));
            }
        }

        throw $last ?? new ChatProviderException('The chat provider failed safely.', true);
    }
}
