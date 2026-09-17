<?php

namespace App\Http\Requests;

use App\Models\Bot;
use App\Models\KnowledgeSource;
use Illuminate\Foundation\Http\FormRequest;

final class RetrainKnowledgeSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $bot = $this->route('subscriberBot');
        $source = $this->route('subscriberSource');

        return $bot instanceof Bot && $source instanceof KnowledgeSource
            && $this->user()?->can('train', $bot) === true
            && $this->user()?->can('update', $source) === true;
    }

    public function rules(): array
    {
        return [];
    }
}
