<?php

namespace Database\Factories;

use App\Enums\WidgetChannel;
use App\Models\VisitorSession;
use App\Models\Widget;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<VisitorSession> */
final class VisitorSessionFactory extends Factory
{
    protected $model = VisitorSession::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'widget_id' => Widget::factory(),
            'user_id' => fn (array $attributes): int => Widget::query()->findOrFail($attributes['widget_id'])->user_id,
            'bot_id' => fn (array $attributes): int => Widget::query()->findOrFail($attributes['widget_id'])->bot_id,
            'token_hash' => hash('sha256', Str::random(64)),
            'origin' => 'https://example.test',
            'channel' => WidgetChannel::EMBEDDED,
            'visitor_identifier' => 'Visitor '.Str::upper(Str::random(4)),
            'last_seen_at' => now('UTC'),
            'expires_at' => now('UTC')->addHours(12),
        ];
    }
}
