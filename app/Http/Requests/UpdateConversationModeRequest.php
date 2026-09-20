<?php

namespace App\Http\Requests;

use App\Enums\ConversationHandlingMode;
use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class UpdateConversationModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('subscriberConversation');

        return $conversation instanceof Conversation && Gate::allows('changeMode', $conversation);
    }

    public function rules(): array
    {
        return ['mode' => ['required', Rule::enum(ConversationHandlingMode::class)]];
    }
}
