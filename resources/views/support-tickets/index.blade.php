@extends('subscriber.layouts.app')

@section('title', __('Support'))
@section('header-title', __('Settings'))
@section('header-subtitle', __('Your account details and security.'))

@section('content')
    @php
        $sortUrl = fn (string $column) => route('support-tickets.index', array_merge(request()->query(), [
            'sort' => $column,
            'direction' => ($filters['sort'] ?? null) === $column && ($filters['direction'] ?? 'desc') === 'asc' ? 'desc' : 'asc',
            'page' => null,
        ]));
    @endphp

    <div class="nd-sub-support-page">
        @include('subscriber.partials.settings-tabs', ['activeSettingsTab' => 'support'])

        <div class="nd-sub-support-heading">
            <h2>{{ __('Support') }}</h2>
            <a class="nd-sub-primary-button" href="{{ route('support-tickets.create') }}"><i class="iconoir-plus-circle" aria-hidden="true"></i>{{ __('New Ticket') }}</a>
        </div>

        <section class="nd-sub-support-card" aria-labelledby="support-ticket-list-title">
            <h3 class="visually-hidden" id="support-ticket-list-title">{{ __('Your support tickets') }}</h3>

            <form class="nd-sub-ticket-filters" method="GET" action="{{ route('support-tickets.index') }}">
                <label class="nd-sub-ticket-search"><span class="visually-hidden">{{ __('Search tickets') }}</span><i class="iconoir-search" aria-hidden="true"></i><input class="form-control" type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search tickets…') }}"></label>
                <select class="form-select" name="status" aria-label="{{ __('Filter by status') }}" onchange="this.form.submit()"><option value="">{{ __('All statuses') }}</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>@endforeach</select>
                <select class="form-select" name="priority" aria-label="{{ __('Filter by priority') }}" onchange="this.form.submit()"><option value="">{{ __('All priorities') }}</option>@foreach ($priorities as $priority)<option value="{{ $priority->value }}" @selected(($filters['priority'] ?? null) === $priority->value)>{{ $priority->label() }}</option>@endforeach</select>
                <label class="nd-sub-page-size">{{ __('Show') }}<select class="form-select" name="per_page" onchange="this.form.submit()">@foreach ([10, 15, 25, 50] as $size)<option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 15) === $size)>{{ $size }}</option>@endforeach</select><span>{{ __('entries') }}</span></label>
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'last_activity_at' }}"><input type="hidden" name="direction" value="{{ $filters['direction'] ?? 'desc' }}">
                <button class="btn btn-light" type="submit">{{ __('Apply') }}</button>
            </form>

            @if ($tickets->isEmpty())
                <div class="nd-sub-support-empty"><span><i class="iconoir-lifebelt" aria-hidden="true"></i></span><h3>{{ __('No support tickets yet.') }}</h3><p>{{ __('Create a ticket whenever you need help.') }}</p><a class="nd-sub-primary-button" href="{{ route('support-tickets.create') }}">{{ __('New Ticket') }}</a></div>
            @else
                <div class="table-responsive">
                    <table class="table nd-sub-ticket-table align-middle">
                        <thead><tr>
                            @foreach (['reference' => __('Reference'), 'subject' => __('Subject'), 'priority' => __('Priority'), 'status' => __('Status'), 'last_activity_at' => __('Last activity')] as $column => $label)
                                <th scope="col"><a href="{{ $sortUrl($column) }}">{{ $label }}<i class="iconoir-sort" aria-hidden="true"></i></a></th>
                            @endforeach
                            <th scope="col" class="text-end">{{ __('Actions') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($tickets as $ticket)
                                <tr>
                                    <td data-label="{{ __('Reference') }}"><a class="nd-sub-ticket-reference" href="{{ route('support-tickets.show', $ticket) }}">{{ $ticket->reference }}</a></td>
                                    <td data-label="{{ __('Subject') }}"><strong>{{ $ticket->subject }}</strong>@if ($ticket->category)<small>{{ $ticket->category }}</small>@endif</td>
                                    <td data-label="{{ __('Priority') }}"><span class="nd-sub-ticket-badge priority-{{ $ticket->priority->value }}">{{ $ticket->priority->label() }}</span></td>
                                    <td data-label="{{ __('Status') }}"><span class="nd-sub-ticket-badge status-{{ $ticket->status->value }}">{{ $ticket->status->label() }}</span></td>
                                    <td data-label="{{ __('Last activity') }}"><time datetime="{{ $ticket->last_activity_at->toIso8601String() }}">{{ $ticket->last_activity_at->format('d M, Y \a\t h:i A') }}</time></td>
                                    <td data-label="{{ __('Actions') }}" class="text-end"><a class="nd-sub-ticket-view" href="{{ route('support-tickets.show', $ticket) }}" aria-label="{{ __('View ticket :reference', ['reference' => $ticket->reference]) }}" title="{{ __('View ticket') }}"><i class="iconoir-eye" aria-hidden="true"></i></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="nd-sub-ticket-pagination"><span>{{ __('Showing :first–:last of :total tickets', ['first' => $tickets->firstItem(), 'last' => $tickets->lastItem(), 'total' => $tickets->total()]) }}</span>{{ $tickets->links('pagination::bootstrap-5') }}</div>
            @endif
        </section>
    </div>
@endsection
