<?php

namespace App\Http\Requests;

use App\Enums\PrechatFieldType;
use App\Models\Bot;
use App\Services\BotConfigurationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

final class UpdateBotSettingsRequest extends FormRequest
{
    protected $errorBag = 'botSettings';

    public function authorize(): bool
    {
        $bot = $this->route('subscriberBot');

        return $bot instanceof Bot && $this->user()?->can('update', $bot) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return array_merge(
            BotConfigurationRules::identity(),
            BotConfigurationRules::settings(),
            BotConfigurationRules::starterQuestions('starter_questions'),
            BotConfigurationRules::prechatFields('prechat_fields'),
            ['is_active' => ['required', 'boolean']],
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach ($this->input('prechat_fields', []) as $index => $field) {
                if (($field['type'] ?? null) === PrechatFieldType::SELECT->value
                    && empty($field['options'] ?? [])) {
                    $validator->errors()->add(
                        "prechat_fields.{$index}.options",
                        __('A select field requires at least one option.'),
                    );
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $questions = collect($this->input('starter_questions', []))
            ->map(fn (mixed $question): string => Str::squish((string) $question))
            ->filter()
            ->values()
            ->all();

        $fields = collect($this->input('prechat_fields', []))
            ->map(function (mixed $field): array {
                $field = is_array($field) ? $field : [];
                $options = preg_split('/\R/u', (string) ($field['options_text'] ?? '')) ?: [];

                return [
                    'standard_key' => $this->nullableString($field['standard_key'] ?? null),
                    'label' => Str::squish((string) ($field['label'] ?? '')),
                    'type' => (string) ($field['type'] ?? PrechatFieldType::TEXT->value),
                    'placeholder' => $this->nullableString($field['placeholder'] ?? null),
                    'is_required' => filter_var($field['is_required'] ?? false, FILTER_VALIDATE_BOOL),
                    'options' => collect($options)
                        ->map(fn (string $option): string => Str::squish($option))
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                ];
            })
            ->filter(fn (array $field): bool => $field['label'] !== '' || $field['standard_key'] !== null)
            ->values()
            ->all();

        $this->merge([
            'name' => Str::squish((string) $this->input('name')),
            'display_name' => $this->nullableString($this->input('display_name')),
            'welcome_message' => trim((string) $this->input('welcome_message')),
            'prechat_enabled' => $this->boolean('prechat_enabled'),
            'primary_language' => trim((string) $this->input('primary_language')),
            'persona' => trim((string) $this->input('persona')),
            'fallback_message' => trim((string) $this->input('fallback_message')),
            'offer_human_handoff' => $this->boolean('offer_human_handoff'),
            'answer_only_from_knowledge_base' => $this->boolean('answer_only_from_knowledge_base'),
            'model_override' => $this->nullableString($this->input('model_override')),
            'is_active' => $this->boolean('is_active'),
            'starter_questions' => $questions,
            'prechat_fields' => $fields,
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
