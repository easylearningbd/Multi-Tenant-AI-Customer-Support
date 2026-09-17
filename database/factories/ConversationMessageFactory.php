<?php

namespace Database\Factories;

use App\Enums\MessageActor;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ConversationMessage> */
final class ConversationMessageFactory extends Factory
{
    protected $model = ConversationMessage::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'conversation_id' => Conversation::factory(),
            'user_id' => fn (array $attributes) => Conversation::find($attributes['conversation_id'])->user_id,
            'bot_id' => fn (array $attributes) => Conversation::find($attributes['conversation_id'])->bot_id,
            'actor_type' => MessageActor::VISITOR,
            'status' => MessageStatus::RECEIVED,
            'idempotency_key' => (string) Str::uuid(),
            'body' => fake()->sentence(),
        ];
    }
}
