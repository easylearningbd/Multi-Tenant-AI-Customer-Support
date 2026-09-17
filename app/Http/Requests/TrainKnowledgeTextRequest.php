<?php

namespace App\Http\Requests;

use App\Models\Bot;
use Illuminate\Foundation\Http\FormRequest;

final class TrainKnowledgeTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bot = $this->route('subscriberBot');

        return $bot instanceof Bot && $this->user()?->can('train', $bot) === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:180'],
            'text' => ['required', 'string', 'max:500000'],
        ];
    }
}
