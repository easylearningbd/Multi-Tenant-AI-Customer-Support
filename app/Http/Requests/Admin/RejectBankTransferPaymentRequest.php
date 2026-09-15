<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class RejectBankTransferPaymentRequest extends FormRequest
{
    protected $errorBag = 'paymentRejection';

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ADMIN;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'confirmation' => ['accepted'],
            'rejection_reason' => ['required', 'string', 'min:3', 'max:2000'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['rejection_reason', 'review_note'] as $field) {
            $value = $this->input($field);
            $this->merge([$field => is_string($value) && trim($value) !== '' ? trim($value) : null]);
        }
    }
}
