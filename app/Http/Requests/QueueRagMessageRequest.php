<?php

namespace App\Http\Requests;

use App\Models\Bot;
use Illuminate\Foundation\Http\FormRequest;

final class QueueRagMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bot = $this->route('subscriberBot');

        return $bot instanceof Bot && $this->user()?->can('view', $bot) === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('message'))) {
            $this->merge(['message' => trim($this->input('message'))]);
        }
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:'.max(1, (int) config('neuraldesk.rag.message_max_length', 4000))],
            'idempotency_key' => ['required', 'uuid'],
            'conversation_uuid' => ['nullable', 'uuid'],
        ];
    }
}
