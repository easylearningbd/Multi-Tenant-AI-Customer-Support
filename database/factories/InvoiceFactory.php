<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Invoice> */
final class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'number' => 'INV-'.Str::upper((string) Str::ulid()),
            'user_id' => User::factory()->subscriber(),
            'payment_id' => Payment::factory(),
            'plan_id' => Plan::factory(),
            'status' => PaymentStatus::PENDING,
            'subtotal_minor' => 2900,
            'total_minor' => 2900,
            'currency' => 'USD',
            'description' => 'Test Plan subscription',
            'issued_at' => now(),
            'paid_at' => null,
            'metadata' => null,
        ];
    }
}
