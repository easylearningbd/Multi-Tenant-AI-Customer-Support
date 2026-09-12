@extends('admin.layouts.app')

@section('title', __('Plan Details'))

@section('content')
    <div class="nd-plan-detail-header">
        <div>
            <span class="nd-section-icon nd-tone-primary" aria-hidden="true"><i class="iconoir-database"></i></span>
            <div><h1>{{ $plan->name }}</h1><p>{{ $plan->slug }}</p><span class="nd-status-badge {{ $plan->is_active ? 'nd-status-success' : 'nd-status-muted' }}">{{ $plan->is_active ? __('Active') : __('Inactive') }}</span></div>
        </div>
        <div class="nd-detail-actions">
            <a class="btn nd-btn-primary" href="{{ route('admin.plans.edit', $plan) }}"><i class="iconoir-edit-pencil" aria-hidden="true"></i>{{ __('Edit') }}</a>
            <a class="btn nd-btn-secondary" href="{{ route('admin.plans.index') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back') }}</a>
            @unless ($isReferenced)
                <button class="btn nd-btn-danger-outline" type="button" data-bs-toggle="modal" data-bs-target="#delete-plan-modal"><i class="iconoir-trash" aria-hidden="true"></i>{{ __('Delete') }}</button>
            @endunless
        </div>
    </div>

    <div class="row g-3 g-xxl-4">
        <div class="col-xl-7">
            <section class="card nd-card nd-plan-detail-card mb-3 mb-xxl-4" aria-labelledby="plan-overview-title"><div class="card-body">
                <h2 id="plan-overview-title">{{ __('Plan Overview') }}</h2>
                <dl class="nd-plan-detail-grid">
                    <div><dt>{{ __('Description') }}</dt><dd>{{ $plan->description ?: __('Not provided') }}</dd></div>
                    <div><dt>{{ __('Price') }}</dt><dd>{{ $plan->formattedPrice() }}</dd></div>
                    <div><dt>{{ __('Currency') }}</dt><dd>{{ $plan->currency }}</dd></div>
                    <div><dt>{{ __('Interval') }}</dt><dd>{{ ucfirst($plan->interval->value) }}</dd></div>
                    @if ($plan->interval === \App\Enums\PlanInterval::TRIAL)<div><dt>{{ __('Trial duration') }}</dt><dd>{{ trans_choice(':count day|:count days', $plan->trial_days, ['count' => $plan->trial_days]) }}</dd></div>@endif
                    <div><dt>{{ __('Custom pricing') }}</dt><dd>{{ $plan->custom_pricing ? __('Enabled') : __('Disabled') }}</dd></div>
                    <div><dt>{{ __('Sort order') }}</dt><dd>{{ $plan->sort_order }}</dd></div>
                    <div><dt>{{ __('Created') }}</dt><dd>{{ $plan->created_at->timezone(config('app.timezone'))->format('d M, Y \a\t h:i A') }}</dd></div>
                    <div><dt>{{ __('Last updated') }}</dt><dd>{{ $plan->updated_at->timezone(config('app.timezone'))->format('d M, Y \a\t h:i A') }}</dd></div>
                </dl>
            </div></section>

            <section class="card nd-card nd-plan-detail-card" aria-labelledby="plan-features-title"><div class="card-body">
                <h2 id="plan-features-title">{{ __('Features') }}</h2>
                @forelse ($plan->features ?? [] as $feature)<div class="nd-plan-feature"><i class="iconoir-check-circle" aria-hidden="true"></i><span>{{ $feature }}</span></div>@empty<div class="nd-inline-empty">{{ __('No marketing features configured.') }}</div>@endforelse
            </div></section>
        </div>
        <div class="col-xl-5">
            <section class="card nd-card nd-plan-detail-card" aria-labelledby="plan-usage-title"><div class="card-body">
                <h2 id="plan-usage-title">{{ __('Usage Limits') }}</h2>
                <dl class="nd-plan-limit-list">
                    @foreach (\App\Models\Plan::LIMITS as $key => $label)
                        <div><dt>{{ __($label) }}</dt><dd>{{ $plan->hasUnlimitedLimit($key) ? __('Unlimited') : number_format($plan->limitFor($key)) }}</dd></div>
                    @endforeach
                </dl>
            </div></section>
        </div>
    </div>

    @unless ($isReferenced)
        <div class="modal fade" id="delete-plan-modal" tabindex="-1" aria-labelledby="delete-plan-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content nd-confirm-modal">
            <div class="modal-header"><h2 class="modal-title fs-5" id="delete-plan-title">{{ __('Delete plan') }}</h2><button class="btn-close btn-close-white" type="button" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
            <div class="modal-body"><p>{{ __('Delete :plan permanently?', ['plan' => $plan->name]) }}</p><p class="mb-0 text-muted">{{ __('The server will recheck billing references before deletion.') }}</p></div>
            <div class="modal-footer"><button class="btn nd-btn-secondary" type="button" data-bs-dismiss="modal">{{ __('Cancel') }}</button><form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" data-submit-once>@csrf @method('DELETE')<button class="btn nd-btn-danger" type="submit" data-submit-button>{{ __('Delete plan') }}</button></form></div>
        </div></div></div>
    @endunless
@endsection

@push('scripts')<script src="{{ asset('js/admin-plans.js') }}"></script>@endpush
