<?php

namespace App\Http\Requests;

use App\Enums\SupportTicketPriority;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class StoreSupportTicketRequest extends SupportMessageRequest
{
    /** @var string */
    protected $errorBag = 'supportTicket';

    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::USER;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:5', 'max:200'],
            'priority' => ['required', Rule::enum(SupportTicketPriority::class)],
            'category' => ['nullable', 'string', 'max:100'],
            'message' => $this->messageRules(10),
            ...$this->attachmentRules(),
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'subject' => is_string($this->subject) ? trim($this->subject) : $this->subject,
            'category' => is_string($this->category) && trim($this->category) !== ''
                ? Str::squish($this->category)
                : null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('support-tickets.create');
    }
}
