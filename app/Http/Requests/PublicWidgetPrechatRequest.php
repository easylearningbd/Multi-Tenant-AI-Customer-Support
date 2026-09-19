<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PublicWidgetPrechatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fields' => ['present', 'array', 'max:6'],
            'fields.*' => ['nullable', 'string', 'max:'.max(1, (int) config('neuraldesk.widgets.prechat_value_max', 1000))],
        ];
    }
}
