<?php

namespace App\Http\Requests\Admin;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListPaymentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::ADMIN;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(PaymentStatus::class)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['reference', 'expected_amount_minor', 'payment_method', 'status', 'submitted_at', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 25, 50])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $sorts = ['reference', 'expected_amount_minor', 'payment_method', 'status', 'submitted_at', 'created_at'];
        $directions = ['asc', 'desc'];
        $pageSizes = [10, 15, 25, 50];
        $statuses = array_column(PaymentStatus::cases(), 'value');
        $methods = array_column(PaymentMethod::cases(), 'value');

        $this->merge([
            'search' => is_string($this->search) && trim($this->search) !== '' ? trim($this->search) : null,
            'status' => is_string($this->status) && in_array($this->status, $statuses, true) ? $this->status : null,
            'payment_method' => is_string($this->payment_method) && in_array($this->payment_method, $methods, true) ? $this->payment_method : null,
            'plan_id' => filter_var($this->plan_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null,
            'date_from' => is_string($this->date_from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date_from) ? $this->date_from : null,
            'date_to' => is_string($this->date_to) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date_to) ? $this->date_to : null,
            'sort' => is_string($this->sort) && in_array($this->sort, $sorts, true) ? $this->sort : 'submitted_at',
            'direction' => is_string($this->direction) && in_array($this->direction, $directions, true) ? $this->direction : 'desc',
            'per_page' => in_array((int) $this->per_page, $pageSizes, true) ? (int) $this->per_page : 15,
        ]);
    }
}
