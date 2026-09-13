<?php

namespace Database\Factories;

use App\Enums\SupportTicketSenderType;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SupportTicketMessage> */
final class SupportTicketMessageFactory extends Factory
{
    protected $model = SupportTicketMessage::class;

    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'sender_id' => User::factory()->subscriber(),
            'sender_type' => SupportTicketSenderType::SUBSCRIBER,
            'body' => fake()->paragraph(),
        ];
    }
}
