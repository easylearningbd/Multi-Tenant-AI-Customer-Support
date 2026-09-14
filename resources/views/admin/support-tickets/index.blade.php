@extends('admin.layouts.app')

@section('title', __('Support Tickets'))

@php
    $currentSort = $filters['sort'] ?? 'last_activity_at';
    $currentDirection = $filters['direction'] ?? 'desc';
    $sortUrl = fn (string $column) => request()->fullUrlWithQuery([
        'sort' => $column,
        'direction' => $currentSort === $column && $currentDirection === 'asc' ? 'desc' : 'asc',
        'page' => null,
    ]);
    $statusClass = fn ($status) => match ($status) {
        \App\Enums\SupportTicketStatus::RESOLVED => 'nd-status-success',
        \App\Enums\SupportTicketStatus::AWAITING_USER => 'nd-status-warning',
        \App\Enums\SupportTicketStatus::OPEN, \App\Enums\SupportTicketStatus::AWAITING_SUPPORT => 'nd-status-info',
        default => 'nd-status-muted',
    };
    $priorityClass = fn ($priority) => match ($priority) {
        \App\Enums\SupportTicketPriority::URGENT => 'nd-ticket-priority-urgent',
        \App\Enums\SupportTicketPriority::HIGH => 'nd-ticket-priority-high',
        \App\Enums\SupportTicketPriority::MEDIUM => 'nd-ticket-priority-medium',
        default => 'nd-ticket-priority-low',
    };
@endphp

@section('content')
    <div class="nd-page-heading">
        <h1>{{ __('Support Tickets') }}</h1>
        <p>{{ __('Review subscriber requests, reply securely, and manage ticket status.') }}</p>
    </div>

    <section class="card nd-card nd-users-card nd-admin-tickets-card" aria-labelledby="support-tickets-title">
        <div class="card-body">
            <h2 class="visually-hidden" id="support-tickets-title">{{ __('Subscriber support tickets') }}</h2>

            <form class="nd-ticket-toolbar" method="GET" action="{{ route('admin.support-tickets.index') }}" role="search">
                <div class="nd-users-search">
                    <i class="iconoir-search" aria-hidden="true"></i>
                    <label class="visually-hidden" for="ticket-search">{{ __('Search tickets') }}</label>
                    <input class="form-control" id="ticket-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="{{ __('Search reference, subject, requester, or category...') }}">
                </div>

                <div class="nd-ticket-filters">
                    <label class="visually-hidden" for="ticket-status">{{ __('Status') }}</label>
                    <select class="form-select" id="ticket-status" name="status" data-ticket-auto-submit>
                        <option value="">{{ __('All statuses') }}</option>
                        @foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->adminLabel() }}</option>@endforeach
                    </select>

                    <label class="visually-hidden" for="ticket-priority">{{ __('Priority') }}</label>
                    <select class="form-select" id="ticket-priority" name="priority" data-ticket-auto-submit>
                        <option value="">{{ __('All priorities') }}</option>
                        @foreach ($priorities as $priority)<option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>@endforeach
                    </select>

                    <label class="visually-hidden" for="ticket-visibility">{{ __('Visibility') }}</label>
                    <select class="form-select" id="ticket-visibility" name="visibility" data-ticket-auto-submit>
                        <option value="active" @selected(($filters['visibility'] ?? 'active') === 'active')>{{ __('Active') }}</option>
                        <option value="archived" @selected(($filters['visibility'] ?? '') === 'archived')>{{ __('Archived') }}</option>
                        <option value="all" @selected(($filters['visibility'] ?? '') === 'all')>{{ __('All tickets') }}</option>
                    </select>

                    <label for="tickets-per-page">{{ __('Show') }}</label>
                    <select class="form-select nd-ticket-page-size" id="tickets-per-page" name="per_page" data-ticket-auto-submit>
                        @foreach ([10, 15, 25, 50] as $size)<option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 15) === $size)>{{ $size }}</option>@endforeach
                    </select>
                    <span>{{ __('entries') }}</span>
                    <input name="sort" type="hidden" value="{{ $currentSort }}">
                    <input name="direction" type="hidden" value="{{ $currentDirection }}">
                    <button class="btn nd-btn-primary" type="submit"><i class="iconoir-search" aria-hidden="true"></i>{{ __('Apply') }}</button>
                    @if (collect($filters)->except(['sort', 'direction', 'per_page', 'visibility'])->filter()->isNotEmpty() || ($filters['visibility'] ?? 'active') !== 'active')<a class="btn nd-btn-secondary" href="{{ route('admin.support-tickets.index') }}">{{ __('Clear') }}</a>@endif
                </div>
            </form>

            @if ($tickets->isEmpty())
                <div class="nd-users-empty" role="status"><span aria-hidden="true"><i class="iconoir-chat-bubble-empty"></i></span><h3>{{ __('No support tickets found.') }}</h3><p>{{ __('Try changing the search or filter options.') }}</p></div>
            @else
                <div class="table-responsive nd-users-table-wrap">
                    <table class="table nd-users-table nd-admin-tickets-table align-middle">
                        <thead><tr>
                            @foreach (['reference' => __('Reference'), 'subject' => __('Subject')] as $column => $label)<th scope="col"><a class="nd-sort-link" href="{{ $sortUrl($column) }}">{{ $label }}<i class="iconoir-sort" aria-hidden="true"></i></a></th>@endforeach
                            <th scope="col">{{ __('User') }}</th>
                            @foreach (['priority' => __('Priority'), 'status' => __('Status'), 'last_activity_at' => __('Last activity')] as $column => $label)<th scope="col"><a class="nd-sort-link" href="{{ $sortUrl($column) }}">{{ $label }}<i class="iconoir-sort" aria-hidden="true"></i></a></th>@endforeach
                            <th class="text-end" scope="col">{{ __('Actions') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($tickets as $ticket)
                                <tr>
                                    <td data-label="{{ __('Reference') }}"><a class="nd-ticket-reference" href="{{ route('admin.support-tickets.show', $ticket) }}">{{ $ticket->reference }}</a></td>
                                    <td data-label="{{ __('Subject') }}"><a class="nd-ticket-subject" href="{{ route('admin.support-tickets.show', $ticket) }}">{{ $ticket->subject }}</a></td>
                                    <td data-label="{{ __('User') }}"><span class="nd-ticket-requester">{{ $ticket->requester?->name ?? __('Former subscriber') }}</span></td>
                                    <td data-label="{{ __('Priority') }}"><span class="nd-ticket-priority {{ $priorityClass($ticket->priority) }}">{{ $ticket->priority->label() }}</span></td>
                                    <td data-label="{{ __('Status') }}"><span class="nd-status-badge {{ $statusClass($ticket->status) }}">{{ $ticket->status->adminLabel() }}</span>@if ($ticket->archived_at)<span class="nd-status-badge nd-status-muted ms-1">{{ __('Archived') }}</span>@endif</td>
                                    <td class="nd-cell-muted" data-label="{{ __('Last activity') }}"><time datetime="{{ $ticket->last_activity_at->toIso8601String() }}">{{ $ticket->last_activity_at->format('d M, Y \a\t h:i A') }}</time></td>
                                    <td class="text-end" data-label="{{ __('Actions') }}"><div class="nd-row-actions"><a class="btn nd-icon-action" href="{{ route('admin.support-tickets.show', $ticket) }}" aria-label="{{ __('View :ticket', ['ticket' => $ticket->reference]) }}" title="{{ __('View') }}"><i class="iconoir-eye" aria-hidden="true"></i></a>@unless ($ticket->archived_at)<button class="btn nd-icon-action nd-icon-action-danger" type="button" data-bs-toggle="modal" data-bs-target="#archive-ticket-modal" data-archive-url="{{ route('admin.support-tickets.archive', $ticket) }}" data-archive-reference="{{ $ticket->reference }}" data-archive-subject="{{ $ticket->subject }}" aria-label="{{ __('Archive :ticket', ['ticket' => $ticket->reference]) }}" title="{{ __('Archive') }}"><i class="iconoir-archive" aria-hidden="true"></i></button>@endunless</div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($tickets->hasPages())<div class="nd-users-pagination"><p>{{ __('Showing :first-:last of :total tickets', ['first' => $tickets->firstItem(), 'last' => $tickets->lastItem(), 'total' => $tickets->total()]) }}</p>{{ $tickets->onEachSide(1)->links('pagination::bootstrap-5') }}</div>@endif
            @endif
        </div>
    </section>

    <div class="modal fade" id="archive-ticket-modal" tabindex="-1" aria-labelledby="archive-ticket-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content nd-confirm-modal">
            <div class="modal-header"><h2 class="modal-title fs-5" id="archive-ticket-title">{{ __('Archive ticket') }}</h2><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body"><p>{{ __('Archive') }} <strong data-archive-ticket-reference></strong>?</p><p class="mb-0 text-muted" data-archive-ticket-subject></p><p class="mt-2 mb-0 text-muted">{{ __('The conversation and attachments will be preserved, but the subscriber will no longer see this ticket.') }}</p></div>
            <div class="modal-footer"><button class="btn nd-btn-secondary" type="button" data-bs-dismiss="modal">{{ __('Cancel') }}</button><form method="POST" action="" data-archive-ticket-form data-ticket-submit-once>@csrf @method('DELETE')<button class="btn nd-btn-danger" type="submit" data-ticket-submit-button>{{ __('Archive ticket') }}</button></form></div>
        </div></div>
    </div>
@endsection

@push('scripts')<script src="{{ asset('js/admin-support-tickets.js') }}"></script>@endpush
