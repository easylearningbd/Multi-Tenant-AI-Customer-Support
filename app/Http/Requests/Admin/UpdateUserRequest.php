<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class UpdateUserRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'userUpdate';

    public function authorize(): bool
    {
        $subscriber = $this->route('subscriber');

        return $subscriber instanceof User
            && $this->user()?->can('update', $subscriber) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $subscriber */
        $subscriber = $this->route('subscriber');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($subscriber->id),
            ],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^(?=(?:\D*\d){7,})\+?[0-9\s().-]+$/'],
            'avatar' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.config('admin.profile.avatar_max_kilobytes'),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->name) ? trim($this->name) : $this->name,
            'email' => is_string($this->email) ? Str::lower(trim($this->email)) : $this->email,
            'phone' => is_string($this->phone) && trim($this->phone) !== '' ? trim($this->phone) : null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.users.edit', $this->route('subscriber'));
    }
}
