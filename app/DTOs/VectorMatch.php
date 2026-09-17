<?php

namespace App\DTOs;

final readonly class VectorMatch
{
    public function __construct(
        public string $chunkUuid,
        public string $sourceUuid,
        public string $sourceName,
        public string $content,
        public float $score,
        public int $chunkId,
        public int $sourceId,
    ) {}
}
