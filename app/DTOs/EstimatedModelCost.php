<?php

namespace App\DTOs;

final readonly class EstimatedModelCost
{
    public function __construct(
        public int $minorUnits,
        public string $currency,
    ) {}
}
