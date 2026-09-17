<?php

namespace Database\Factories;

use App\Enums\PrechatFieldType;
use App\Models\Bot;
use App\Models\BotPrechatField;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BotPrechatField> */
final class BotPrechatFieldFactory extends Factory
{
    protected $model = BotPrechatField::class;

    public function definition(): array
    {
        return [
            'bot_id' => Bot::factory(),
            'key' => 'custom_'.fake()->unique()->slug(2),
            'label' => fake()->words(2, true),
            'type' => PrechatFieldType::TEXT,
            'placeholder' => fake()->optional()->sentence(3),
            'is_required' => false,
            'position' => 1,
            'options' => null,
        ];
    }
}
