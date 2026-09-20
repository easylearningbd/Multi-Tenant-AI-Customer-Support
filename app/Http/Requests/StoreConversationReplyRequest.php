<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use App\Rules\SafeConversationAttachment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

final class StoreConversationReplyRequest extends FormRequest
{
    protected $errorBag = 'conversationReply';

    public function authorize(): bool
    {
        $conversation = $this->route('subscriberConversation');

        return $conversation instanceof Conversation && Gate::allows('reply', $conversation);
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
            'message' => ['nullable', 'required_without:attachments', 'string', 'max:'.max(1, (int) config('neuraldesk.rag.message_max_length', 4000))],
            'idempotency_key' => ['required', 'uuid'],
            'attachments' => ['nullable', 'array', 'max:'.max(1, (int) config('neuraldesk.conversations.maximum_attachments', 5))],
            'attachments.*' => [
                'file',
                File::types(array_keys((array) config('neuraldesk.conversations.allowed_attachments', [])))
                    ->max(max(1, (int) config('neuraldesk.conversations.attachment_max_kb', 10240))),
                new SafeConversationAttachment,
            ],
        ];
    }
}
