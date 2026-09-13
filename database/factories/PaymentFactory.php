<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Payment> */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        $plan = Plan::factory();

        return [
            'reference' => 'PAY-'.Str::upper((string) Str::ulid()),
            'user_id' => User::factory()->subscriber(),
            'plan_id' => $plan,
            'subscription_id' => null,
            'payment_method' => PaymentMethod::BANK_TRANSFER,
            'status' => PaymentStatus::PENDING,
            'expected_amount_minor' => 2900,
            'submitted_amount_minor' => 2900,
            'currency' => 'USD',
            'plan_name_snapshot' => 'Test Plan',
            'plan_interval_snapshot' => 'monthly',
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
            'payer_name' => fake()->name(),
            'payer_bank_name' => fake()->company().' Bank',
            'transaction_reference' => fake()->unique()->bothify('BANK-########'),
            'transferred_at' => now()->subHour(),
            'notes' => null,
            'submitted_at' => now(),
            'paid_at' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'rejection_reason' => null,
            'provider' => null,
            'provider_payment_id' => null,
            'metadata' => ['source' => 'subscriber_bank_transfer'],
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::PAID,
            'paid_at' => now(),
            'reviewed_at' => now(),
        ]);
    }
}
