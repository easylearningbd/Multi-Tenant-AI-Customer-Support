@extends('subscriber.layouts.app')

@section('title', __('Payment :reference', ['reference' => $payment->reference]))
@section('header-title', $payment->reference)
@section('header-subtitle', __('Bank-transfer payment details'))

@section('content')
    <div class="nd-sub-payment-page nd-sub-payment-detail-page">
        <div class="nd-sub-payment-heading">
            <div>
                <span class="nd-sub-payment-status status-{{ $payment->status->value }}">{{ $payment->status->label() }}</span>
                <h2>{{ $payment->plan_name_snapshot }}</h2>
                <p>{{ __('Payment reference: :reference', ['reference' => $payment->reference]) }}</p>
            </div>
            <a class="nd-sub-back-button" href="{{ route('billing.index') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back to Billing') }}</a>
        </div>

        @if ($payment->status === \App\Enums\PaymentStatus::PENDING)
            <div class="nd-sub-payment-banner pending" role="status"><i class="iconoir-clock" aria-hidden="true"></i><p>{{ __('Your bank transfer is awaiting review. Your current plan remains active until this payment is approved.') }}</p></div>
        @elseif ($payment->status === \App\Enums\PaymentStatus::REJECTED)
            <div class="nd-sub-payment-banner rejected" role="alert"><i class="iconoir-warning-circle" aria-hidden="true"></i><div><strong>{{ __('Payment rejected') }}</strong><p>{{ $payment->rejection_reason ?: __('Please contact support before submitting another payment.') }}</p></div></div>
        @endif

        <div class="row g-3 g-xl-4">
            <div class="col-xl-7">
                <section class="nd-sub-billing-card nd-sub-payment-detail-card" aria-labelledby="payment-information-title">
                    <header><small>{{ __('Transfer record') }}</small><h3 id="payment-information-title">{{ __('Payment information') }}</h3></header>
                    <dl class="nd-sub-detail-list">
                        <div><dt>{{ __('Payment reference') }}</dt><dd>{{ $payment->reference }}</dd></div>
                        <div><dt>{{ __('Invoice number') }}</dt><dd>{{ $payment->invoice?->number ?? __('Not available') }}</dd></div>
                        <div><dt>{{ __('Status') }}</dt><dd>{{ $payment->status->label() }}</dd></div>
                        <div><dt>{{ __('Payment method') }}</dt><dd>{{ $payment->payment_method->label() }}</dd></div>
                        <div><dt>{{ __('Payer name') }}</dt><dd>{{ $payment->payer_name }}</dd></div>
                        <div><dt>{{ __('Payer bank') }}</dt><dd>{{ $payment->payer_bank_name }}</dd></div>
                        <div><dt>{{ __('Transaction reference') }}</dt><dd>{{ $payment->transaction_reference }}</dd></div>
                        <div><dt>{{ __('Transfer date') }}</dt><dd><time datetime="{{ $payment->transferred_at->toIso8601String() }}">{{ $payment->transferred_at->format('M d, Y · h:i A') }}</time></dd></div>
                        <div><dt>{{ __('Submitted date') }}</dt><dd><time datetime="{{ $payment->submitted_at->toIso8601String() }}">{{ $payment->submitted_at->format('M d, Y · h:i A') }}</time></dd></div>
                        @if ($payment->reviewed_at)<div><dt>{{ __('Reviewed date') }}</dt><dd><time datetime="{{ $payment->reviewed_at->toIso8601String() }}">{{ $payment->reviewed_at->format('M d, Y · h:i A') }}</time></dd></div>@endif
                        @if ($payment->paid_at)<div><dt>{{ __('Paid date') }}</dt><dd><time datetime="{{ $payment->paid_at->toIso8601String() }}">{{ $payment->paid_at->format('M d, Y · h:i A') }}</time></dd></div>@endif
                    </dl>
                    @if ($payment->notes)<div class="nd-sub-payment-notes-display"><small>{{ __('Notes') }}</small><p>{{ $payment->notes }}</p></div>@endif
                </section>

                <section class="nd-sub-billing-card nd-sub-proof-card" aria-labelledby="proof-title">
                    <header><span class="nd-sub-billing-icon invoice" aria-hidden="true"><i class="iconoir-attachment"></i></span><div><small>{{ __('Private document') }}</small><h3 id="proof-title">{{ __('Payment proof') }}</h3></div></header>
                    @if ($payment->proof)
                        <div><span><i class="iconoir-page" aria-hidden="true"></i></span><p><strong>{{ $payment->proof->original_name }}</strong><small>{{ number_format($payment->proof->size / 1024, 1) }} KB</small></p><a href="{{ route('billing.payments.proof.download', $payment) }}"><i class="iconoir-download" aria-hidden="true"></i>{{ __('Download proof') }}</a></div>
                    @else
                        <p>{{ __('Payment proof is unavailable.') }}</p>
                    @endif
                </section>
            </div>

            <div class="col-xl-5">
                <aside class="nd-sub-billing-card nd-sub-order-summary" aria-labelledby="submitted-order-title">
                    <header><small>{{ __('Immutable order summary') }}</small><h3 id="submitted-order-title">{{ $payment->plan_name_snapshot }}</h3></header>
                    <dl>
                        <div><dt>{{ __('Interval') }}</dt><dd>{{ $payment->plan_interval_snapshot->label() }}</dd></div>
                        <div><dt>{{ __('Expected amount') }}</dt><dd>{{ $payment->formattedExpectedAmount() }}</dd></div>
                        <div><dt>{{ __('Submitted amount') }}</dt><dd>{{ $payment->formattedSubmittedAmount() }}</dd></div>
                        <div><dt>{{ __('Currency') }}</dt><dd>{{ $payment->currency }}</dd></div>
                    </dl>
                    @if (count($payment->plan_snapshot['features'] ?? []) > 0)
                        <h4>{{ __('Plan features after approval') }}</h4>
                        <ul>@foreach ($payment->plan_snapshot['features'] as $feature)<li><i class="iconoir-check-circle" aria-hidden="true"></i>{{ $feature }}</li>@endforeach</ul>
                    @endif
                </aside>
            </div>
        </div>
    </div>
@endsection
