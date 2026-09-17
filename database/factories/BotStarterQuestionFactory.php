<?php

namespace Database\Factories;

use App\Models\Bot;
use App\Models\BotStarterQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BotStarterQuestion> */
final class BotStarterQuestionFactory extends Factory
{
    protected $model = BotStarterQuestion::class;

    public function definition(): array
    {
        return [
            'bot_id' => Bot::factory(),
            'question' => fake()->sentence(6),
            'position' => 1,
        ];
    }
}
