<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PublicWidgetHandoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['conversation_uuid' => ['required', 'uuid']];
    }
}
