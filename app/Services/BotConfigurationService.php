<?php

namespace App\Services;

use App\Enums\PrechatFieldType;
use App\Models\Bot;
use App\Models\BotPrechatField;
use App\Models\BotStarterQuestion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class BotConfigurationService
{
    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function validateSettings(array $settings): array
    {
        return Validator::make($settings, BotConfigurationRules::settings())->validate();
    }

    /**
     * @param  list<string>  $questions
     * @return Collection<int, BotStarterQuestion>
     */
    public function replaceStarterQuestions(Bot $bot, array $questions): Collection
    {
        $validated = Validator::make(
            ['questions' => $questions],
            BotConfigurationRules::starterQuestions(),
        )->validate();

        return DB::transaction(function () use ($bot, $validated): Collection {
            $lockedBot = Bot::query()->lockForUpdate()->findOrFail($bot->id);
            $lockedBot->starterQuestions()->delete();

            foreach ($validated['questions'] as $index => $question) {
                $lockedBot->starterQuestions()->create([
                    'question' => Str::squish($question),
                    'position' => $index + 1,
                ]);
            }

            return $lockedBot->starterQuestions()->get();
        });
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return Collection<int, BotPrechatField>
     */
    public function replacePrechatFields(Bot $bot, array $fields): Collection
    {
        $validated = Validator::make(
            ['fields' => $fields],
            BotConfigurationRules::prechatFields(),
        )->validate();

        foreach ($validated['fields'] as $index => $field) {
            if (empty($field['standard_key'])
                && $field['type'] === PrechatFieldType::SELECT->value
                && empty(array_filter($field['options'] ?? [], fn (mixed $option): bool => trim((string) $option) !== ''))) {
                throw ValidationException::withMessages([
                    "fields.{$index}.options" => __('A select field requires at least one option.'),
                ]);
            }
        }

        return DB::transaction(function () use ($bot, $validated): Collection {
            $lockedBot = Bot::query()->lockForUpdate()->findOrFail($bot->id);
            $lockedBot->prechatFields()->delete();
            $usedKeys = [];

            foreach ($validated['fields'] as $index => $field) {
                $standardKey = $field['standard_key'] ?? null;
                $type = $standardKey
                    ? $this->standardFieldType($standardKey)
                    : PrechatFieldType::from($field['type']);
                $key = $standardKey ?: $this->uniqueCustomKey($field['label'], $usedKeys);
                $usedKeys[] = $key;

                $lockedBot->prechatFields()->create([
                    'key' => $key,
                    'label' => Str::squish($field['label']),
                    'type' => $type,
                    'placeholder' => $this->nullableSquished($field['placeholder'] ?? null),
                    'is_required' => (bool) $field['is_required'],
                    'position' => $index + 1,
                    'options' => $type === PrechatFieldType::SELECT
                        ? $this->cleanOptions($field['options'] ?? [])
                        : null,
                ]);
            }

            return $lockedBot->prechatFields()->get();
        });
    }

    /** @param list<string> $usedKeys */
    private function uniqueCustomKey(string $label, array $usedKeys): string
    {
        $base = Str::of($label)->ascii()->snake()->limit(50, '')->toString();
        $base = 'custom_'.($base !== '' ? $base : 'field');
        $key = $base;
        $suffix = 2;

        while (in_array($key, $usedKeys, true)) {
            $key = Str::limit($base, 60, '').'_'.$suffix;
            $suffix++;
        }

        return $key;
    }

    private function standardFieldType(string $key): PrechatFieldType
    {
        return match ($key) {
            'email' => PrechatFieldType::EMAIL,
            'phone' => PrechatFieldType::PHONE,
            default => PrechatFieldType::TEXT,
        };
    }

    /**
     * @param  list<string>  $options
     * @return list<string>
     */
    private function cleanOptions(array $options): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (string $option): string => Str::squish($option), $options),
            fn (string $option): bool => $option !== '',
        )));
    }

    private function nullableSquished(?string $value): ?string
    {
        $value = Str::squish((string) $value);

        return $value !== '' ? $value : null;
    }
}
