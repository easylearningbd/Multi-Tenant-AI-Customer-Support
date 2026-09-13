@extends('subscriber.layouts.app')

@section('title', __('Complete payment'))
@section('header-title', __('Complete payment'))
@section('header-subtitle', __('Submit your bank transfer for secure manual review.'))

@section('content')
    @php($paymentErrors = $errors->getBag('bankTransfer'))

    <div class="nd-sub-payment-page">
        <div class="nd-sub-payment-heading">
            <div><h2>{{ __('Complete payment') }}</h2><p>{{ __('Your current plan stays active while this payment is reviewed.') }}</p></div>
            <a class="nd-sub-back-button" href="{{ route('billing.index') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back to Billing') }}</a>
        </div>

        <div class="row g-3 g-xl-4 align-items-start">
            <div class="col-xl-7">
                <section class="nd-sub-billing-card nd-sub-payment-methods" aria-labelledby="payment-method-title">
                    <header><small>{{ __('Payment method') }}</small><h3 id="payment-method-title">{{ __('How would you like to pay?') }}</h3></header>
                    <div class="nd-sub-method-grid" role="radiogroup" aria-label="{{ __('Payment method') }}">
                        <div class="nd-sub-method-card is-selected" role="radio" aria-checked="true" tabindex="0">
                            <span><i class="iconoir-bank" aria-hidden="true"></i></span>
                            <div><strong>{{ __('Bank Transfer') }}</strong><small>{{ __('Available · selected') }}</small></div>
                            <i class="iconoir-check-circle" aria-hidden="true"></i>
                        </div>
                        <div class="nd-sub-method-card is-disabled" role="radio" aria-checked="false" aria-disabled="true">
                            <span><i class="iconoir-credit-card" aria-hidden="true"></i></span>
                            <div><strong>{{ __('Stripe') }}</strong><small>{{ __('Coming soon') }}</small></div>
                            <span class="nd-sub-coming-soon">{{ __('Disabled') }}</span>
                        </div>
                    </div>
                </section>

                <section class="nd-sub-billing-card nd-sub-bank-instructions" aria-labelledby="bank-instructions-title">
                    <header><span class="nd-sub-billing-icon current" aria-hidden="true"><i class="iconoir-bank"></i></span><div><small>{{ __('Transfer instructions') }}</small><h3 id="bank-instructions-title">{{ __('Bank account details') }}</h3></div></header>
                    <p>{{ __('Transfer the exact plan amount to the account below, then submit your receipt for review.') }}</p>
                    <dl>
                        @foreach ($bankDetails as $label => $value)
                            <div>
                                <dt>{{ $label }}</dt><dd id="bank-value-{{ $loop->index }}">{{ $value }}</dd>
                                <button type="button" data-copy-target="bank-value-{{ $loop->index }}" aria-label="{{ __('Copy :label', ['label' => $label]) }}"><i class="iconoir-copy" aria-hidden="true"></i><span>{{ __('Copy') }}</span></button>
                            </div>
                        @endforeach
                    </dl>
                    @if ($bankInstructions)<div class="nd-sub-bank-note"><i class="iconoir-info-circle" aria-hidden="true"></i><p>{{ $bankInstructions }}</p></div>@endif
                </section>

                <section class="nd-sub-billing-card nd-sub-bank-form-card" aria-labelledby="bank-transfer-form-title">
                    <header><small>{{ __('Transfer details') }}</small><h3 id="bank-transfer-form-title">{{ __('Submit payment proof') }}</h3></header>
                    <div class="nd-sub-readonly-account" aria-label="{{ __('Billing account') }}">
                        <div><small>{{ __('Subscriber') }}</small><strong>{{ auth()->user()->name }}</strong></div>
                        <div><small>{{ __('Email') }}</small><strong>{{ auth()->user()->email }}</strong></div>
                        <div><small>{{ __('Selected plan') }}</small><strong>{{ $plan->name }}</strong></div>
                        <div><small>{{ __('Expected amount') }}</small><strong>{{ $plan->formattedPrice() }}</strong></div>
                    </div>

                    <form method="POST" action="{{ route('billing.bank-transfer.store', $plan) }}" enctype="multipart/form-data" data-payment-form data-single-submit>
                        @csrf
                        <div class="nd-sub-payment-form-grid">
                            <div class="nd-sub-form-field">
                                <label for="payer-name">{{ __('Payer name') }} <span aria-hidden="true">*</span></label>
                                <input class="form-control {{ $paymentErrors->has('payer_name') ? 'is-invalid' : '' }}" id="payer-name" name="payer_name" value="{{ old('payer_name', auth()->user()->name) }}" maxlength="255" required aria-describedby="payer-name-error">
                                @if ($paymentErrors->has('payer_name'))<div class="invalid-feedback" id="payer-name-error">{{ $paymentErrors->first('payer_name') }}</div>@endif
                            </div>
                            <div class="nd-sub-form-field">
                                <label for="payer-bank-name">{{ __('Payer bank name') }} <span aria-hidden="true">*</span></label>
                                <input class="form-control {{ $paymentErrors->has('payer_bank_name') ? 'is-invalid' : '' }}" id="payer-bank-name" name="payer_bank_name" value="{{ old('payer_bank_name') }}" maxlength="255" required aria-describedby="payer-bank-name-error">
                                @if ($paymentErrors->has('payer_bank_name'))<div class="invalid-feedback" id="payer-bank-name-error">{{ $paymentErrors->first('payer_bank_name') }}</div>@endif
                            </div>
                            <div class="nd-sub-form-field">
                                <label for="transaction-reference">{{ __('Transaction/reference number') }} <span aria-hidden="true">*</span></label>
                                <input class="form-control {{ $paymentErrors->has('transaction_reference') ? 'is-invalid' : '' }}" id="transaction-reference" name="transaction_reference" value="{{ old('transaction_reference') }}" maxlength="191" required aria-describedby="transaction-reference-error">
                                @if ($paymentErrors->has('transaction_reference'))<div class="invalid-feedback" id="transaction-reference-error">{{ $paymentErrors->first('transaction_reference') }}</div>@endif
                            </div>
                            <div class="nd-sub-form-field">
                                <label for="transferred-at">{{ __('Transfer date and time') }} <span aria-hidden="true">*</span></label>
                                <input class="form-control {{ $paymentErrors->has('transferred_at') ? 'is-invalid' : '' }}" id="transferred-at" name="transferred_at" type="datetime-local" value="{{ old('transferred_at', now()->format('Y-m-d\TH:i')) }}" required aria-describedby="transferred-at-error">
                                @if ($paymentErrors->has('transferred_at'))<div class="invalid-feedback" id="transferred-at-error">{{ $paymentErrors->first('transferred_at') }}</div>@endif
                            </div>
                            <div class="nd-sub-form-field">
                                <label for="submitted-amount">{{ __('Submitted amount') }} ({{ $plan->currency }}) <span aria-hidden="true">*</span></label>
                                <input class="form-control {{ $paymentErrors->has('submitted_amount') ? 'is-invalid' : '' }}" id="submitted-amount" name="submitted_amount" inputmode="decimal" value="{{ old('submitted_amount', $plan->priceDecimal()) }}" required aria-describedby="submitted-amount-help submitted-amount-error">
                                <small id="submitted-amount-help">{{ __('A different amount remains pending for Admin review.') }}</small>
                                @if ($paymentErrors->has('submitted_amount'))<div class="invalid-feedback" id="submitted-amount-error">{{ $paymentErrors->first('submitted_amount') }}</div>@endif
                            </div>
                            <div class="nd-sub-form-field nd-sub-payment-notes">
                                <label for="payment-notes">{{ __('Notes') }}</label>
                                <textarea class="form-control {{ $paymentErrors->has('notes') ? 'is-invalid' : '' }}" id="payment-notes" name="notes" rows="4" maxlength="2000" aria-describedby="payment-notes-help payment-notes-error">{{ old('notes') }}</textarea>
                                <small id="payment-notes-help">{{ __('Optional plain-text information for the reviewer.') }}</small>
                                @if ($paymentErrors->has('notes'))<div class="invalid-feedback" id="payment-notes-error">{{ $paymentErrors->first('notes') }}</div>@endif
                            </div>
                            <div class="nd-sub-form-field nd-sub-payment-proof">
                                <label for="payment-proof">{{ __('Payment proof') }} <span aria-hidden="true">*</span></label>
                                <input class="form-control {{ $paymentErrors->has('payment_proof') ? 'is-invalid' : '' }}" id="payment-proof" name="payment_proof" type="file" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" required aria-describedby="payment-proof-help payment-proof-error">
                                <small id="payment-proof-help">{{ __('Upload your bank receipt or transfer confirmation. Accepted: PDF, JPG, PNG, WebP. Maximum 10 MB.') }}</small>
                                @if ($paymentErrors->has('payment_proof'))<div class="invalid-feedback" id="payment-proof-error">{{ $paymentErrors->first('payment_proof') }}</div>@endif
                            </div>
                        </div>
                        <div class="form-check nd-sub-payment-confirmation">
                            <input class="form-check-input {{ $paymentErrors->has('confirmation') ? 'is-invalid' : '' }}" id="payment-confirmation" name="confirmation" type="checkbox" value="1" @checked(old('confirmation')) required>
                            <label class="form-check-label" for="payment-confirmation">{{ __('I confirm that the transfer details are accurate.') }}</label>
                            @if ($paymentErrors->has('confirmation'))<div class="invalid-feedback">{{ $paymentErrors->first('confirmation') }}</div>@endif
                        </div>
                        <div class="nd-sub-payment-actions">
                            <button class="nd-sub-primary-button" type="submit" data-loading-label="{{ __('Submitting…') }}">{{ __('Submit for review') }}</button>
                            <a href="{{ route('billing.index') }}">{{ __('Cancel') }}</a>
                        </div>
                    </form>
                </section>
            </div>

            <div class="col-xl-5">
                <aside class="nd-sub-billing-card nd-sub-order-summary" aria-labelledby="order-summary-title">
                    <header><small>{{ __('Order summary') }}</small><h3 id="order-summary-title">{{ $plan->name }}</h3><p>{{ $plan->description ?: __('No description provided.') }}</p></header>
                    <div class="nd-sub-order-price"><span>{{ __('Plan price') }}</span><strong>{{ $plan->formattedPrice() }}</strong><small>{{ $plan->interval->label() }}</small></div>
                    @if (count($plan->features ?? []) > 0)
                        <ul>@foreach ($plan->features as $feature)<li><i class="iconoir-check-circle" aria-hidden="true"></i>{{ $feature }}</li>@endforeach</ul>
                    @endif
                    <dl>@foreach (\App\Models\Plan::LIMITS as $key => $label)<div><dt>{{ __($label) }}</dt><dd>{{ $plan->hasUnlimitedLimit($key) ? __('Unlimited') : number_format($plan->limitFor($key)) }}</dd></div>@endforeach</dl>
                    <div class="nd-sub-order-total"><span>{{ __('Total due') }}</span><strong>{{ $plan->formattedPrice() }}</strong></div>
                    <p class="nd-sub-pending-notice"><i class="iconoir-clock" aria-hidden="true"></i>{{ __('Submission does not activate this plan. Activation occurs only after authorized review.') }}</p>
                </aside>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/subscriber-payments.js') }}"></script>
@endpush
