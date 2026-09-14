<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\SupportMessageRequest;
use App\Models\SupportTicket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;

final class StoreSupportTicketReplyRequest extends SupportMessageRequest
{
    /** @var string */
    protected $errorBag = 'adminSupportReply';

    public function authorize(): bool
    {
        $ticket = $this->route('adminTicket');

        return $ticket instanceof SupportTicket && Gate::allows('manage', $ticket);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'message' => $this->messageRules(),
            'submission_token' => ['required', 'uuid'],
            ...$this->attachmentRules(),
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            $ticket = $this->route('adminTicket');

            if ($ticket instanceof SupportTicket && ! Gate::allows('replyAsAdmin', $ticket)) {
                $validator->errors()->add('message', __('Resolved, closed, or archived tickets cannot receive replies.'));
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
