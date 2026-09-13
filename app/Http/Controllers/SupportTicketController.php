<?php

namespace App\Http\Controllers;

use App\Actions\CreateSupportTicket;
use App\Enums\SupportTicketPriority;
use App\Enums\SupportTicketStatus;
use App\Http\Requests\ListSupportTicketsRequest;
use App\Http\Requests\StoreSupportTicketRequest;
use App\Models\SupportTicket;
use App\Services\SupportTicketIndexQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

final class SupportTicketController extends Controller
{
    public function index(ListSupportTicketsRequest $request, SupportTicketIndexQuery $query): View
    {
        Gate::authorize('viewAny', SupportTicket::class);

        return view('support-tickets.index', [
            'tickets' => $query->paginate($request),
            'filters' => $request->validated(),
            'priorities' => SupportTicketPriority::cases(),
            'statuses' => SupportTicketStatus::cases(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', SupportTicket::class);

        return view('support-tickets.create', [
            'priorities' => SupportTicketPriority::cases(),
        ]);
    }

    public function store(StoreSupportTicketRequest $request, CreateSupportTicket $createTicket): RedirectResponse
    {
        $attributes = $request->safe()->only(['subject', 'priority', 'category', 'message']);

        try {
            $ticket = $createTicket->handle(
                $request->user(),
                $attributes,
                $request->file('attachments', []),
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('support-tickets.create')
                ->withInput($request->safe()->except('attachments'))
                ->with('toast', [
                    'type' => 'error',
                    'title' => __('Ticket creation failed'),
                    'message' => __('Your ticket could not be created. Please try again.'),
                ]);
        }

        return redirect()->route('support-tickets.show', $ticket)->with('toast', [
            'type' => 'success',
            'title' => __('Ticket submitted'),
            'message' => __('Ticket created successfully.'),
        ]);
    }

    public function show(SupportTicket $subscriberTicket): View
    {
        Gate::authorize('view', $subscriberTicket);

        $messages = $subscriberTicket->messages()
            ->with([
                'sender:id,name,role,avatar_path',
                'attachments:id,support_ticket_message_id,original_name,size',
            ])
            ->latest('created_at')
            ->latest('id')
            ->paginate(50, pageName: 'messages');

        return view('support-tickets.show', [
            'ticket' => $subscriberTicket,
            'messages' => $messages,
        ]);
    }
}
