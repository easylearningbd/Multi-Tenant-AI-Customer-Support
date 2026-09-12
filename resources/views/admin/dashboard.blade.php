@extends('admin.layouts.app')

@section('title', __('Super Admin Dashboard'))

@section('content')
    <div class="nd-page-heading d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3">
        <div>
            <h1>{{ __('Dashboard') }}</h1>
            <p>{{ __('Track revenue, payments, refunds, and operational activity from one focused view.') }}</p>
        </div>
        <div class="nd-source-note" role="status">
            <i class="iconoir-info-circle" aria-hidden="true"></i>
            <span>{{ $dashboard['dataNotice'] }}</span>
        </div>
    </div>

    <section aria-labelledby="dashboard-summary-heading" class="mb-4">
        <h2 class="visually-hidden" id="dashboard-summary-heading">{{ __('Dashboard summary') }}</h2>
        <div class="row g-3 g-xxl-4">
            @foreach ($dashboard['summary'] as $metric)
                <div class="col-sm-6 col-xl-3">
                    <article class="card nd-card nd-metric-card h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <span class="nd-metric-icon nd-tone-{{ $metric['tone'] }}" aria-hidden="true"><i class="{{ $metric['icon'] }}"></i></span>
                                <span class="nd-status-pill nd-tone-{{ $metric['tone'] }}">{{ $metric['status'] }}</span>
                            </div>
                            <p class="nd-metric-label">{{ $metric['label'] }}</p>
                            <p class="nd-metric-value">{{ $metric['value'] }}</p>
                        </div>
                    </article>
                </div>
            @endforeach
        </div>
    </section>

    <section class="row g-3 g-xxl-4 mb-4" aria-label="{{ __('Revenue and payment health') }}">
        <div class="col-xl-8">
            <article class="card nd-card nd-chart-card h-100">
                <div class="card-body">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-4">
                        <div>
                            <h2 class="nd-card-title">{{ __('Revenue Overview') }}</h2>
                            <p class="nd-card-subtitle">{{ __('Monthly collection, net revenue, and refunds') }}</p>
                        </div>
                        <div class="nd-chart-legend" aria-label="{{ __('Revenue chart legend') }}">
                            <span><i class="nd-legend-dot nd-net"></i>{{ __('Net Revenue') }}</span>
                            <span><i class="nd-legend-dot nd-collected"></i>{{ __('Collected') }}</span>
                            <span><i class="nd-legend-dot nd-refunds"></i>{{ __('Refunds') }}</span>
                        </div>
                    </div>

                    @if ($dashboard['revenue']['hasData'])
                        <div id="admin-revenue-chart" class="nd-revenue-chart" role="img" aria-label="{{ __('Monthly net revenue, collected payments, and refunds chart') }}"></div>
                    @else
                        <div class="nd-chart-empty" role="status">
                            <span class="nd-empty-icon" aria-hidden="true"><i class="iconoir-stat-up"></i></span>
                            <h3>{{ __('No revenue data yet') }}</h3>
                            <p>{{ __('Revenue trends will appear after the payment module records transactions.') }}</p>
                        </div>
                    @endif
                </div>
            </article>
        </div>

        <div class="col-xl-4">
            <article class="card nd-card nd-health-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <div>
                            <h2 class="nd-card-title">{{ __('Payment Health') }}</h2>
                            <p class="nd-card-subtitle">{{ __('Gateway and settlement status') }}</p>
                        </div>
                        <span class="nd-status-pill nd-tone-neutral">{{ $dashboard['paymentHealth']['hasData'] ? __('Live') : __('Awaiting data') }}</span>
                    </div>

                    @if ($dashboard['paymentHealth']['hasData'])
                        <div id="admin-payment-health-chart" class="nd-health-chart" role="img" aria-label="{{ __('Percentage of successful payments') }}"></div>
                    @else
                        <div class="nd-empty-donut" role="img" aria-label="{{ __('Payment success percentage is unavailable') }}"><div><strong>—</strong><span>{{ __('Paid') }}</span></div></div>
                    @endif

                    <div class="nd-health-stats">
                        <div><span>{{ __('Completed') }}</span><strong>{{ $dashboard['paymentHealth']['completed'] ?? '—' }}</strong></div>
                        <div><span>{{ __('Failed') }}</span><strong>{{ $dashboard['paymentHealth']['failed'] ?? '—' }}</strong></div>
                        <div><span>{{ __('Pending Rate') }}</span><strong>{{ $dashboard['paymentHealth']['pendingRate'] ?? '—' }}</strong></div>
                        <div><span>{{ __('Failure Rate') }}</span><strong>{{ $dashboard['paymentHealth']['failureRate'] ?? '—' }}</strong></div>
                        <div><span>{{ __('Net Revenue') }}</span><strong>{{ $dashboard['paymentHealth']['netRevenue'] ?? '—' }}</strong></div>
                        <div><span>{{ __('Refunded') }}</span><strong>{{ $dashboard['paymentHealth']['refunded'] ?? '—' }}</strong></div>
                    </div>

                    <div class="nd-gateway-mix">
                        <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                            <h3>{{ __('Gateway Mix') }}</h3>
                            @if ($dashboard['paymentHealth']['hasData'])
                                <span>{{ __(':count payments', ['count' => $dashboard['paymentHealth']['completed']]) }}</span>
                            @endif
                        </div>
                        @forelse ($dashboard['paymentHealth']['gateways'] as $gateway)
                            <div class="nd-gateway-row">
                                <div><span>{{ $gateway['name'] }}</span><span>{{ $gateway['count'] }}</span></div>
                                <div class="progress" role="progressbar" aria-label="{{ __(':gateway payment share', ['gateway' => $gateway['name']]) }}" aria-valuenow="{{ $gateway['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width: {{ $gateway['percentage'] }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="nd-inline-empty">{{ __('Gateway usage will appear when payment data is available.') }}</p>
                        @endforelse
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section class="row g-3 g-xxl-4" aria-label="{{ __('Recent operations') }}">
        <div class="col-xl-7">
            <article class="card nd-card h-100">
                <div class="card-body">
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 mb-3">
                        <h2 class="nd-card-title mb-0">{{ __('Recent Activity') }}</h2>
                        @if (Route::has('admin.payments.index'))
                            <a class="nd-view-all" href="{{ route('admin.payments.index') }}">{{ __('View All') }}<span class="visually-hidden"> {{ __('payments') }}</span></a>
                        @endif
                    </div>

                    <ul class="nav nav-pills nd-activity-tabs" id="activity-tabs" role="tablist">
                        <li class="nav-item" role="presentation"><button class="nav-link active" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments-panel" type="button" role="tab" aria-controls="payments-panel" aria-selected="true"><i class="iconoir-credit-card" aria-hidden="true"></i>{{ __('Payments') }}</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-panel" type="button" role="tab" aria-controls="users-panel" aria-selected="false"><i class="iconoir-group" aria-hidden="true"></i>{{ __('Users') }}</button></li>
                        <li class="nav-item" role="presentation"><button class="nav-link" id="logins-tab" data-bs-toggle="tab" data-bs-target="#logins-panel" type="button" role="tab" aria-controls="logins-panel" aria-selected="false"><i class="iconoir-clock" aria-hidden="true"></i>{{ __('Logins') }}</button></li>
                    </ul>

                    <div class="tab-content nd-activity-content" id="activity-tab-content">
                        <div class="tab-pane fade show active" id="payments-panel" role="tabpanel" aria-labelledby="payments-tab" tabindex="0">
                            @forelse ($dashboard['activity']['payments'] as $payment)
                                <div class="nd-activity-row">
                                    <span class="nd-row-icon nd-tone-success"><i class="iconoir-credit-card" aria-hidden="true"></i></span>
                                    <div class="nd-row-copy"><strong>{{ $payment['name'] }}</strong><span>{{ $payment['source'] }} · {{ $payment['time'] }}</span></div>
                                    <strong class="nd-row-value">{{ $payment['amount'] }}</strong>
                                    <span class="nd-status-pill nd-tone-success">{{ $payment['status'] }}</span>
                                </div>
                            @empty
                                <div class="nd-list-empty"><i class="iconoir-credit-card" aria-hidden="true"></i><strong>{{ __('No payment activity') }}</strong><span>{{ __('Completed and pending payments will appear here.') }}</span></div>
                            @endforelse
                        </div>

                        <div class="tab-pane fade" id="users-panel" role="tabpanel" aria-labelledby="users-tab" tabindex="0">
                            @forelse ($dashboard['activity']['users'] as $userActivity)
                                <div class="nd-activity-row">
                                    <span class="nd-row-icon nd-tone-primary"><i class="iconoir-user" aria-hidden="true"></i></span>
                                    <div class="nd-row-copy"><strong>{{ $userActivity['name'] }}</strong><span>{{ $userActivity['description'] }} · {{ $userActivity['time'] }}</span></div>
                                    <span class="nd-status-pill nd-tone-primary">{{ __('New') }}</span>
                                </div>
                            @empty
                                <div class="nd-list-empty"><i class="iconoir-group" aria-hidden="true"></i><strong>{{ __('No recent users') }}</strong><span>{{ __('New account activity will appear here.') }}</span></div>
                            @endforelse
                        </div>

                        <div class="tab-pane fade" id="logins-panel" role="tabpanel" aria-labelledby="logins-tab" tabindex="0">
                            @forelse ($dashboard['activity']['logins'] as $login)
                                <div class="nd-activity-row">
                                    <span class="nd-row-icon nd-tone-primary"><i class="iconoir-clock" aria-hidden="true"></i></span>
                                    <div class="nd-row-copy"><strong>{{ $login['name'] }}</strong><span>{{ $login['description'] }} · {{ $login['time'] }}</span></div>
                                    <span class="nd-status-pill nd-tone-primary">{{ $login['status'] }}</span>
                                </div>
                            @empty
                                <div class="nd-list-empty"><i class="iconoir-clock" aria-hidden="true"></i><strong>{{ __('No login activity source') }}</strong><span>{{ __('Login events will appear when activity tracking is implemented.') }}</span></div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </article>
        </div>

        <div class="col-xl-5">
            <article class="card nd-card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
                        <h2 class="nd-card-title mb-0">{{ __('Support Tickets') }}</h2>
                        @if (Route::has('admin.support-tickets.index'))
                            <a class="nd-view-all" href="{{ route('admin.support-tickets.index') }}">{{ __('View All') }}<span class="visually-hidden"> {{ __('support tickets') }}</span></a>
                        @endif
                    </div>

                    <div class="nd-ticket-counts" aria-label="{{ __('Support ticket totals') }}">
                        <div class="nd-ticket-open"><strong>{{ $dashboard['tickets']['open'] ?? '—' }}</strong><span>{{ __('Open') }}</span></div>
                        <div class="nd-ticket-pending"><strong>{{ $dashboard['tickets']['pending'] ?? '—' }}</strong><span>{{ __('Pending') }}</span></div>
                        <div class="nd-ticket-urgent"><strong>{{ $dashboard['tickets']['urgent'] ?? '—' }}</strong><span>{{ __('Urgent') }}</span></div>
                    </div>

                    <div class="nd-ticket-list">
                        @forelse ($dashboard['tickets']['recent'] as $ticket)
                            <div class="nd-ticket-row">
                                <span class="nd-row-icon nd-tone-primary"><i class="iconoir-chat-bubble" aria-hidden="true"></i></span>
                                <div class="nd-row-copy"><strong>{{ $ticket['subject'] }}</strong><span>{{ $ticket['reference'] }} · {{ $ticket['customer'] }} · {{ $ticket['time'] }}</span></div>
                                <span class="nd-status-pill nd-tone-{{ $ticket['tone'] }}">{{ $ticket['status'] }}</span>
                            </div>
                        @empty
                            <div class="nd-list-empty"><i class="iconoir-chat-bubble" aria-hidden="true"></i><strong>{{ __('No support ticket data') }}</strong><span>{{ __('Recent tickets will appear when the support module is connected.') }}</span></div>
                        @endforelse
                    </div>
                </div>
            </article>
        </div>
    </section>
@endsection

@push('vendor-scripts')
    <script src="{{ asset('theme/assets/libs/apexcharts/apexcharts.min.js') }}"></script>
@endpush

@push('scripts')
    <script>
        window.NeuralDeskAdminDashboardCharts = {{ Illuminate\Support\Js::from([
            'revenue' => $dashboard['revenue'],
            'paymentHealth' => $dashboard['paymentHealth'],
        ]) }};
    </script>
    <script src="{{ asset('js/admin-dashboard.js') }}"></script>
@endpush
