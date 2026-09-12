@extends('admin.layouts.app')

@section('title', __('Create Plan'))

@section('content')
    <div class="nd-page-heading nd-page-heading-actions">
        <div><h1>{{ __('Create Plan') }}</h1><p>{{ __('Configure pricing, feature bullets, and usage limits for subscriber plans.') }}</p></div>
        <a class="btn nd-btn-secondary" href="{{ route('admin.plans.index') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back') }}</a>
    </div>

    <form method="POST" action="{{ route('admin.plans.store') }}" data-submit-once>
        @csrf
        @include('admin.plans.partials.form', ['submitLabel' => __('Create Plan')])
    </form>
@endsection

@push('scripts')<script src="{{ asset('js/admin-plans.js') }}"></script>@endpush
