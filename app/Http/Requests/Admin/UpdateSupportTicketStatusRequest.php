<?php

namespace App\Http\Requests\Admin;

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

final class UpdateSupportTicketStatusRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'adminSupportStatus';

    public function authorize(): bool
    {
        $ticket = $this->route('adminTicket');

        return $ticket instanceof SupportTicket && Gate::allows('updateStatus', $ticket);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return ['status' => ['required', Rule::enum(SupportTicketStatus::class)]];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $ticket = $this->route('adminTicket');
            $status = is_string($this->status) ? SupportTicketStatus::tryFrom($this->status) : null;

            if ($ticket instanceof SupportTicket && $status && ! $ticket->status->canTransitionTo($status)) {
                $validator->errors()->add('status', __('This ticket status transition is not allowed.'));
            }
        }];
    }

    protected function getRedirectUrl(): string
    {
        $ticket = $this->route('adminTicket');

        return $ticket instanceof SupportTicket
            ? route('admin.support-tickets.show', $ticket)
            : route('admin.support-tickets.index');
    }
}
