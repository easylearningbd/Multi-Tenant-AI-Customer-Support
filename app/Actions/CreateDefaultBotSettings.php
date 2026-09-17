<?php

namespace App\Actions;

use App\Models\Bot;
use App\Models\BotSetting;
use App\Services\BotConfigurationService;
use App\Services\BotDefaults;
use Illuminate\Support\Facades\DB;

final class CreateDefaultBotSettings
{
    public function __construct(
        private readonly BotDefaults $defaults,
        private readonly BotConfigurationService $configuration,
    ) {}

    public function handle(Bot $bot): BotSetting
    {
        $settings = $this->configuration->validateSettings($this->defaults->settings());

        return DB::transaction(function () use ($bot, $settings): BotSetting {
            $lockedBot = Bot::query()->lockForUpdate()->findOrFail($bot->id);

            return $lockedBot->setting()->firstOrCreate([], $settings);
        }, 3);
    }
}
