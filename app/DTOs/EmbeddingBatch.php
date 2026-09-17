<?php

namespace App\DTOs;

final readonly class EmbeddingBatch
{
    /** @param list<list<float>> $vectors */
    public function __construct(public array $vectors, public string $model, public int $dimensions) {}
}
