<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subscription> */
final class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $plan = Plan::factory();

        return [
            'user_id' => User::factory()->subscriber(),
            'plan_id' => $plan,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now()->subDays(5),
            'trial_ends_at' => null,
            'current_period_starts_at' => now()->subDays(5),
            'current_period_ends_at' => now()->addDays(25),
            'ends_at' => null,
            'canceled_at' => null,
            'provider' => 'test',
            'provider_subscription_id' => fake()->unique()->uuid(),
            'provider_price_id' => null,
            'trial_claim_key' => null,
            'plan_snapshot' => [
                'name' => 'Test Plan',
                'slug' => 'test-plan',
                'price_minor' => 2900,
                'currency' => 'USD',
                'interval' => 'monthly',
                'trial_days' => null,
                'features' => ['Test feature'],
                'limits' => array_fill_keys(array_keys(Plan::LIMITS), 0),
            ],
            'metadata' => null,
        ];
    }
}
