<?php

namespace App\Actions;

use App\Models\Bot;
use App\Models\User;
use App\Services\BotDefaults;
use App\Services\CurrentSubscriptionResolver;
use App\Services\PlanLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class CreateBot
{
    public function __construct(
        private readonly CurrentSubscriptionResolver $subscriptions,
        private readonly PlanLimitService $limits,
        private readonly BotDefaults $defaults,
        private readonly CreateDefaultBotSettings $createDefaultSettings,
    ) {}

    public function handle(User $subscriber, string $name): Bot
    {
        return DB::transaction(function () use ($subscriber, $name): Bot {
            $owner = User::query()->subscribers()->lockForUpdate()->findOrFail($subscriber->id);
            $subscription = $this->subscriptions->for($owner);

            if (! $subscription || ! $subscription->grantsEntitlements()) {
                throw ValidationException::withMessages([
                    'plan_limit' => __('An active subscription is required before creating a bot.'),
                ]);
            }

            $this->limits->ensureAllows(
                $subscription,
                'chatbots_limit',
                $owner->bots()->count(),
            );

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

            return $bot->fresh(['setting']);
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
