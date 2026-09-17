<?php

namespace App\Http\Requests;

use App\Models\Bot;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

final class StoreBotRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'createBot';

    public function authorize(): bool
    {
        return $this->user()?->can('create', Bot::class) === true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:'.config('neuraldesk.bots.limits.name'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? Str::squish($this->name) : $this->name,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('bots.index');
    }
}
