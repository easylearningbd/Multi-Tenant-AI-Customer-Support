@extends('subscriber.layouts.app')

@section('title', __('Billing'))
@section('header-title', __('Billing'))
@section('header-subtitle', __('Plan, usage, and invoices for your chatbot workspace.'))

@section('content')
    @php
        $current = $billing['current'];
        $statusClass = $current ? str_replace('_', '-', $current['status']->value) : 'unavailable';
    @endphp

    <div class="nd-sub-billing-page">
        <div class="row g-3 g-xl-4 align-items-stretch nd-sub-billing-current-row">
            <div class="col-xl-8">
                <section class="nd-sub-billing-card nd-sub-current-plan-card h-100" aria-labelledby="current-plan-title">
                    @if ($current)
                        <header class="nd-sub-current-plan-header">
                            <span class="nd-sub-billing-icon current" aria-hidden="true"><i class="iconoir-sparks"></i></span>
                            <div>
                                <small>{{ __('Current workspace plan') }}</small>
                                <div class="nd-sub-current-plan-name">
                                    <h2 id="current-plan-title">{{ $current['name'] }}</h2>
                                    <span class="nd-sub-subscription-status status-{{ $statusClass }}">{{ $current['statusLabel'] }}</span>
                                </div>
                            </div>
                        </header>

                        @if ($current['dateLabel']['date'])
                            <p class="nd-sub-billing-date">
                                {{ $current['dateLabel']['label'] }}
                                <time datetime="{{ $current['dateLabel']['date']->toIso8601String() }}">{{ $current['dateLabel']['date']->format('M d, Y') }}</time>
                            </p>
                        @endif

                        <div class="nd-sub-usage-grid">
                            @foreach ($current['usage'] as $usage)
                                <article class="nd-sub-usage-item is-{{ $usage['state'] }}">
                                    <div>
                                        <h3>{{ $usage['label'] }}</h3>
                                        <strong>{{ $usage['usedLabel'] }} / {{ $usage['limitLabel'] }}</strong>
                                    </div>
                                    @if ($usage['unlimited'])
                                        <div class="nd-sub-usage-progress is-unlimited" role="img" aria-label="{{ __(':label usage: :used used, unlimited plan capacity', ['label' => $usage['label'], 'used' => $usage['usedLabel']]) }}"><span></span></div>
                                    @else
                                        <div class="nd-sub-usage-progress is-{{ $usage['state'] }}" role="progressbar" aria-label="{{ __(':label plan usage', ['label' => $usage['label']]) }}" aria-valuemin="0" aria-valuenow="{{ $usage['used'] }}" aria-valuemax="{{ $usage['limit'] }}"><span style="--nd-usage-width: {{ $usage['percentage'] }}%"></span></div>
                                    @endif
                                </article>
                            @endforeach
                        </div>
                    @else
                        <div class="nd-sub-billing-empty current-plan-empty">
                            <span class="nd-sub-billing-icon current" aria-hidden="true"><i class="iconoir-sparks"></i></span>
                            <h2 id="current-plan-title">{{ __('No active plan') }}</h2>
                            <p>{{ __('Your billing plan is not configured. Please contact support for assistance.') }}</p>
                            @if (Route::has('support-tickets.create'))
                                <a class="nd-sub-billing-dark-button" href="{{ route('support-tickets.create') }}">{{ __('Contact support') }}</a>
                            @endif
                        </div>
                    @endif
                </section>
            </div>

            <div class="col-xl-4">
                <section class="nd-sub-billing-card nd-sub-plan-features-card h-100" aria-labelledby="current-features-title">
                    <header>
                        <span class="nd-sub-billing-icon features" aria-hidden="true"><i class="iconoir-rocket"></i></span>
                        <div><small>{{ __('Your plan includes') }}</small><h2 id="current-features-title">{{ __('Plan features') }}</h2></div>
                    </header>
                    @if ($current && count($current['features']) > 0)
                        <ul>
                            @foreach ($current['features'] as $feature)
                                <li><i class="iconoir-check-circle" aria-hidden="true"></i><span>{{ $feature }}</span></li>
                            @endforeach
                        </ul>
                    @else
                        <div class="nd-sub-billing-empty compact"><p>{{ __('No plan features are available to display.') }}</p></div>
                    @endif
                </section>
            </div>
        </div>

        <section class="nd-sub-billing-card nd-sub-available-plans" aria-labelledby="available-plans-title">
            <header class="nd-sub-billing-section-heading">
                <div><small>{{ __('Explore options') }}</small><h2 id="available-plans-title">{{ __('Available plans') }}</h2></div>
                <p>{{ __('Change plans when your workspace needs evolve.') }}</p>
            </header>

            @if ($billing['availablePlans']->isEmpty())
                <div class="nd-sub-billing-empty"><span class="nd-sub-billing-icon features" aria-hidden="true"><i class="iconoir-box-iso"></i></span><h3>{{ __('No plans are available right now.') }}</h3><p>{{ __('Please check again later or contact support.') }}</p></div>
            @else
                <div class="nd-sub-plan-grid">
                    @foreach ($billing['availablePlans'] as $available)
                        @php($plan = $available['model'])
                        <article class="nd-sub-plan-card {{ $available['isCurrent'] ? 'is-current' : '' }}">
                            @if ($available['isCurrent'])<span class="nd-sub-current-badge">{{ __('Current') }}</span>@endif
                            <h3>{{ $plan->name }}</h3>
                            <p>{{ $plan->description ?: __('No description provided.') }}</p>
                            <div class="nd-sub-plan-price"><strong>{{ $available['priceLabel'] }}</strong><span>/ {{ $available['intervalLabel'] }}</span></div>

                            @if (count($available['features']) > 0)
                                <ul class="nd-sub-plan-feature-list">
                                    @foreach ($available['features'] as $feature)
                                        <li><i class="iconoir-check" aria-hidden="true"></i><span>{{ $feature }}</span></li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="nd-sub-plan-no-features">{{ __('No feature list provided.') }}</p>
                            @endif

                            <dl class="nd-sub-plan-limits">
                                @foreach (\App\Models\Plan::LIMITS as $key => $label)
                                    <div><dt>{{ __($label) }}</dt><dd>{{ $plan->hasUnlimitedLimit($key) ? __('Unlimited') : number_format($plan->limitFor($key)) }}</dd></div>
                                @endforeach
                            </dl>

                            <div class="nd-sub-plan-action">
                                @if ($available['isCurrent'])
                                    <button type="button" disabled aria-describedby="plan-note-{{ $plan->id }}">{{ __('Current plan') }} <i class="iconoir-arrow-right" aria-hidden="true"></i></button>
                                    <small id="plan-note-{{ $plan->id }}">{{ __('This is your current subscription.') }}</small>
                                @elseif ($plan->custom_pricing)
                                    @if (Route::has('support-tickets.create'))
                                        <a class="is-contact" href="{{ route('support-tickets.create') }}">{{ __('Contact sales') }} <i class="iconoir-arrow-right" aria-hidden="true"></i></a>
                                        <small>{{ __('Contact support to discuss this plan.') }}</small>
                                    @else
                                        <button class="is-contact" type="button" disabled aria-describedby="plan-note-{{ $plan->id }}">{{ __('Contact sales') }}</button>
                                        <small id="plan-note-{{ $plan->id }}">{{ __('A contact channel is not configured yet.') }}</small>
                                    @endif
                                @elseif ($available['trialUsed'])
                                    <button type="button" disabled aria-describedby="plan-note-{{ $plan->id }}">{{ __('Trial used') }}</button>
                                    <small id="plan-note-{{ $plan->id }}">{{ __('The introductory trial can only be used once.') }}</small>
                                @elseif ($plan->interval === \App\Enums\PlanInterval::TRIAL)
                                    <button type="button" disabled aria-describedby="plan-note-{{ $plan->id }}">{{ __('Available at registration') }}</button>
                                    <small id="plan-note-{{ $plan->id }}">{{ __('Trials cannot be started again from Billing.') }}</small>
                                @else
                                    @if ($available['canPay'])
                                        <a href="{{ route('billing.payment.create', $plan) }}">{{ __('Choose plan') }} <i class="iconoir-arrow-right" aria-hidden="true"></i></a>
                                        <small>{{ __('Pay securely by bank transfer.') }}</small>
                                    @else
                                        <button type="button" disabled aria-describedby="plan-note-{{ $plan->id }}">{{ __('Choose plan') }} <i class="iconoir-arrow-right" aria-hidden="true"></i></button>
                                        <small id="plan-note-{{ $plan->id }}">{{ __('Bank transfer is not available for this plan.') }}</small>
                                    @endif
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="nd-sub-billing-card nd-sub-invoices-card" aria-labelledby="invoices-title">
            <header class="nd-sub-billing-section-heading"><div><small>{{ __('Payment history') }}</small><h2 id="invoices-title">{{ __('Invoices') }}</h2></div></header>

            @if ($billing['invoices']->isEmpty())
                <div class="nd-sub-billing-empty invoice-empty"><span class="nd-sub-billing-icon invoice" aria-hidden="true"><i class="iconoir-receipt"></i></span><h3>{{ __('No invoices yet.') }}</h3><p>{{ __('Your payment history will appear here after your first successful purchase.') }}</p></div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle nd-sub-invoice-table">
                        <thead><tr><th scope="col">{{ __('Date') }}</th><th scope="col">{{ __('Reference') }}</th><th scope="col">{{ __('Description') }}</th><th scope="col">{{ __('Amount') }}</th><th scope="col">{{ __('Method') }}</th><th scope="col">{{ __('Status') }}</th><th scope="col"><span class="visually-hidden">{{ __('Action') }}</span></th></tr></thead>
                        <tbody>
                            @foreach ($billing['invoices'] as $invoice)
                                <tr>
                                    <td><time datetime="{{ $invoice->submitted_at->toIso8601String() }}">{{ $invoice->submitted_at->format('M d, Y') }}</time></td>
                                    <td><strong>{{ $invoice->invoice?->number ?? $invoice->reference }}</strong><small>{{ $invoice->reference }}</small></td>
                                    <td>{{ $invoice->plan_name_snapshot }} {{ __('subscription') }}</td>
                                    <td>{{ $invoice->formattedExpectedAmount() }}</td>
                                    <td>{{ $invoice->payment_method->label() }}</td>
                                    <td><span class="nd-sub-payment-status status-{{ $invoice->status->value }}">{{ $invoice->status->label() }}</span></td>
                                    <td><a class="nd-sub-invoice-action" href="{{ route('billing.payments.show', $invoice) }}" aria-label="{{ __('View payment :reference', ['reference' => $invoice->reference]) }}"><i class="iconoir-eye" aria-hidden="true"></i></a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($billing['invoices']->hasPages())
                    <div class="nd-sub-billing-pagination">{{ $billing['invoices']->withQueryString()->links() }}</div>
                @endif
            @endif
        </section>
    </div>
@endsection
