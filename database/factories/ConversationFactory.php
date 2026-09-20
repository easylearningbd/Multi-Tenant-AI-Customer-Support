<?php

namespace Database\Factories;

use App\Enums\ConversationHandlingMode;
use App\Enums\ConversationStatus;
use App\Models\Bot;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Conversation> */
final class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory()->subscriber(),
            'bot_id' => fn (array $attributes) => Bot::factory()->create(['user_id' => $attributes['user_id']])->id,
            'status' => ConversationStatus::OPEN_AI,
            'handling_mode' => ConversationHandlingMode::AI,
            'channel' => 'subscriber_api',
            'visitor_identifier' => null,
            'subject' => fake()->sentence(5),
            'started_at' => now('UTC'),
            'last_message_at' => now('UTC'),
            'last_message_preview' => fake()->sentence(),
            'last_message_sender_type' => 'visitor',
            'unread_count' => 0,
        ];
    }
}
