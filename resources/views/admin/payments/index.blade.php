@extends('admin.layouts.app')

@section('title', __('Payments'))

@php
    $currentSort = $filters['sort'] ?? 'submitted_at';
    $currentDirection = $filters['direction'] ?? 'desc';
    $sortUrl = fn (string $column) => request()->fullUrlWithQuery(['sort' => $column, 'direction' => $currentSort === $column && $currentDirection === 'asc' ? 'desc' : 'asc', 'page' => null]);
    $statusClass = fn ($status) => match ($status) {
        \App\Enums\PaymentStatus::PAID => 'nd-status-success',
        \App\Enums\PaymentStatus::PENDING => 'nd-status-warning',
        \App\Enums\PaymentStatus::REJECTED, \App\Enums\PaymentStatus::CANCELED, \App\Enums\PaymentStatus::EXPIRED => 'nd-status-danger',
        default => 'nd-status-muted',
    };
@endphp

@section('content')
    <div class="nd-page-heading"><h1>{{ __('Payments') }}</h1><p>{{ __('Review subscriber payments and their immutable billing records.') }}</p></div>

    <section class="card nd-card nd-users-card nd-payments-card" aria-labelledby="payments-title"><div class="card-body">
        <h2 class="visually-hidden" id="payments-title">{{ __('Subscriber payments') }}</h2>
        <form class="nd-payment-toolbar" method="GET" action="{{ route('admin.payments.index') }}" role="search">
            <div class="nd-users-search"><i class="iconoir-search" aria-hidden="true"></i><label class="visually-hidden" for="payment-search">{{ __('Search payments') }}</label><input class="form-control" id="payment-search" name="search" type="search" value="{{ $filters['search'] ?? '' }}" maxlength="100" placeholder="{{ __('Search reference, invoice, transfer, customer, or plan...') }}"></div>
            <div class="nd-payment-filters">
                <label class="visually-hidden" for="payment-status">{{ __('Status') }}</label><select class="form-select" id="payment-status" name="status" data-payment-auto-submit><option value="">{{ __('All statuses') }}</option>@foreach ($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select>
                <label class="visually-hidden" for="payment-method">{{ __('Payment method') }}</label><select class="form-select" id="payment-method" name="payment_method" data-payment-auto-submit><option value="">{{ __('All methods') }}</option>@foreach ($methods as $method)<option value="{{ $method->value }}" @selected(($filters['payment_method'] ?? '') === $method->value)>{{ $method->label() }}</option>@endforeach</select>
                <label class="visually-hidden" for="payment-plan">{{ __('Plan') }}</label><select class="form-select" id="payment-plan" name="plan_id" data-payment-auto-submit><option value="">{{ __('All plans') }}</option>@foreach ($plans as $plan)<option value="{{ $plan->id }}" @selected((int) ($filters['plan_id'] ?? 0) === $plan->id)>{{ $plan->name }}</option>@endforeach</select>
                <label class="visually-hidden" for="payment-date-from">{{ __('Submitted from') }}</label><input class="form-control" id="payment-date-from" name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" title="{{ __('Submitted from') }}">
                <label class="visually-hidden" for="payment-date-to">{{ __('Submitted through') }}</label><input class="form-control" id="payment-date-to" name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" title="{{ __('Submitted through') }}">
                <label for="payments-per-page">{{ __('Show') }}</label><select class="form-select nd-payment-page-size" id="payments-per-page" name="per_page" data-payment-auto-submit>@foreach ([10, 15, 25, 50] as $size)<option value="{{ $size }}" @selected((int) ($filters['per_page'] ?? 15) === $size)>{{ $size }}</option>@endforeach</select><span>{{ __('entries') }}</span>
                <input name="sort" type="hidden" value="{{ $currentSort }}"><input name="direction" type="hidden" value="{{ $currentDirection }}">
                <button class="btn nd-btn-primary" type="submit"><i class="iconoir-search" aria-hidden="true"></i>{{ __('Apply') }}</button>@if (collect($filters)->except(['sort', 'direction', 'per_page'])->filter()->isNotEmpty())<a class="btn nd-btn-secondary" href="{{ route('admin.payments.index') }}">{{ __('Clear') }}</a>@endif
            </div>
        </form>

        @if ($payments->isEmpty())
            <div class="nd-users-empty" role="status"><span aria-hidden="true"><i class="iconoir-credit-card"></i></span><h3>{{ __('No payments found') }}</h3><p>{{ __('Payments will appear here after subscribers submit them.') }}</p></div>
        @else
            <div class="table-responsive nd-users-table-wrap"><table class="table nd-users-table nd-payments-table align-middle">
                <thead><tr>
                    <th scope="col"><a class="nd-sort-link" href="{{ $sortUrl('reference') }}">{{ __('Reference') }}<i class="iconoir-sort" aria-hidden="true"></i></a></th><th scope="col"><a class="nd-sort-link" href="{{ $sortUrl('expected_amount_minor') }}">{{ __('Amount') }}<i class="iconoir-sort" aria-hidden="true"></i></a></th><th scope="col"><a class="nd-sort-link" href="{{ $sortUrl('payment_method') }}">{{ __('Method') }}<i class="iconoir-sort" aria-hidden="true"></i></a></th><th scope="col"><a class="nd-sort-link" href="{{ $sortUrl('status') }}">{{ __('Status') }}<i class="iconoir-sort" aria-hidden="true"></i></a></th><th scope="col">{{ __('Customer') }}</th><th scope="col">{{ __('Plan') }}</th><th scope="col"><a class="nd-sort-link" href="{{ $sortUrl('submitted_at') }}">{{ __('Submitted') }}<i class="iconoir-sort" aria-hidden="true"></i></a></th><th class="text-end" scope="col">{{ __('Actions') }}</th>
                </tr></thead>
                <tbody>@foreach ($payments as $payment)<tr>
                    <td data-label="{{ __('Reference') }}"><a class="nd-payment-reference" href="{{ route('admin.payments.show', $payment) }}">{{ $payment->reference }}</a>@if ($payment->invoice)<small>{{ $payment->invoice->number }}</small>@endif</td>
                    <td data-label="{{ __('Amount') }}"><strong>{{ $payment->formattedExpectedAmount() }}</strong>@if ($payment->hasAmountMismatch())<small class="text-danger">{{ __('Submitted :amount', ['amount' => $payment->formattedSubmittedAmount()]) }}</small>@endif</td>
                    <td data-label="{{ __('Method') }}"><span class="nd-payment-method">{{ $payment->payment_method->label() }}</span></td><td data-label="{{ __('Status') }}"><span class="nd-status-badge {{ $statusClass($payment->status) }}">{{ $payment->status->label() }}</span></td>
                    <td data-label="{{ __('Customer') }}"><span class="nd-payment-customer">{{ $payment->user?->name ?? __('Former subscriber') }}</span><small>{{ $payment->user?->email ?? __('Account unavailable') }}</small></td><td data-label="{{ __('Plan') }}"><span>{{ $payment->plan_name_snapshot }}</span></td>
                    <td class="nd-cell-muted" data-label="{{ __('Submitted') }}"><time datetime="{{ $payment->submitted_at?->toIso8601String() }}">{{ $payment->submitted_at?->timezone(config('app.timezone'))->format('d M, Y \a\t h:i A') ?? __('N/A') }}</time></td><td class="text-end" data-label="{{ __('Actions') }}"><div class="nd-row-actions"><a class="btn nd-icon-action" href="{{ route('admin.payments.show', $payment) }}" aria-label="{{ __('View payment :payment', ['payment' => $payment->reference]) }}" title="{{ __('View') }}"><i class="iconoir-eye" aria-hidden="true"></i></a></div></td>
                </tr>@endforeach</tbody>
            </table></div>
            <div class="nd-users-pagination"><p>{{ __('Showing :first-:last of :total payments', ['first' => $payments->firstItem(), 'last' => $payments->lastItem(), 'total' => $payments->total()]) }}</p>{{ $payments->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
        @endif
    </div></section>
@endsection

@push('scripts')<script src="{{ asset('js/admin-payments.js') }}"></script>@endpush
