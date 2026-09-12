@extends('admin.layouts.app')

@section('title', __('User Details'))

@php
    $avatarUrl = $subscriber->avatarUrl();
    $verified = $subscriber->email_verified_at !== null;
    $formattedCreatedAt = $subscriber->created_at?->timezone(config('app.timezone'));
    $formattedUpdatedAt = $subscriber->updated_at?->timezone(config('app.timezone'));
@endphp

@section('content')
    <div class="nd-user-detail-header">
        <div class="nd-user-detail-identity">
            <span class="nd-user-detail-avatar">
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="{{ __('Profile image for :name', ['name' => $subscriber->name]) }}">
                @else
                    <span aria-hidden="true">{{ $subscriber->initials() }}</span>
                @endif
            </span>
            <div>
                <h1>{{ $subscriber->name }}</h1>
                <p>{{ $subscriber->email }}</p>
                <span class="nd-status-badge nd-status-info">{{ $subscriber->role->value }}</span>
            </div>
        </div>
        <div class="nd-detail-actions">
            <a class="btn nd-btn-primary" href="{{ route('admin.users.edit', $subscriber) }}"><i class="iconoir-edit-pencil" aria-hidden="true"></i>{{ __('Edit') }}</a>
            <a class="btn nd-btn-secondary" href="{{ route('admin.users.index') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back') }}</a>
        </div>
    </div>

    <div class="row g-3 g-xxl-4 mb-3 mb-xxl-4">
        <div class="col-md-6">
            <section class="card nd-card nd-user-summary-card" aria-labelledby="email-summary-title"><div class="card-body">
                <span class="nd-section-icon {{ $verified ? 'nd-tone-success' : 'nd-tone-warning' }}" aria-hidden="true"><i class="iconoir-mail"></i></span>
                <p id="email-summary-title">{{ __('Email verification') }}</p>
                <strong class="{{ $verified ? 'text-success' : 'text-warning' }}">{{ $verified ? __('Verified') : __('Unverified') }}</strong>
            </div></section>
        </div>
        <div class="col-md-6">
            <section class="card nd-card nd-user-summary-card" aria-labelledby="role-summary-title"><div class="card-body">
                <span class="nd-section-icon nd-tone-primary" aria-hidden="true"><i class="iconoir-group"></i></span>
                <p id="role-summary-title">{{ __('Assigned platform roles') }}</p>
                <strong>1</strong>
            </div></section>
        </div>
    </div>

    <div class="row g-3 g-xxl-4">
        <div class="col-xl-6">
            <section class="card nd-card nd-detail-card" aria-labelledby="personal-info-title"><div class="card-body">
                <h2 id="personal-info-title"><i class="iconoir-user" aria-hidden="true"></i>{{ __('Personal Information') }}</h2>
                <dl>
                    <div><dt>{{ __('Full name') }}</dt><dd>{{ $subscriber->name }}</dd></div>
                    <div><dt>{{ __('Email address') }}</dt><dd>{{ $subscriber->email }}</dd></div>
                    <div><dt>{{ __('Phone number') }}</dt><dd>{{ $subscriber->phone ?: __('Not provided') }}</dd></div>
                    <div><dt>{{ __('Member since') }}</dt><dd>{{ $formattedCreatedAt?->format('d M, Y') ?? __('N/A') }}</dd></div>
                </dl>
            </div></section>
        </div>
        <div class="col-xl-6">
            <section class="card nd-card nd-detail-card" aria-labelledby="security-info-title"><div class="card-body">
                <h2 id="security-info-title"><i class="iconoir-shield" aria-hidden="true"></i>{{ __('Security & Verification') }}</h2>
                <dl>
                    <div><dt>{{ __('Email verification') }}</dt><dd><span class="nd-status-badge {{ $verified ? 'nd-status-success' : 'nd-status-warning' }}">{{ $verified ? __('Verified') : __('Pending') }}</span></dd></div>
                    <div><dt>{{ __('Assigned platform role') }}</dt><dd><span class="nd-status-badge nd-status-info">{{ $subscriber->role->value }}</span></dd></div>
                </dl>
            </div></section>
        </div>
        <div class="col-xl-6">
            <section class="card nd-card nd-detail-card" aria-labelledby="activity-info-title"><div class="card-body">
                <h2 id="activity-info-title"><i class="iconoir-clock" aria-hidden="true"></i>{{ __('Activity Log') }}</h2>
                <dl><div><dt>{{ __('Account created') }}</dt><dd>{{ $formattedCreatedAt?->format('d M, Y \a\t h:i A') ?? __('N/A') }}</dd></div></dl>
            </div></section>
        </div>
        <div class="col-xl-6">
            <section class="card nd-card nd-detail-card" aria-labelledby="additional-info-title"><div class="card-body">
                <h2 id="additional-info-title"><i class="iconoir-info-circle" aria-hidden="true"></i>{{ __('Additional Information') }}</h2>
                <dl>
                    <div><dt>{{ __('User ID') }}</dt><dd>#{{ $subscriber->id }}</dd></div>
                    <div><dt>{{ __('Last updated') }}</dt><dd>{{ $formattedUpdatedAt?->format('d M, Y \a\t h:i A') ?? __('N/A') }}</dd></div>
                </dl>
            </div></section>
        </div>
    </div>
@endsection
