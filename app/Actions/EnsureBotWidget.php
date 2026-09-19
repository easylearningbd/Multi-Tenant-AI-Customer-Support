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

            $welcome = $lockedBot->setting()->value('welcome_message')
                ?: (string) config('neuraldesk.widgets.default_welcome_message');
            $widget = new Widget;
            $widget->public_id = (string) Str::ulid();
            $widget->user_id = $lockedBot->user_id;
            $widget->bot_id = $lockedBot->id;
            $widget->fill([
                'is_enabled' => true,
                'accent_color' => (string) config('neuraldesk.widgets.default_accent_color'),
                'position' => WidgetPosition::from((string) config('neuraldesk.widgets.default_position')),
                'welcome_message' => $welcome,
            ]);
            $widget->save();

            return $widget;
        }, 3);
    }
}
