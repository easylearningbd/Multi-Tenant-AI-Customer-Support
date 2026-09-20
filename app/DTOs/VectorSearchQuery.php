<?php

namespace App\DTOs;

final readonly class VectorSearchQuery
{
    /** @param list<float> $embedding @param list<int> $sourceIds */
    public function __construct(
        public int $tenantId,
        public int $botId,
        public array $embedding,
        public string $embeddingModel,
        public int $topK = 5,
        public ?float $minimumScore = null,
        public array $sourceIds = [],
    ) {}
}
