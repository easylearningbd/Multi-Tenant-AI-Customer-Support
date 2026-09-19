<?php

namespace App\Actions;

use App\Models\Bot;
use App\Models\User;
use App\Models\Widget;
use App\Services\WidgetOriginPolicy;
use Illuminate\Support\Facades\DB;

final class UpdateWidgetAppearance
{
    public function __construct(private readonly WidgetOriginPolicy $origins) {}

    /** @param array{is_enabled: bool, accent_color: string, position: string, welcome_message: string, allowed_origins?: list<string>} $attributes */
    public function handle(User $owner, Bot $bot, Widget $widget, array $attributes): Widget
    {
        abort_unless($bot->user_id === $owner->id && $widget->user_id === $owner->id && $widget->bot_id === $bot->id, 404);

        return DB::transaction(function () use ($owner, $bot, $widget, $attributes): Widget {
            $locked = Widget::query()->ownedBy($owner)->forBot($bot)->whereKey($widget->id)->lockForUpdate()->firstOrFail();
            $locked->fill([
                'is_enabled' => $attributes['is_enabled'],
                'accent_color' => strtoupper($attributes['accent_color']),
                'position' => $attributes['position'],
                'welcome_message' => trim($attributes['welcome_message']),
            ])->save();

            if (array_key_exists('allowed_origins', $attributes)) {
                $normalized = collect($attributes['allowed_origins'])
                    ->map(fn (string $origin): ?string => $this->origins->normalize($origin))
                    ->filter()
                    ->unique()
                    ->values();

                $locked->domains()->where('user_id', $owner->id)->where('bot_id', $bot->id)
                    ->whereNotIn('origin', $normalized->all())->delete();

                foreach ($normalized as $origin) {
                    if ($locked->domains()->where('origin', $origin)->exists()) {
                        continue;
                    }
                    $domain = $locked->domains()->make(['origin' => $origin]);
                    $domain->user_id = $owner->id;
                    $domain->bot_id = $bot->id;
                    $domain->save();
                }
            }

            return $locked->fresh(['bot', 'domains']);
        }, 3);
    }
}
