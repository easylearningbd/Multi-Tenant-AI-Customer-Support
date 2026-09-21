<?php

namespace App\Services;

use App\DTOs\UsagePeriod;
use App\Enums\PlanInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class UsagePeriodResolver
{
    public function monthly(Subscription $subscription, ?CarbonInterface $at = null): UsagePeriod
    {
        $now = $at ? CarbonImmutable::instance($at)->utc() : CarbonImmutable::now('UTC');
        $metadata = $subscription->metadata ?? [];
        $anchorValue = $metadata['usage_period_anchor'] ?? null;
        $anchor = $anchorValue
            ? CarbonImmutable::parse((string) $anchorValue, 'UTC')
            : CarbonImmutable::instance($subscription->current_period_starts_at ?? $subscription->starts_at ?? $now)->utc();

        if ($subscription->status === SubscriptionStatus::TRIALING) {
            $end = CarbonImmutable::instance($subscription->trial_ends_at ?? $subscription->current_period_ends_at ?? $anchor->addMonth())->utc();

            return new UsagePeriod($anchor, $end);
        }

        $interval = (string) ($subscription->plan_snapshot['interval'] ?? PlanInterval::MONTHLY->value);
        if ($interval === PlanInterval::MONTHLY->value && ! isset($metadata['usage_period_anchor'])) {
            return new UsagePeriod(
                CarbonImmutable::instance($subscription->current_period_starts_at ?? $anchor)->utc(),
                CarbonImmutable::instance($subscription->current_period_ends_at ?? $anchor->addMonthNoOverflow())->utc(),
            );
        }

        $start = $anchor;
        while ($start->addMonthNoOverflow()->lessThanOrEqualTo($now)) {
            $start = $start->addMonthNoOverflow();
        }
        while ($start->greaterThan($now)) {
            $start = $start->subMonthNoOverflow();
        }

        $end = $start->addMonthNoOverflow();
        if ($subscription->current_period_ends_at && $end->greaterThan($subscription->current_period_ends_at)) {
            $end = CarbonImmutable::instance($subscription->current_period_ends_at)->utc();
        }

        return new UsagePeriod($start, $end);
    }
}
