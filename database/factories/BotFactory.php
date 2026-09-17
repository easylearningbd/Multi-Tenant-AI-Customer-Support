<?php

namespace Database\Factories;

use App\Models\Bot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Bot> */
final class BotFactory extends Factory
{
    protected $model = Bot::class;

    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'public_id' => (string) Str::ulid(),
            'user_id' => User::factory()->subscriber(),
            'name' => $name,
            'display_name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 999999),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
