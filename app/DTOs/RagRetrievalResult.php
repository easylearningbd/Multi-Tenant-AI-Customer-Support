<?php

namespace App\DTOs;

final readonly class RagRetrievalResult
{
    /** @param list<VectorMatch> $matches */
    public function __construct(
        public array $matches,
        public string $embeddingModel,
        public int $embeddingDimensions,
        public ?float $minimumScore,
        public int $durationMs,
    ) {}

    public function confidence(): float
    {
        return $this->matches[0]->score ?? 0.0;
    }
}
