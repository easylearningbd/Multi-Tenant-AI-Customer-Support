<?php

namespace Database\Factories;

use App\Enums\PlanInterval;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Plan> */
final class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'description' => fake()->sentence(),
            'price_minor' => 2900,
            'currency' => 'USD',
            'interval' => PlanInterval::MONTHLY,
            'trial_days' => null,
            'custom_pricing' => false,
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 100),
            'features' => ['Knowledge base answers', 'Email support'],
            'limits' => array_fill_keys(array_keys(Plan::LIMITS), 0),
        ];
    }

    public function trial(): static
    {
        return $this->state(fn (): array => [
            'price_minor' => 0,
            'interval' => PlanInterval::TRIAL,
            'trial_days' => 14,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
