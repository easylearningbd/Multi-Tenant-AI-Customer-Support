<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Validation\ValidationException;

final class PlanLimitService
{
    public function allows(Plan $plan, string $limit, int $currentUsage, int $requestedUnits = 1): bool
    {
        return $plan->allowsUsage($limit, $currentUsage, $requestedUnits);
    }

    public function ensureAllows(Plan $plan, string $limit, int $currentUsage, int $requestedUnits = 1): void
    {
        if (! $this->allows($plan, $limit, $currentUsage, $requestedUnits)) {
            throw ValidationException::withMessages([
                'plan_limit' => __('Your current plan limit has been reached.'),
            ]);
        }
    }
}
