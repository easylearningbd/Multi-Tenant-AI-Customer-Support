<?php

namespace App\Services;

use App\Enums\BotTone;
use App\Enums\PrechatFieldType;
use Illuminate\Validation\Rule;

final class BotConfigurationRules
{
    /** @return array<string, list<mixed>> */
    public static function identity(): array
    {
        $limits = config('neuraldesk.bots.limits');

        return [
            'name' => ['required', 'string', 'max:'.$limits['name']],
            'display_name' => ['nullable', 'string', 'max:'.$limits['display_name']],
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function settings(): array
    {
        $limits = config('neuraldesk.bots.limits');

        return [
            'welcome_message' => ['required', 'string', 'max:'.$limits['welcome_message']],
            'prechat_enabled' => ['required', 'boolean'],
            'tone' => ['required', Rule::enum(BotTone::class)],
            'primary_language' => [
                'required',
                'string',
                Rule::in(array_keys(config('neuraldesk.bots.languages', []))),
            ],
            'persona' => ['required', 'string', 'max:'.$limits['persona']],
            'fallback_message' => ['required', 'string', 'max:'.$limits['fallback_message']],
            'offer_human_handoff' => ['required', 'boolean'],
            'answer_only_from_knowledge_base' => ['required', 'boolean'],
            'model_override' => [
                'nullable',
                'string',
                'max:100',
                Rule::in(config('neuraldesk.ai.allowed_chat_models', [])),
            ],
            'temperature' => [
                'required',
                'decimal:0,2',
                'between:'.$limits['temperature_min'].','.$limits['temperature_max'],
            ],
            'max_output_tokens' => [
                'required',
                'integer',
                'between:'.$limits['max_output_tokens_min'].','.$limits['max_output_tokens_max'],
            ],
            'kb_confidence' => [
                'required',
                'decimal:0,3',
                'between:'.$limits['kb_confidence_min'].','.$limits['kb_confidence_max'],
            ],
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function starterQuestions(string $root = 'questions'): array
    {
        $limits = config('neuraldesk.bots.limits');

        return [
            $root => ['present', 'array', 'max:'.$limits['starter_questions']],
            $root.'.*' => ['required', 'string', 'distinct:strict', 'max:'.$limits['starter_question']],
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function prechatFields(string $root = 'fields'): array
    {
        $limits = config('neuraldesk.bots.limits');

        return [
            $root => ['present', 'array', 'max:'.$limits['prechat_fields']],
            $root.'.*.standard_key' => ['nullable', 'string', 'distinct:strict', Rule::in(['name', 'email', 'phone'])],
            $root.'.*.label' => ['required', 'string', 'max:100'],
            $root.'.*.type' => ['required', Rule::enum(PrechatFieldType::class)],
            $root.'.*.placeholder' => ['nullable', 'string', 'max:255'],
            $root.'.*.is_required' => ['required', 'boolean'],
            $root.'.*.options' => ['nullable', 'array', 'max:'.$limits['prechat_options']],
            $root.'.*.options.*' => ['required', 'string', 'distinct:strict', 'max:'.$limits['prechat_option']],
        ];
    }
}
