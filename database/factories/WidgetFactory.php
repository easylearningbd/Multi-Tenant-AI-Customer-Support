<?php

namespace Database\Factories;

use App\Enums\WidgetPosition;
use App\Models\Bot;
use App\Models\Widget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Widget> */
final class WidgetFactory extends Factory
{
    protected $model = Widget::class;

    public function definition(): array
    {
        return [
            'public_id' => (string) Str::ulid(),
            'bot_id' => Bot::factory(),
            'user_id' => fn (array $attributes): int => Bot::query()->findOrFail($attributes['bot_id'])->user_id,
            'is_enabled' => true,
            'accent_color' => '#6259E8',
            'position' => WidgetPosition::BOTTOM_RIGHT,
            'welcome_message' => 'How can we help you today?',
        ];
    }
}
