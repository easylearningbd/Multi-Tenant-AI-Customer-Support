<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

final class UpdateAdminPasswordRequest extends FormRequest
{
    /**
     * The error bag used for password validation failures.
     *
     * @var string
     */
    protected $errorBag = 'passwordUpdate';

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ADMIN;
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
        return route('admin.profile.edit').'#change-password';
    }
}
