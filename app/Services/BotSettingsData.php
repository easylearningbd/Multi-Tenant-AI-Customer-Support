<?php

namespace App\Services;

use App\Enums\BotTone;
use App\Enums\PrechatFieldType;
use App\Models\Bot;

final class BotSettingsData
{
    public function __construct(private readonly BotDefaults $defaults) {}

    /** @return array<string, mixed> */
    public function for(Bot $bot): array
    {
        $bot->loadMissing(['setting', 'starterQuestions', 'prechatFields']);
        $settings = $bot->setting;
        $values = $settings ? [
            'welcome_message' => $settings->welcome_message,
            'prechat_enabled' => $settings->prechat_enabled,
            'tone' => $settings->tone->value,
            'primary_language' => $settings->primary_language,
            'persona' => $settings->persona,
            'fallback_message' => $settings->fallback_message,
            'offer_human_handoff' => $settings->offer_human_handoff,
            'answer_only_from_knowledge_base' => $settings->answer_only_from_knowledge_base,
            'model_override' => $settings->model_override,
            'temperature' => $settings->temperature,
            'max_output_tokens' => $settings->max_output_tokens,
            'kb_confidence' => $settings->kb_confidence,
        ] : $this->defaults->settings();

        return [
            'bot' => $bot,
            'settingValues' => $values,
            'starterQuestions' => $bot->starterQuestions->pluck('question')->all(),
            'prechatFields' => $bot->prechatFields->map(fn ($field): array => [
                'standard_key' => in_array($field->key, ['name', 'email', 'phone'], true) ? $field->key : null,
                'label' => $field->label,
                'type' => $field->type->value,
                'placeholder' => $field->placeholder,
                'is_required' => $field->is_required,
                'options_text' => implode(PHP_EOL, $field->options ?? []),
            ])->all(),
            'tones' => BotTone::cases(),
            'fieldTypes' => PrechatFieldType::cases(),
            'languages' => config('neuraldesk.bots.languages', []),
            'allowedModels' => config('neuraldesk.ai.allowed_chat_models', []),
        ];
    }
}
