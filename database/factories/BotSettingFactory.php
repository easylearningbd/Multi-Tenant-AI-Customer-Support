<?php

namespace Database\Factories;

use App\Enums\BotTone;
use App\Models\Bot;
use App\Models\BotSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BotSetting> */
final class BotSettingFactory extends Factory
{
    protected $model = BotSetting::class;

    public function definition(): array
    {
        return [
            'bot_id' => Bot::factory(),
            'welcome_message' => 'How can we help you today?',
            'prechat_enabled' => false,
            'tone' => BotTone::FRIENDLY,
            'primary_language' => 'en',
            'persona' => 'A helpful customer support assistant.',
            'fallback_message' => 'I could not find a reliable answer in the available knowledge.',
            'offer_human_handoff' => true,
            'answer_only_from_knowledge_base' => true,
            'model_override' => null,
            'temperature' => '0.30',
            'max_output_tokens' => 600,
            'kb_confidence' => '0.650',
        ];
    }
}
