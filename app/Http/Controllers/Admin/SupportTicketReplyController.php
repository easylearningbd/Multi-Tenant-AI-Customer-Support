<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SendSupportTicketReply;
use App\Exceptions\SupportTicketNotReplyable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSupportTicketReplyRequest;
use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Throwable;

final class SupportTicketReplyController extends Controller
{
    public function store(
        StoreSupportTicketReplyRequest $request,
        SupportTicket $adminTicket,
        SendSupportTicketReply $sendReply,
    ): RedirectResponse {
        try {
            $sendReply->handle(
                $adminTicket,
                $request->user(),
                $request->validated('message'),
                $request->file('attachments', []),
                $request->validated('submission_token'),
            );
        } catch (SupportTicketNotReplyable) {
            return redirect()->route('admin.support-tickets.show', $adminTicket)->with('toast', [
                'type' => 'warning',
                'title' => __('Reply blocked'),
                'message' => __('Resolved, closed, or archived tickets cannot receive replies.'),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.support-tickets.show', $adminTicket)
                ->withInput($request->safe()->except(['attachments', 'submission_token']))
                ->with('toast', [
                    'type' => 'error',
                    'title' => __('Reply failed'),
                    'message' => __('The reply could not be sent. Please try again.'),
                ]);
        }

        return redirect()->route('admin.support-tickets.show', $adminTicket)->with('toast', [
            'type' => 'success',
            'title' => __('Reply sent'),
            'message' => __('Reply sent successfully.'),
        ]);
    }
}
