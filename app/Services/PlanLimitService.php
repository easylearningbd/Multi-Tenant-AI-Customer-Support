<?php

namespace App\Services;

use App\Enums\PlanMetric;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Plan;
use App\Models\Subscription;

final class PlanLimitService
{
    public function allows(Plan|Subscription $plan, string $limit, int $currentUsage, int $requestedUnits = 1): bool
    {
        return $plan->allowsUsage($limit, $currentUsage, $requestedUnits);
    }

    public function ensureAllows(Plan|Subscription $plan, string $limit, int $currentUsage, int $requestedUnits = 1): void
    {
        if (! $this->allows($plan, $limit, $currentUsage, $requestedUnits)) {
            throw new PlanLimitExceededException(PlanMetric::from($limit), $currentUsage, $plan->limitFor($limit));
        }
    }
}
