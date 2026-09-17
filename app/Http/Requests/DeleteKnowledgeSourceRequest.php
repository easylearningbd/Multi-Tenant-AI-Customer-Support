<?php

namespace App\Http\Requests;

use App\Models\KnowledgeSource;
use Illuminate\Foundation\Http\FormRequest;

final class DeleteKnowledgeSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $source = $this->route('subscriberSource');

        return $source instanceof KnowledgeSource && $this->user()?->can('delete', $source) === true;
    }

    public function rules(): array
    {
        return [];
    }
}
