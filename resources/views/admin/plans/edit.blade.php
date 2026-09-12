@extends('admin.layouts.app')

@section('title', __('Edit Plan'))

@section('content')
    <div class="nd-page-heading nd-page-heading-actions">
        <div><h1>{{ __('Edit Plan') }}</h1><p>{{ __('Update plan presentation, pricing, and platform limits.') }}</p></div>
        <a class="btn nd-btn-secondary" href="{{ route('admin.plans.show', $plan) }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.plans.update', $plan) }}" data-submit-once>
        @csrf
        @method('PUT')
        @include('admin.plans.partials.form', ['submitLabel' => __('Update Plan')])
    </form>
@endsection

@push('scripts')<script src="{{ asset('js/admin-plans.js') }}"></script>@endpush
