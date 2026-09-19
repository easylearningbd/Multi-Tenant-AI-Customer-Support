<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BootstrapPublicWidgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['access_proof' => ['required', 'string', 'max:12000']];
    }
}
