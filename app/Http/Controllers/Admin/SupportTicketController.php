<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ArchiveSupportTicket;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListSupportTicketsRequest;
use App\Models\SupportTicket;
use App\Services\Admin\SupportTicketIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

final class SupportTicketController extends Controller
{
    public function index(ListSupportTicketsRequest $request, SupportTicketIndexQuery $query): View
    {
        Gate::authorize('manageAny', SupportTicket::class);

        return view('admin.support-tickets.index', [
            'tickets' => $query->paginate($request),
            'filters' => $request->validated(),
            'priorities' => SupportTicketPriority::cases(),
            'statuses' => SupportTicketStatus::cases(),
        ]);
    }

    public function show(Request $request, SupportTicket $adminTicket): View
    {
        Gate::authorize('manage', $adminTicket);

        $adminTicket->load('requester:id,name,email,avatar_path');
        $messages = $adminTicket->messages()
            ->with([
                'sender:id,name,role,avatar_path',
                'attachments:id,support_ticket_message_id,original_name,size',
            ])
            ->latest('created_at')
            ->latest('id')
            ->paginate(50, pageName: 'messages');

        $request->user()->unreadNotifications()
            ->where('data->ticket_id', $adminTicket->id)
            ->update(['read_at' => now()]);

        return view('admin.support-tickets.show', [
            'ticket' => $adminTicket,
            'messages' => $messages,
            'statuses' => SupportTicketStatus::cases(),
        ]);
    }

    public function archive(SupportTicket $adminTicket, ArchiveSupportTicket $archiveTicket): RedirectResponse
    {
        Gate::authorize('archive', $adminTicket);

        try {
            $archiveTicket->handle($adminTicket);
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('admin.support-tickets.index')->with('toast', [
                'type' => 'error',
                'title' => __('Archive failed'),
                'message' => __('The ticket could not be archived. Please try again.'),
            ]);
        }

        return redirect()->route('admin.support-tickets.index')->with('toast', [
            'type' => 'success',
            'title' => __('Ticket archived'),
            'message' => __('Ticket archived successfully.'),
        ]);
    }
}
