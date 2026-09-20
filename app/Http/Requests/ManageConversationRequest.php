<?php

namespace App\Http\Requests;

use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

final class ManageConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $conversation = $this->route('subscriberConversation');

        return $conversation instanceof Conversation && Gate::allows('changeStatus', $conversation);
    }

    public function rules(): array
    {
        return [];
    }
}
