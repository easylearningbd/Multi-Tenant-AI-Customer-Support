<?php

namespace App\Http\Requests;

use App\Models\SupportTicket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Gate;

final class StoreSupportTicketReplyRequest extends SupportMessageRequest
{
    /** @var string */
    protected $errorBag = 'supportReply';

    public function authorize(): bool
    {
        $ticket = $this->route('subscriberTicket');

        return $ticket instanceof SupportTicket && Gate::allows('view', $ticket);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'message' => $this->messageRules(),
            ...$this->attachmentRules(),
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $ticket = $this->route('subscriberTicket');

                if ($ticket instanceof SupportTicket && ! Gate::allows('reply', $ticket)) {
                    $validator->errors()->add('message', __('This ticket is closed. Open a new ticket if you still need help.'));
                }
            },
        ];
    }

    protected function getRedirectUrl(): string
    {
        $ticket = $this->route('subscriberTicket');

        return $ticket instanceof SupportTicket
            ? route('support-tickets.show', $ticket)
            : route('support-tickets.index');
    }
}
