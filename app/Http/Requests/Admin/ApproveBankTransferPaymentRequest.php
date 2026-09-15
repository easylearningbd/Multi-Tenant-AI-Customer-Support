<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ApproveBankTransferPaymentRequest extends FormRequest
{
    protected $errorBag = 'paymentApproval';

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ADMIN;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'confirmation' => ['accepted'],
            'amount_mismatch_acknowledged' => ['nullable', 'boolean'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'review_note' => is_string($this->review_note) && trim($this->review_note) !== '' ? trim($this->review_note) : null,
        ]);
    }
}
