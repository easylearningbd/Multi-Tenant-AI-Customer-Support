<?php

namespace App\DTOs;

use Carbon\CarbonImmutable;

final readonly class UsagePeriod
{
    public function __construct(
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
    ) {}
}
