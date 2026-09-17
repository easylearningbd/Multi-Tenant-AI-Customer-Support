<?php

namespace App\DTOs;

final readonly class ChatCompletionResult
{
    public function __construct(
        public string $text,
        public string $responseId,
        public string $model,
        public int $inputTokens,
        public int $outputTokens,
        public string $finishReason,
        public int $latencyMs,
    ) {}
}
