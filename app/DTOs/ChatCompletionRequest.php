<?php

namespace App\DTOs;

final readonly class ChatCompletionRequest
{
    /** @param list<array{role: string, content: string}> $input */
    public function __construct(
        public string $model,
        public string $instructions,
        public array $input,
        public float $temperature,
        public int $maxOutputTokens,
        public string $idempotencyKey,
        public string $safetyIdentifier,
    ) {}
}
