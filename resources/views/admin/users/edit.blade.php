@extends('admin.layouts.app')

@section('title', __('Edit User'))

@php
    $avatarUrl = $subscriber->avatarUrl();
    $avatarMaxMegabytes = max(1, (int) ceil(config('admin.profile.avatar_max_kilobytes') / 1024));
@endphp

@section('content')
    <div class="nd-page-heading nd-page-heading-actions">
        <div><h1>{{ __('Edit User') }}</h1><p>{{ __('Update the subscriber’s permitted profile information.') }}</p></div>
        <a class="btn nd-btn-secondary" href="{{ route('admin.users.show', $subscriber) }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back to details') }}</a>
    </div>

    <div class="row justify-content-center"><div class="col-xl-8">
        <section class="card nd-card nd-profile-card" aria-labelledby="edit-user-title"><div class="card-body">
            <div class="nd-section-heading">
                <span class="nd-section-icon nd-tone-primary" aria-hidden="true"><i class="iconoir-user"></i></span>
                <div><h2 id="edit-user-title">{{ __('Profile Information') }}</h2><p>{{ __('Changes apply only to this subscriber account. The platform role cannot be edited here.') }}</p></div>
            </div>

            <div class="nd-current-profile">
                <span class="nd-profile-avatar" id="profile-avatar-preview">
                    <img id="profile-avatar-image" class="{{ $avatarUrl ? '' : 'd-none' }}" src="{{ $avatarUrl ?? '' }}" alt="{{ __('Profile image preview') }}">
                    <span id="profile-avatar-initials" class="{{ $avatarUrl ? 'd-none' : '' }}" aria-hidden="true">{{ $subscriber->initials() }}</span>
                </span>
                <div><strong>{{ $subscriber->name }}</strong><span>{{ __('Subscriber account') }}</span></div>
            </div>

            <form method="POST" action="{{ route('admin.users.update', $subscriber) }}" enctype="multipart/form-data" data-submit-once>
                @csrf
                @method('PUT')

                <div class="mb-3">
                    <label class="form-label" for="subscriber-name">{{ __('Name') }} <span aria-hidden="true">*</span></label>
                    <input class="form-control nd-form-control @error('name', 'userUpdate') is-invalid @enderror" id="subscriber-name" name="name" type="text" value="{{ old('name', $subscriber->name) }}" maxlength="255" autocomplete="name" required aria-describedby="subscriber-name-error">
                    @error('name', 'userUpdate')<div class="invalid-feedback" id="subscriber-name-error">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="subscriber-email">{{ __('Email address') }} <span aria-hidden="true">*</span></label>
                    <input class="form-control nd-form-control @error('email', 'userUpdate') is-invalid @enderror" id="subscriber-email" name="email" type="email" value="{{ old('email', $subscriber->email) }}" maxlength="255" autocomplete="email" required aria-describedby="subscriber-email-error">
                    @error('email', 'userUpdate')<div class="invalid-feedback" id="subscriber-email-error">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label" for="subscriber-phone">{{ __('Phone number') }}</label>
                    <input class="form-control nd-form-control @error('phone', 'userUpdate') is-invalid @enderror" id="subscriber-phone" name="phone" type="tel" value="{{ old('phone', $subscriber->phone) }}" maxlength="32" autocomplete="tel" placeholder="{{ __('Enter an international phone number') }}" aria-describedby="subscriber-phone-help subscriber-phone-error">
                    <div class="form-text" id="subscriber-phone-help">{{ __('Use an international-friendly format, for example +1 202 555 0123.') }}</div>
                    @error('phone', 'userUpdate')<div class="invalid-feedback" id="subscriber-phone-error">{{ $message }}</div>@enderror
                </div>
                <div class="mb-4">
                    <label class="form-label" for="subscriber-avatar">{{ __('Profile image') }}</label>
                    <label class="nd-avatar-upload @error('avatar', 'userUpdate') is-invalid @enderror" for="subscriber-avatar">
                        <input id="subscriber-avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="subscriber-avatar-help subscriber-avatar-error" data-user-avatar-input>
                        <i class="iconoir-cloud-upload" aria-hidden="true"></i>
                        <strong>{{ __('Drag and drop an image here or browse') }}</strong>
                        <span id="subscriber-avatar-help">{{ __('JPG, JPEG, PNG, or WEBP up to :size MB.', ['size' => $avatarMaxMegabytes]) }}</span>
                        <span class="nd-selected-file" data-selected-file aria-live="polite"></span>
                    </label>
                    @error('avatar', 'userUpdate')<div class="invalid-feedback d-block" id="subscriber-avatar-error">{{ $message }}</div>@enderror
                </div>
                <div class="nd-form-actions">
                    <button class="btn nd-btn-primary" type="submit" data-submit-button><span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-submit-spinner></span><span>{{ __('Update User') }}</span></button>
                    <a class="btn nd-btn-secondary" href="{{ route('admin.users.show', $subscriber) }}">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div></section>
    </div></div>
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-users.js') }}"></script>
@endpush
