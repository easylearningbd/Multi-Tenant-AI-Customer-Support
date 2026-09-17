<?php

namespace App\Contracts;

use App\DTOs\EmbeddingBatch;

interface EmbeddingProviderInterface
{
    /** @param list<string> $inputs */
    public function embedMany(array $inputs): EmbeddingBatch;
}
