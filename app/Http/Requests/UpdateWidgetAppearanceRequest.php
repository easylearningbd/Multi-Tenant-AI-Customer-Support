<?php

namespace App\Http\Requests;

use App\Enums\WidgetPosition;
use App\Models\Bot;
use App\Rules\WidgetOrigin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateWidgetAppearanceRequest extends FormRequest
{
    protected $errorBag = 'widgetAppearance';

    public function authorize(): bool
    {
        $bot = $this->route('subscriberBot');

        return $bot instanceof Bot && $this->user()?->can('manageEmbed', $bot) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'accent_color' => ['required', 'string', Rule::in(array_keys((array) config('neuraldesk.widgets.accent_colors', [])))],
            'position' => ['required', Rule::enum(WidgetPosition::class)],
            'welcome_message' => ['required', 'string', 'max:'.(int) config('neuraldesk.widgets.welcome_message_max', 500)],
            'allowed_origins' => ['sometimes', 'array', 'max:'.max(1, (int) config('neuraldesk.widgets.maximum_origins', 25))],
            'allowed_origins.*' => ['required', 'string', 'max:255', new WidgetOrigin],
        ];
    }

    protected function prepareForValidation(): void
    {
        $attributes = [
            'is_enabled' => $this->boolean('is_enabled'),
            'accent_color' => strtoupper(trim((string) $this->input('accent_color'))),
            'position' => trim((string) $this->input('position')),
            'welcome_message' => trim((string) $this->input('welcome_message')),
        ];

        if ($this->has('allowed_origins_text')) {
            $attributes['allowed_origins'] = collect(preg_split('/\R/u', (string) $this->input('allowed_origins_text')) ?: [])
                ->map(fn (string $origin): string => trim($origin))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $this->merge($attributes);
    }
}
