<?php

namespace App\Actions;

use App\Enums\WidgetPosition;
use App\Models\Bot;
use App\Models\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EnsureBotWidget
{
    public function handle(Bot $bot): Widget
    {
        return DB::transaction(function () use ($bot): Widget {
            $lockedBot = Bot::query()->whereKey($bot->id)->where('user_id', $bot->user_id)->lockForUpdate()->firstOrFail();
            $existing = Widget::query()->forBot($lockedBot)->first();
            if ($existing) {
                return $existing;
            }

            $welcome = trim((string) ($lockedBot->setting()->value('welcome_message')
                ?: config('neuraldesk.widgets.default_welcome_message')));
            $welcome = $welcome !== '' ? $welcome : 'How can we help you today?';
            $position = WidgetPosition::tryFrom((string) config('neuraldesk.widgets.default_position'))
                ?? WidgetPosition::BOTTOM_RIGHT;
            $accent = strtoupper((string) config('neuraldesk.widgets.default_accent_color'));
            $accent = array_key_exists($accent, (array) config('neuraldesk.widgets.accent_colors', []))
                ? $accent
                : '#6259E8';
            $widget = new Widget;
            $widget->public_id = (string) Str::ulid();
            $widget->user_id = $lockedBot->user_id;
            $widget->bot_id = $lockedBot->id;
            $widget->fill([
                'is_enabled' => true,
                'accent_color' => $accent,
                'position' => $position,
                'welcome_message' => $welcome,
            ]);
            $widget->save();

            return $widget;
        }, 3);
    }
}
