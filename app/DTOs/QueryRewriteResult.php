<?php

namespace App\DTOs;

final readonly class QueryRewriteResult
{
    public function __construct(
        public string $query,
        public string $model,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public bool $usedProvider = false,
    ) {}
}
