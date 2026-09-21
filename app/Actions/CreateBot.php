<?php

namespace App\Actions;

use App\Enums\PlanMetric;
use App\Models\Bot;
use App\Models\User;
use App\Services\BotDefaults;
use App\Services\PlanUsageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CreateBot
{
    public function __construct(
        private readonly PlanUsageService $usage,
        private readonly BotDefaults $defaults,
        private readonly CreateDefaultBotSettings $createDefaultSettings,
        private readonly EnsureBotWidget $ensureBotWidget,
    ) {}

    public function handle(User $subscriber, string $name): Bot
    {
        return DB::transaction(function () use ($subscriber, $name): Bot {
            $owner = User::query()->subscribers()->lockForUpdate()->findOrFail($subscriber->id);
            $subscription = $this->usage->activeSubscription($owner);
            $activeBots = $owner->bots()->where('is_active', true)->count();
            $this->usage->ensureWithinLimit($subscription, PlanMetric::CHATBOTS, $activeBots);
            $this->usage->ensureWithinLimit($subscription, PlanMetric::KNOWLEDGE_BASES, $activeBots);

            $normalizedName = Str::squish($name);
            $bot = new Bot;
            $bot->public_id = (string) Str::ulid();
            $bot->name = $normalizedName;
            $bot->display_name = $this->defaults->displayName($normalizedName);
            $bot->slug = $this->uniqueSlug($owner, $normalizedName);
            $bot->is_active = false;
            $bot->user()->associate($owner);
            $bot->save();

            $this->createDefaultSettings->handle($bot);

            $this->ensureBotWidget->handle($bot);

            return $bot->fresh(['setting', 'widget']);
        }, 3);
    }

    private function uniqueSlug(User $owner, string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'bot';
        $base = Str::limit($base, 110, '');
        $slug = $base;
        $suffix = 2;

        while ($owner->bots()->withTrashed()->where('slug', $slug)->exists()) {
            $ending = '-'.$suffix;
            $slug = Str::limit($base, 120 - strlen($ending), '').$ending;
            $suffix++;
        }

        return $slug;
    }
}
