<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

final class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'status',
        'starts_at',
        'trial_ends_at',
        'current_period_starts_at',
        'current_period_ends_at',
        'ends_at',
        'canceled_at',
        'provider',
        'provider_subscription_id',
        'provider_price_id',
        'trial_claim_key',
        'plan_snapshot',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'immutable_datetime',
            'trial_ends_at' => 'immutable_datetime',
            'current_period_starts_at' => 'immutable_datetime',
            'current_period_ends_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'canceled_at' => 'immutable_datetime',
            'plan_snapshot' => 'array',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return HasMany<UsageCounter, $this> */
    public function usageCounters(): HasMany
    {
        return $this->hasMany(UsageCounter::class);
    }

    public function effectiveStatus(?CarbonInterface $at = null): SubscriptionStatus
    {
        $at = $at ? CarbonImmutable::instance($at) : CarbonImmutable::now();

        if ($this->status === SubscriptionStatus::TRIALING) {
            return $this->trial_ends_at?->greaterThan($at)
                ? SubscriptionStatus::TRIALING
                : SubscriptionStatus::EXPIRED;
        }

        if ($this->status === SubscriptionStatus::ACTIVE) {
            $accessEnd = $this->ends_at ?? $this->current_period_ends_at;

            return $accessEnd && $accessEnd->lessThanOrEqualTo($at)
                ? SubscriptionStatus::EXPIRED
                : SubscriptionStatus::ACTIVE;
        }

        if ($this->status === SubscriptionStatus::CANCELED) {
            $accessEnd = $this->ends_at ?? $this->current_period_ends_at;

            return $accessEnd && $accessEnd->greaterThan($at)
                ? SubscriptionStatus::CANCELED
                : SubscriptionStatus::EXPIRED;
        }

        return $this->status;
    }

    public function grantsEntitlements(?CarbonInterface $at = null): bool
    {
        return in_array($this->effectiveStatus($at), [
            SubscriptionStatus::TRIALING,
            SubscriptionStatus::ACTIVE,
            SubscriptionStatus::CANCELED,
        ], true);
    }

    public function limitFor(string $key): int
    {
        if (! array_key_exists($key, Plan::LIMITS)) {
            throw new InvalidArgumentException("Unknown plan limit [{$key}].");
        }

        return (int) (($this->plan_snapshot['limits'] ?? [])[$key] ?? 0);
    }

    public function hasUnlimitedLimit(string $key): bool
    {
        return $this->limitFor($key) === 0;
    }

    public function allowsUsage(string $key, int $currentUsage, int $requestedUnits = 1): bool
    {
        if (! $this->grantsEntitlements()) {
            return false;
        }

        if ($currentUsage < 0 || $requestedUnits < 1) {
            throw new InvalidArgumentException('Usage values must be non-negative and requested units must be positive.');
        }

        return $this->hasUnlimitedLimit($key)
            || $currentUsage + $requestedUnits <= $this->limitFor($key);
    }

    /** @return list<string> */
    public function featureList(): array
    {
        return array_values(array_filter(
            $this->plan_snapshot['features'] ?? [],
            fn (mixed $feature): bool => is_string($feature) && trim($feature) !== '',
        ));
    }

    public function planName(): string
    {
        return (string) ($this->plan_snapshot['name'] ?? $this->plan?->name ?? __('Unknown plan'));
    }
}
