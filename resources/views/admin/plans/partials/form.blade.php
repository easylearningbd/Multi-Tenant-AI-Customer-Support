@php
    use App\Enums\PlanInterval;
    use App\Models\Plan;

    $featuresValue = old('features', $plan->exists ? ($plan->features ?? []) : []);
    $featuresValue = is_array($featuresValue) ? implode(PHP_EOL, $featuresValue) : $featuresValue;
    $limits = $plan->exists ? ($plan->limits ?? []) : [];
    $selectedInterval = old('interval', $plan->interval?->value ?? PlanInterval::MONTHLY->value);
@endphp

<div class="row g-3 g-xxl-4 align-items-start">
    <div class="col-xl-8">
        <section class="card nd-card nd-plan-form-card mb-3 mb-xxl-4" aria-labelledby="plan-details-title">
            <div class="card-body">
                <h2 id="plan-details-title">{{ __('Plan Details') }}</h2>
                <p class="nd-plan-card-copy">{{ __('This is what administrators and subscribers see when comparing subscription options.') }}</p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="plan-name">{{ __('Name') }} <span aria-hidden="true">*</span></label>
                        <input class="form-control nd-form-control @error('name', 'planForm') is-invalid @enderror" id="plan-name" name="name" type="text" value="{{ old('name', $plan->name) }}" maxlength="100" required aria-describedby="plan-name-error" placeholder="{{ __('e.g. Growth Monthly') }}">
                        @error('name', 'planForm')<div class="invalid-feedback" id="plan-name-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="plan-slug">{{ __('Slug') }}</label>
                        <input class="form-control nd-form-control @error('slug', 'planForm') is-invalid @enderror" id="plan-slug" name="slug" type="text" value="{{ old('slug', $plan->slug) }}" maxlength="120" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" aria-describedby="plan-slug-help plan-slug-error">
                        <div class="form-text" id="plan-slug-help">{{ $plan->exists ? __('Leave blank to keep the current slug.') : __('Optional — generated from the name when left blank.') }}</div>
                        @error('slug', 'planForm')<div class="invalid-feedback" id="plan-slug-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="plan-description">{{ __('Description') }}</label>
                        <textarea class="form-control nd-form-control @error('description', 'planForm') is-invalid @enderror" id="plan-description" name="description" rows="4" maxlength="2000" aria-describedby="plan-description-error" placeholder="{{ __('Short plan summary shown on billing cards.') }}">{{ old('description', $plan->description) }}</textarea>
                        @error('description', 'planForm')<div class="invalid-feedback" id="plan-description-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="plan-features">{{ __('Features') }}</label>
                        <textarea class="form-control nd-form-control @error('features', 'planForm') is-invalid @enderror" id="plan-features" name="features" rows="6" aria-describedby="plan-features-help plan-features-error" placeholder="{{ __('One feature per line') }}">{{ $featuresValue }}</textarea>
                        <div class="form-text" id="plan-features-help">{{ __('Empty lines and duplicate feature names are removed.') }}</div>
                        @error('features', 'planForm')<div class="invalid-feedback" id="plan-features-error">{{ $message }}</div>@enderror
                        @error('features.*', 'planForm')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </section>

        <section class="card nd-card nd-plan-form-card" aria-labelledby="plan-limits-title">
            <div class="card-body">
                <h2 id="plan-limits-title">{{ __('Usage Limits') }}</h2>
                <p class="nd-plan-card-copy">{{ __('Use 0 for unlimited where the product should not block usage.') }}</p>
                <div class="row g-3">
                    @foreach (Plan::LIMITS as $key => $label)
                        <div class="col-md-6">
                            <label class="form-label" for="plan-{{ str_replace('_', '-', $key) }}">{{ __($label) }} <span aria-hidden="true">*</span></label>
                            <input class="form-control nd-form-control @error($key, 'planForm') is-invalid @enderror" id="plan-{{ str_replace('_', '-', $key) }}" name="{{ $key }}" type="number" value="{{ old($key, $limits[$key] ?? 0) }}" min="0" max="{{ config('plans.max_limit') }}" step="1" required aria-describedby="plan-{{ str_replace('_', '-', $key) }}-error">
                            @error($key, 'planForm')<div class="invalid-feedback" id="plan-{{ str_replace('_', '-', $key) }}-error">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    </div>

    <div class="col-xl-4">
        <section class="card nd-card nd-plan-form-card mb-3 mb-xxl-4" aria-labelledby="plan-pricing-title">
            <div class="card-body">
                <h2 id="plan-pricing-title">{{ __('Pricing') }}</h2>
                <p class="nd-plan-card-copy">{{ __('Set the subscription amount and renewal interval.') }}</p>

                @if (! empty($isReferenced))
                    <div class="alert alert-warning nd-plan-alert" role="status">{{ __('This plan has billing references. Commercial pricing fields cannot be changed.') }}</div>
                @endif

                <div class="mb-3">
                    <label class="form-label" for="plan-price">{{ __('Price') }} <span aria-hidden="true">*</span></label>
                    <input class="form-control nd-form-control @error('price', 'planForm') is-invalid @enderror" id="plan-price" name="price" type="text" inputmode="decimal" value="{{ old('price', $plan->exists ? $plan->priceDecimal() : '0.00') }}" required aria-describedby="plan-price-help plan-price-error">
                    <div class="form-text" id="plan-price-help">{{ __('Trial and contact-sales plans are stored with a zero payable price.') }}</div>
                    @error('price', 'planForm')<div class="invalid-feedback" id="plan-price-error">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="plan-currency">{{ __('Currency') }} <span aria-hidden="true">*</span></label>
                    <select class="form-select nd-form-control @error('currency', 'planForm') is-invalid @enderror" id="plan-currency" name="currency" required aria-describedby="plan-currency-error">
                        @foreach (config('plans.currencies') as $currency)
                            <option value="{{ $currency }}" @selected(old('currency', $plan->currency ?? 'USD') === $currency)>{{ $currency }}</option>
                        @endforeach
                    </select>
                    @error('currency', 'planForm')<div class="invalid-feedback" id="plan-currency-error">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="plan-interval">{{ __('Interval') }} <span aria-hidden="true">*</span></label>
                    <select class="form-select nd-form-control @error('interval', 'planForm') is-invalid @enderror" id="plan-interval" name="interval" required data-plan-interval aria-describedby="plan-interval-error">
                        @foreach (PlanInterval::cases() as $interval)
                            <option value="{{ $interval->value }}" @selected($selectedInterval === $interval->value)>{{ ucfirst($interval->value) }}</option>
                        @endforeach
                    </select>
                    @error('interval', 'planForm')<div class="invalid-feedback" id="plan-interval-error">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3 {{ $selectedInterval === PlanInterval::TRIAL->value ? '' : 'd-none' }}" data-trial-days-group>
                    <label class="form-label" for="plan-trial-days">{{ __('Trial days') }} <span aria-hidden="true">*</span></label>
                    <input class="form-control nd-form-control @error('trial_days', 'planForm') is-invalid @enderror" id="plan-trial-days" name="trial_days" type="number" value="{{ old('trial_days', $plan->trial_days) }}" min="1" max="365" step="1" data-trial-days @disabled($selectedInterval !== PlanInterval::TRIAL->value) aria-describedby="plan-trial-days-error">
                    @error('trial_days', 'planForm')<div class="invalid-feedback" id="plan-trial-days-error">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label class="form-label" for="plan-sort-order">{{ __('Sort order') }} <span aria-hidden="true">*</span></label>
                    <input class="form-control nd-form-control @error('sort_order', 'planForm') is-invalid @enderror" id="plan-sort-order" name="sort_order" type="number" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" min="0" max="4294967295" step="1" required aria-describedby="plan-sort-order-help plan-sort-order-error">
                    <div class="form-text" id="plan-sort-order-help">{{ __('Lower numbers appear first.') }}</div>
                    @error('sort_order', 'planForm')<div class="invalid-feedback" id="plan-sort-order-error">{{ $message }}</div>@enderror
                </div>
            </div>
        </section>

        <section class="card nd-card nd-plan-form-card mb-3 mb-xxl-4" aria-labelledby="plan-publishing-title">
            <div class="card-body">
                <h2 id="plan-publishing-title">{{ __('Publishing') }}</h2>
                <p class="nd-plan-card-copy">{{ __('Inactive plans stay in admin history and are excluded from new selections.') }}</p>

                <input name="is_active" type="hidden" value="0">
                <div class="form-check form-switch nd-plan-switch">
                    <input class="form-check-input" id="plan-is-active" name="is_active" type="checkbox" value="1" @checked((bool) old('is_active', $plan->exists ? $plan->is_active : true))>
                    <label class="form-check-label" for="plan-is-active">{{ __('Active plan') }}</label>
                </div>

                <input name="custom_pricing" type="hidden" value="0">
                <div class="form-check form-switch nd-plan-switch">
                    <input class="form-check-input" id="plan-custom-pricing" name="custom_pricing" type="checkbox" value="1" @checked((bool) old('custom_pricing', $plan->custom_pricing ?? false))>
                    <label class="form-check-label" for="plan-custom-pricing">{{ __('Custom pricing (contact sales)') }}</label>
                </div>
                <p class="form-text mb-0">{{ __('Disables automated checkout and presents a contact-sales action to future pricing surfaces.') }}</p>
            </div>
        </section>

        <section class="card nd-card nd-plan-action-card">
            <div class="card-body">
                <button class="btn nd-btn-primary w-100 justify-content-center" type="submit" data-submit-button>
                    <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-submit-spinner></span>
                    <span>{{ $submitLabel }}</span>
                </button>
                <a class="btn nd-btn-secondary w-100 mt-2" href="{{ route('admin.plans.index') }}">{{ __('Cancel') }}</a>
            </div>
        </section>
    </div>
</div>
