<?php

namespace App\Actions\Admin;

use App\Enums\PlanInterval;
use App\Models\Plan;
use App\Services\DecimalMoney;
use App\Services\PlanDeletionGuard;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SavePlan
{
    public function __construct(private readonly PlanDeletionGuard $deletionGuard) {}

    /** @param array<string, mixed> $data */
    public function create(array $data): Plan
    {
        return $this->persist(new Plan, $data);
    }

    /** @param array<string, mixed> $data */
    public function update(Plan $plan, array $data): Plan
    {
        return $this->persist($plan, $data);
    }

    /** @param array<string, mixed> $data */
    private function persist(Plan $plan, array $data): Plan
    {
        $interval = PlanInterval::from($data['interval']);
        $customPricing = (bool) $data['custom_pricing'];
        $priceMinor = $customPricing || $interval === PlanInterval::TRIAL
            ? 0
            : DecimalMoney::toMinor($data['price']);

        if ($plan->exists
            && $this->deletionGuard->isReferenced($plan)
            && ($plan->price_minor !== $priceMinor
                || $plan->currency !== $data['currency']
                || $plan->interval !== $interval
                || $plan->custom_pricing !== $customPricing)) {
            throw new DomainException('Commercial terms cannot change while billing records reference this plan.');
        }

        return DB::transaction(function () use ($plan, $data, $interval, $customPricing, $priceMinor): Plan {
            $plan->fill([
                'name' => $data['name'],
                'slug' => $data['slug'] ?: ($plan->exists ? $plan->slug : $this->uniqueSlug($data['name'])),
                'description' => $data['description'],
                'price_minor' => $priceMinor,
                'currency' => $data['currency'],
                'interval' => $interval,
                'trial_days' => $interval === PlanInterval::TRIAL ? (int) $data['trial_days'] : null,
                'custom_pricing' => $customPricing,
                'is_active' => (bool) $data['is_active'],
                'sort_order' => (int) $data['sort_order'],
                'features' => $data['features'],
                'limits' => Arr::only($data, array_keys(Plan::LIMITS)),
            ]);
            $plan->save();

            return $plan;
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'plan';
        $slug = $base;
        $suffix = 2;

        while (Plan::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
