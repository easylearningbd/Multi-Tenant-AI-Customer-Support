<?php

namespace Database\Factories;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportTicket> */
final class SupportTicketFactory extends Factory
{
    protected $model = SupportTicket::class;

    public function definition(): array
    {
        return [
            'reference' => 'TKT-'.fake()->unique()->numberBetween(100001, 999999),
            'requester_id' => User::factory()->subscriber(),
            'subject' => fake()->sentence(6),
            'priority' => SupportTicketPriority::MEDIUM,
            'category' => null,
            'status' => SupportTicketStatus::AWAITING_SUPPORT,
            'last_activity_at' => now(),
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'status' => SupportTicketStatus::RESOLVED,
            'resolved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => [
            'status' => SupportTicketStatus::CLOSED,
            'closed_at' => now(),
        ]);
    }
}
