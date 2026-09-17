<?php

namespace App\DTOs;

final readonly class RagPrompt
{
    /** @param list<array{role: string, content: string}> $input */
    public function __construct(
        public string $instructions,
        public array $input,
        public string $model,
        public float $temperature,
        public int $maxOutputTokens,
    ) {}
}
