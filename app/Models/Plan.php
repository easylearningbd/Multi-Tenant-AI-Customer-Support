<?php

namespace App\Models;

use App\Enums\PlanInterval;
use App\Services\DecimalMoney;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    public const LIMITS = [
        'ai_answers_per_month' => 'AI answers / month',
        'chatbots_limit' => 'Chatbots',
        'knowledge_bases_limit' => 'Knowledge bases',
        'knowledge_sources_limit' => 'Knowledge sources',
        'team_members_limit' => 'Team members',
        'storage_mb_limit' => 'Storage MB',
    ];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_minor',
        'currency',
        'interval',
        'trial_days',
        'custom_pricing',
        'is_active',
        'sort_order',
        'features',
        'limits',
    ];

    protected function casts(): array
    {
        return [
            'interval' => PlanInterval::class,
            'trial_days' => 'integer',
            'custom_pricing' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'features' => 'array',
            'limits' => 'array',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    public function scopeAvailableForSelection(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCheckoutEligible(Builder $query): Builder
    {
        return $query
            ->availableForSelection()
            ->where('custom_pricing', false)
            ->whereIn('interval', [PlanInterval::MONTHLY, PlanInterval::YEARLY]);
    }

    public function priceDecimal(): string
    {
        return DecimalMoney::fromMinor($this->price_minor);
    }

    public function formattedPrice(): string
    {
        return $this->custom_pricing
            ? __('Contact Sales')
            : $this->currency.' '.$this->priceDecimal();
    }

    public function limitFor(string $key): int
    {
        if (! array_key_exists($key, self::LIMITS)) {
            throw new InvalidArgumentException("Unknown plan limit [{$key}].");
        }

        return (int) (($this->limits ?? [])[$key] ?? 0);
    }

    public function hasUnlimitedLimit(string $key): bool
    {
        return $this->limitFor($key) === 0;
    }

    public function allowsUsage(string $key, int $currentUsage, int $requestedUnits = 1): bool
    {
        if ($currentUsage < 0 || $requestedUnits < 1) {
            throw new InvalidArgumentException('Usage values must be non-negative and requested units must be positive.');
        }

        return $this->hasUnlimitedLimit($key)
            || $currentUsage + $requestedUnits <= $this->limitFor($key);
    }

    public function supportsAutomatedCheckout(): bool
    {
        return $this->is_active
            && ! $this->custom_pricing
            && $this->interval->isRecurring();
    }

    public function supportsBankTransferPayment(): bool
    {
        return $this->is_active
            && ! $this->custom_pricing
            && $this->interval->isRecurring()
            && $this->price_minor > 0;
    }

    /** @return array<string, mixed> */
    public function subscriptionSnapshot(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'price_minor' => $this->price_minor,
            'currency' => $this->currency,
            'interval' => $this->interval->value,
            'trial_days' => $this->trial_days,
            'features' => array_values($this->features ?? []),
            'limits' => collect(array_keys(self::LIMITS))
                ->mapWithKeys(fn (string $key): array => [$key => $this->limitFor($key)])
                ->all(),
        ];
    }
}
