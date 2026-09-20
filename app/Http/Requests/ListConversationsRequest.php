<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class ListConversationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('viewAny', Conversation::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'filter' => $this->input('filter', 'all'),
            'q' => is_string($this->input('q')) ? trim($this->input('q')) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'filter' => ['required', Rule::in(['all', 'open', 'manual', 'resolved', 'archived'])],
            'q' => ['nullable', 'string', 'max:200'],
            'conversation' => ['nullable', 'uuid'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
