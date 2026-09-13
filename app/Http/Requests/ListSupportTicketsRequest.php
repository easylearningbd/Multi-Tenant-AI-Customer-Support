<?php

namespace App\Http\Requests;

use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListSupportTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::USER;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(SupportTicketStatus::class)],
            'priority' => ['nullable', Rule::enum(SupportTicketPriority::class)],
            'sort' => ['nullable', Rule::in(['reference', 'subject', 'priority', 'status', 'last_activity_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', Rule::in([10, 15, 25, 50])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $sorts = ['reference', 'subject', 'priority', 'status', 'last_activity_at'];
        $directions = ['asc', 'desc'];
        $pageSizes = [10, 15, 25, 50];
        $statuses = array_column(SupportTicketStatus::cases(), 'value');
        $priorities = array_column(SupportTicketPriority::cases(), 'value');

        $this->merge([
            'search' => is_string($this->search) && trim($this->search) !== '' ? trim($this->search) : null,
            'status' => is_string($this->status) && in_array($this->status, $statuses, true) ? $this->status : null,
            'priority' => is_string($this->priority) && in_array($this->priority, $priorities, true) ? $this->priority : null,
            'sort' => is_string($this->sort) && in_array($this->sort, $sorts, true) ? $this->sort : 'last_activity_at',
            'direction' => is_string($this->direction) && in_array($this->direction, $directions, true) ? $this->direction : 'desc',
            'per_page' => in_array((int) $this->per_page, $pageSizes, true) ? (int) $this->per_page : 15,
        ]);
    }
}
