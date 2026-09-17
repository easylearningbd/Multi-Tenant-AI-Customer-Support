<?php

namespace App\Actions;

use App\Models\Bot;
use App\Models\User;
use App\Services\BotConfigurationService;
use App\Services\BotDefaults;
use Illuminate\Support\Facades\DB;

final class UpdateBotConfiguration
{
    public function __construct(
        private readonly BotDefaults $defaults,
        private readonly BotConfigurationService $configuration,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function handle(User $owner, Bot $bot, array $attributes): Bot
    {
        return DB::transaction(function () use ($owner, $bot, $attributes): Bot {
            $lockedBot = Bot::query()
                ->ownedBy($owner)
                ->lockForUpdate()
                ->findOrFail($bot->id);

            $lockedBot->update([
                'name' => $attributes['name'],
                'display_name' => $this->defaults->displayName($attributes['name'], $attributes['display_name']),
                'is_active' => $attributes['is_active'],
            ]);

            $settings = $this->configuration->validateSettings(collect($attributes)->only([
                'welcome_message',
                'prechat_enabled',
                'tone',
                'primary_language',
                'persona',
                'fallback_message',
                'offer_human_handoff',
                'answer_only_from_knowledge_base',
                'model_override',
                'temperature',
                'max_output_tokens',
                'kb_confidence',
            ])->all());

            $lockedBot->setting()->updateOrCreate([], $settings);
            $this->configuration->replaceStarterQuestions($lockedBot, $attributes['starter_questions']);
            $this->configuration->replacePrechatFields($lockedBot, $attributes['prechat_fields']);

            return $lockedBot->fresh(['setting', 'starterQuestions', 'prechatFields']);
        }, 3);
    }
}
