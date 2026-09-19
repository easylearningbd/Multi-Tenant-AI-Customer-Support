<?php

namespace App\Actions;

use App\Enums\PrechatFieldType;
use App\Models\VisitorSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveVisitorPrechat
{
    /** @param array<string, mixed> $values */
    public function handle(VisitorSession $session, array $values): VisitorSession
    {
        $bot = $session->bot()->with(['setting', 'prechatFields'])->firstOrFail();
        abort_unless($bot->user_id === $session->user_id, 404);

        $fields = $bot->setting?->prechat_enabled ? $bot->prechatFields : collect();
        $allowedKeys = $fields->pluck('key')->all();
        $unexpected = array_diff(array_keys($values), $allowedKeys);
        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'fields' => __('The pre-chat form contains an unknown field.'),
            ]);
        }

        $rules = [];
        foreach ($fields as $field) {
            $base = $field->is_required ? ['required'] : ['nullable'];
            $typeRules = match ($field->type) {
                PrechatFieldType::EMAIL => ['string', 'email:rfc', 'max:255'],
                PrechatFieldType::PHONE => ['string', 'max:40', 'regex:/^\+?[0-9][0-9\s().-]{5,39}$/'],
                PrechatFieldType::SELECT => ['string', Rule::in($field->options ?? [])],
                PrechatFieldType::TEXTAREA => ['string', 'max:'.max(1, (int) config('neuraldesk.widgets.prechat_value_max', 1000))],
                default => ['string', 'max:255'],
            };
            $rules['fields.'.$field->key] = [...$base, ...$typeRules];
        }

        $validated = Validator::make(['fields' => $values], $rules)->validate()['fields'] ?? [];
        $clean = collect($validated)->map(fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)->all();

        return DB::transaction(function () use ($session, $clean): VisitorSession {
            $locked = VisitorSession::query()->whereKey($session->id)->where('user_id', $session->user_id)
                ->where('bot_id', $session->bot_id)->where('widget_id', $session->widget_id)->active()->lockForUpdate()->firstOrFail();
            $locked->forceFill(['prechat_data' => $clean, 'prechat_completed_at' => now('UTC')])->save();

            return $locked;
        }, 3);
    }
}
