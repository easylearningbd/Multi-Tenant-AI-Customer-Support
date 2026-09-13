<?php

namespace App\Http\Controllers;

use App\Actions\ReplyToSupportTicket;
use App\Exceptions\SupportTicketNotReplyable;
use App\Http\Requests\StoreSupportTicketReplyRequest;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Throwable;

final class SupportTicketReplyController extends Controller
{
    public function store(
        StoreSupportTicketReplyRequest $request,
        SupportTicket $subscriberTicket,
        ReplyToSupportTicket $replyToTicket,
    ): RedirectResponse {
        try {
            $replyToTicket->handle(
                $subscriberTicket,
                $request->user(),
                $request->validated('message'),
                $request->file('attachments', []),
            );
        } catch (SupportTicketNotReplyable) {
            return redirect()->route('support-tickets.show', $subscriberTicket)->with('toast', [
                'type' => 'warning',
                'title' => __('Reply blocked'),
                'message' => __('This ticket is closed. Open a new ticket if you still need help.'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('support-tickets.show', $subscriberTicket)
                ->withInput($request->safe()->except('attachments'))
                ->with('toast', [
                    'type' => 'error',
                    'title' => __('Reply failed'),
                    'message' => __('Your reply could not be sent. Please try again.'),
                ]);
        }

        return redirect()->route('support-tickets.show', $subscriberTicket)->with('toast', [
            'type' => 'success',
            'title' => __('Reply sent'),
            'message' => __('Reply sent successfully.'),
        ]);
    }
}
