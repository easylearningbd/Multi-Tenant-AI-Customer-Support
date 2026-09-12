<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class UpdateSubscriberPasswordRequest extends FormRequest
{
    /**
     * @var string
     */
    protected $errorBag = 'updatePassword';

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::USER;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password:web'],
            'password' => [
                'required',
                Password::defaults(),
                'confirmed',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && Hash::check($value, $this->user()->password)) {
                        $fail(__('The new password must be different from your current password.'));
                    }
                },
            ],
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('profile.edit');
    }
}
