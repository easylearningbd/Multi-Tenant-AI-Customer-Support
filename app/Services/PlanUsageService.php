<?php

namespace App\Services;

use App\DTOs\UsagePeriod;
use App\Enums\PlanMetric;
use App\Enums\UsageLedgerStatus;
use App\Enums\UsageType;
use App\Exceptions\PlanLimitExceededException;
use App\Models\Subscription;
use App\Models\UsageCounter;
use App\Models\UsageLedger;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class PlanUsageService
{
    public const BYTES_PER_MEGABYTE = 1048576;

    public function __construct(
        private readonly CurrentSubscriptionResolver $subscriptions,
        private readonly UsagePeriodResolver $periods,
    ) {}

    public function activeSubscription(User $owner): Subscription
    {
        $subscription = $this->subscriptions->for($owner);
        if (! $subscription || ! $subscription->grantsEntitlements()) {
            throw ValidationException::withMessages([
                'plan_limit' => __('An active subscription is required for this action.'),
            ]);
        }

        return $subscription;
    }

    public function period(Subscription $subscription): UsagePeriod
    {
        return $this->periods->monthly($subscription);
    }

    public function limit(Subscription $subscription, PlanMetric $metric): int
    {
        $limit = $subscription->limitFor($metric->value);

        return $metric->storesBytes() && $limit > 0
            ? $limit * self::BYTES_PER_MEGABYTE
            : $limit;
    }

    public function isUnlimited(Subscription $subscription, PlanMetric $metric): bool
    {
        return $subscription->hasUnlimitedLimit($metric->value);
    }

    public function current(User $owner, Subscription $subscription, PlanMetric $metric): int
    {
        return match ($metric) {
            PlanMetric::AI_ANSWERS => $this->currentAiAnswers($owner, $subscription),
            PlanMetric::CHATBOTS, PlanMetric::KNOWLEDGE_BASES => $owner->bots()->where('is_active', true)->count(),
            PlanMetric::KNOWLEDGE_SOURCES => $owner->knowledgeSources()->count(),
            PlanMetric::TEAM_MEMBERS => 0,
            PlanMetric::STORAGE => (int) $owner->knowledgeSources()->sum('file_size_bytes'),
        };
    }

    public function remaining(User $owner, Subscription $subscription, PlanMetric $metric): ?int
    {
        if ($this->isUnlimited($subscription, $metric)) {
            return null;
        }

        return max(0, $this->limit($subscription, $metric) - $this->current($owner, $subscription, $metric));
    }

    public function ensureCanUse(User $owner, Subscription $subscription, PlanMetric $metric, int $quantity = 1): void
    {
        $current = $this->current($owner, $subscription, $metric);
        $this->ensureWithinLimit($subscription, $metric, $current, $quantity);
    }

    public function ensureWithinLimit(Subscription $subscription, PlanMetric $metric, int $current, int $quantity = 1): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quota quantity must be positive.');
        }

        if ($this->isUnlimited($subscription, $metric)) {
            return;
        }

        $limit = $this->limit($subscription, $metric);
        if ($current + $quantity > $limit) {
            throw new PlanLimitExceededException($metric, $current, $limit);
        }
    }

    public function reserve(User $owner, Subscription $subscription, PlanMetric $metric, int $quantity): UsageCounter
    {
        $period = $this->period($subscription);
        $counter = $this->lockedCounter($owner, $subscription, $metric, $period);
        $authoritativeUsed = $this->authoritativeCounterUsage($owner, $subscription, $metric);
        $authoritativeReserved = $this->authoritativeReservedUsage($owner, $subscription, $metric);
        $counter->forceFill(['used' => $authoritativeUsed, 'reserved' => $authoritativeReserved])->save();

        $this->ensureWithinLimit($subscription, $metric, $counter->used + $counter->reserved, $quantity);
        $counter->increment('reserved', $quantity);

        return $counter->refresh();
    }

    public function consumeLedgerReservation(UsageLedger $ledger): void
    {
        $counter = $this->counterForLedger($ledger);
        if ($counter) {
            $counter->forceFill([
                'reserved' => max(0, $counter->reserved - $ledger->quantity),
                'used' => $counter->used + $ledger->quantity,
            ])->save();
        }
    }

    public function releaseLedgerReservation(UsageLedger $ledger): void
    {
        $counter = $this->counterForLedger($ledger);
        if ($counter) {
            $counter->forceFill(['reserved' => max(0, $counter->reserved - $ledger->quantity)])->save();
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    public function summary(User $owner, Subscription $subscription): Collection
    {
        return collect(PlanMetric::cases())->map(function (PlanMetric $metric) use ($owner, $subscription): array {
            $used = $this->current($owner, $subscription, $metric);
            $limit = $this->limit($subscription, $metric);
            $unlimited = $this->isUnlimited($subscription, $metric);
            $percentage = $unlimited || $limit === 0 ? 0 : min(100, (int) round(($used / $limit) * 100));

            return [
                'key' => $metric->value,
                'label' => $metric->label(),
                'used' => $used,
                'limit' => $limit,
                'unlimited' => $unlimited,
                'remaining' => $unlimited ? null : max(0, $limit - $used),
                'percentage' => $percentage,
                'state' => $unlimited ? 'unlimited' : ($used >= $limit ? 'danger' : ($percentage >= 80 ? 'warning' : 'normal')),
                'storesBytes' => $metric->storesBytes(),
                'usedLabel' => $metric->storesBytes() ? $this->formatBytes($used) : number_format($used),
                'limitLabel' => $unlimited ? __('Unlimited') : ($metric->storesBytes() ? $this->formatBytes($limit) : number_format($limit)),
            ];
        });
    }

    public function reconcile(User $owner, Subscription $subscription): Collection
    {
        return collect(PlanMetric::cases())->map(function (PlanMetric $metric) use ($owner, $subscription): UsageCounter {
            $period = $this->period($subscription);
            $counter = $this->lockedCounter($owner, $subscription, $metric, $period);
            $counter->forceFill([
                'used' => $this->current($owner, $subscription, $metric),
                'reserved' => $this->authoritativeReservedUsage($owner, $subscription, $metric),
            ])->save();

            return $counter;
        });
    }

    private function currentAiAnswers(User $owner, Subscription $subscription): int
    {
        $period = $this->period($subscription);

        return (int) UsageLedger::query()
            ->where('user_id', $owner->id)
            ->where('type', UsageType::AI_ANSWER)
            ->where('status', UsageLedgerStatus::COMMITTED)
            ->where('committed_at', '>=', $period->startsAt)
            ->where('committed_at', '<', $period->endsAt)
            ->sum('quantity');
    }

    private function authoritativeCounterUsage(User $owner, Subscription $subscription, PlanMetric $metric): int
    {
        return $metric === PlanMetric::AI_ANSWERS
            ? $this->currentAiAnswers($owner, $subscription)
            : ($metric === PlanMetric::STORAGE ? $this->current($owner, $subscription, $metric) : 0);
    }

    private function authoritativeReservedUsage(User $owner, Subscription $subscription, PlanMetric $metric): int
    {
        $type = match ($metric) {
            PlanMetric::AI_ANSWERS => UsageType::AI_ANSWER,
            PlanMetric::STORAGE => UsageType::KNOWLEDGE_STORAGE,
            default => null,
        };
        if (! $type) {
            return 0;
        }
        $period = $this->period($subscription);

        return (int) UsageLedger::query()
            ->where('user_id', $owner->id)
            ->where('type', $type)
            ->where('status', UsageLedgerStatus::RESERVED)
            ->where('period_starts_at', $period->startsAt)
            ->where('period_ends_at', $period->endsAt)
            ->sum('quantity');
    }

    private function lockedCounter(User $owner, Subscription $subscription, PlanMetric $metric, UsagePeriod $period): UsageCounter
    {
        UsageCounter::query()->firstOrCreate([
            'user_id' => $owner->id,
            'metric' => $metric->value,
            'period_starts_at' => $period->startsAt,
            'period_ends_at' => $period->endsAt,
        ], [
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'used' => 0,
            'reserved' => 0,
        ]);

        $counter = UsageCounter::query()
            ->where('user_id', $owner->id)
            ->where('metric', $metric)
            ->where('period_starts_at', $period->startsAt)
            ->where('period_ends_at', $period->endsAt)
            ->lockForUpdate()
            ->firstOrFail();
        if ($counter->subscription_id !== $subscription->id || $counter->plan_id !== $subscription->plan_id) {
            $counter->forceFill([
                'subscription_id' => $subscription->id,
                'plan_id' => $subscription->plan_id,
            ])->save();
        }

        return $counter;
    }

    private function counterForLedger(UsageLedger $ledger): ?UsageCounter
    {
        if (! $ledger->subscription_id) {
            return null;
        }

        return UsageCounter::query()
            ->where('user_id', $ledger->user_id)
            ->where('metric', $ledger->type->metric())
            ->where('period_starts_at', $ledger->period_starts_at)
            ->where('period_ends_at', $ledger->period_ends_at)
            ->lockForUpdate()
            ->first();
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= self::BYTES_PER_MEGABYTE) {
            return number_format($bytes / self::BYTES_PER_MEGABYTE, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return number_format($bytes).' B';
    }
}
