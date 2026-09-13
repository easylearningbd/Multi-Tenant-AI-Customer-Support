<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Rules\SafePaymentProof;
use App\Services\DecimalMoney;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

final class StoreBankTransferPaymentRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'bankTransfer';

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::USER
            && $this->user()->isBillingOwner();
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $futureTolerance = max(0, (int) config('billing.bank_transfer.future_transfer_tolerance_minutes'));

        return [
            'payer_name' => ['required', 'string', 'max:255'],
            'payer_bank_name' => ['required', 'string', 'max:255'],
            'transaction_reference' => ['required', 'string', 'max:191'],
            'transferred_at' => ['required', 'date', 'before_or_equal:'.now()->addMinutes($futureTolerance)->toDateTimeString()],
            'submitted_amount' => ['required', 'string', 'regex:/^\d{1,8}(?:\.\d{1,2})?$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'confirmation' => ['accepted'],
            'payment_proof' => [
                'required',
                File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp'])
                    ->max(max(1, (int) config('billing.bank_transfer.proofs.max_kilobytes'))),
                new SafePaymentProof,
            ],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('submitted_amount')) {
                return;
            }

            try {
                if (DecimalMoney::toMinor((string) $this->input('submitted_amount')) < 1) {
                    $validator->errors()->add('submitted_amount', __('The submitted amount must be greater than zero.'));
                }
            } catch (InvalidArgumentException) {
                $validator->errors()->add('submitted_amount', __('The submitted amount must be a valid monetary value.'));
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payer_name' => is_string($this->payer_name) ? trim($this->payer_name) : $this->payer_name,
            'payer_bank_name' => is_string($this->payer_bank_name) ? trim($this->payer_bank_name) : $this->payer_bank_name,
            'transaction_reference' => is_string($this->transaction_reference) ? trim($this->transaction_reference) : $this->transaction_reference,
            'submitted_amount' => is_string($this->submitted_amount) ? trim($this->submitted_amount) : $this->submitted_amount,
            'notes' => is_string($this->notes) && trim($this->notes) !== '' ? trim($this->notes) : null,
        ]);
    }
}
