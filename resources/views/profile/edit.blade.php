@extends('subscriber.layouts.app')

@section('title', __('Settings'))

@section('content')
    @php
        $avatarUrl = $user->avatarUrl();
        $profileErrors = $errors->getBag('updateProfile');
        $passwordErrors = $errors->getBag('updatePassword');
    @endphp

    <div class="nd-sub-settings-page">
        @include('subscriber.partials.settings-tabs', ['activeSettingsTab' => 'profile'])

        <section class="row g-3 nd-sub-account-status" aria-label="{{ __('Account status') }}">
            @foreach ([
                ['icon' => 'iconoir-mail', 'tone' => 'success', 'label' => __('Email'), 'value' => $user->email_verified_at ? __('Verified') : __('Unverified'), 'valueClass' => $user->email_verified_at ? 'text-success' : 'text-warning'],
                ['icon' => 'iconoir-phone', 'tone' => 'purple', 'label' => __('Phone'), 'value' => $user->phone ?: __('Add phone'), 'valueClass' => ''],
                ['icon' => 'iconoir-shield-check', 'tone' => 'purple', 'label' => __('2FA'), 'value' => __('Unavailable'), 'valueClass' => ''],
                ['icon' => 'iconoir-lifebelt', 'tone' => 'cyan', 'label' => __('Support'), 'value' => __('Unavailable'), 'valueClass' => ''],
            ] as $status)
                <div class="col-12 col-sm-6 col-xl-3">
                    <article class="nd-sub-status-card">
                        <span class="nd-sub-status-icon {{ $status['tone'] }}"><i class="{{ $status['icon'] }}" aria-hidden="true"></i></span>
                        <span><small>{{ $status['label'] }}</small><strong class="{{ $status['valueClass'] }}">{{ $status['value'] }}</strong></span>
                    </article>
                </div>
            @endforeach
        </section>

        <div class="row g-4 nd-sub-settings-columns">
            <div class="col-12 col-xl-6">
                <section class="nd-sub-settings-card" id="profile-settings" aria-labelledby="profile-settings-title">
                    <header><h2 id="profile-settings-title">{{ __('Profile') }}</h2><p>{{ __('How you appear across the workspace.') }}</p></header>

                    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" data-single-submit>
                        @csrf
                        @method('PATCH')

                        <div class="nd-sub-avatar-editor">
                            <span class="nd-sub-avatar-preview" data-avatar-preview>
                                @if ($avatarUrl)
                                    <img src="{{ $avatarUrl }}" alt="{{ __('Profile photo for :name', ['name' => $user->name]) }}">
                                @else
                                    <span aria-hidden="true">{{ $user->initials() }}</span>
                                @endif
                            </span>
                            <div>
                                <strong>{{ __('Profile photo') }}</strong>
                                <p>{{ __('JPEG, PNG, or WebP. Maximum 2 MB.') }}</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <label class="nd-sub-photo-button" for="avatar">{{ $avatarUrl ? __('Change photo') : __('Upload photo') }}</label>
                                    <input class="visually-hidden" id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" data-avatar-input aria-describedby="avatar-help avatar-error">
                                    @if ($avatarUrl)<button class="nd-sub-photo-remove" type="button" data-bs-toggle="modal" data-bs-target="#remove-avatar-modal">{{ __('Remove photo') }}</button>@endif
                                </div>
                                <small id="avatar-help" data-avatar-file-name>{{ __('No file selected') }}</small>
                                @if ($profileErrors->has('avatar'))<div class="invalid-feedback d-block" id="avatar-error">{{ $profileErrors->first('avatar') }}</div>@endif
                            </div>
                        </div>

                        @foreach ([
                            ['name' => 'name', 'type' => 'text', 'label' => __('Full name'), 'icon' => 'iconoir-user', 'value' => old('name', $user->name), 'required' => true, 'autocomplete' => 'name', 'help' => null],
                            ['name' => 'email', 'type' => 'email', 'label' => __('Email address'), 'icon' => 'iconoir-mail', 'value' => old('email', $user->email), 'required' => true, 'autocomplete' => 'email', 'help' => __('Changing your email resets its verification status.')],
                            ['name' => 'phone', 'type' => 'tel', 'label' => __('Phone number'), 'icon' => 'iconoir-phone', 'value' => old('phone', $user->phone), 'required' => false, 'autocomplete' => 'tel', 'help' => __('Use an international-friendly format, including the country code when possible.')],
                        ] as $field)
                            <div class="nd-sub-form-field">
                                <label for="{{ $field['name'] }}">{{ $field['label'] }} @if ($field['required'])<span aria-hidden="true">*</span>@endif</label>
                                <div class="nd-sub-input-wrap"><i class="{{ $field['icon'] }}" aria-hidden="true"></i><input class="form-control {{ $profileErrors->has($field['name']) ? 'is-invalid' : '' }}" id="{{ $field['name'] }}" name="{{ $field['name'] }}" type="{{ $field['type'] }}" value="{{ $field['value'] }}" maxlength="{{ $field['name'] === 'phone' ? 32 : 255 }}" @required($field['required']) autocomplete="{{ $field['autocomplete'] }}" @if ($field['name'] === 'phone') placeholder="+14155550100" @endif aria-describedby="{{ $field['name'] }}-help {{ $field['name'] }}-error"></div>
                                @if ($field['help'])<small id="{{ $field['name'] }}-help">{{ $field['help'] }}</small>@endif
                                @if ($profileErrors->has($field['name']))<div class="invalid-feedback d-block" id="{{ $field['name'] }}-error">{{ $profileErrors->first($field['name']) }}</div>@endif
                            </div>
                        @endforeach

                        <button class="nd-sub-dark-button" type="submit" data-loading-label="{{ __('Saving…') }}">{{ __('Save profile') }}</button>
                    </form>
                </section>
            </div>

            <div class="col-12 col-xl-6">
                <section class="nd-sub-settings-card" id="password-settings" aria-labelledby="password-settings-title">
                    <header><h2 id="password-settings-title">{{ __('Change password') }}</h2><p>{{ __('Use at least 8 characters. You will stay logged in here.') }}</p></header>

                    <form method="POST" action="{{ route('password.update') }}" data-single-submit>
                        @csrf
                        @method('PUT')

                        @foreach ([
                            ['name' => 'current_password', 'label' => __('Current password'), 'autocomplete' => 'current-password', 'placeholder' => __('Enter current password')],
                            ['name' => 'password', 'label' => __('New password'), 'autocomplete' => 'new-password', 'placeholder' => __('8+ characters')],
                            ['name' => 'password_confirmation', 'label' => __('Confirm new password'), 'autocomplete' => 'new-password', 'placeholder' => __('Repeat the new password')],
                        ] as $passwordField)
                            <div class="nd-sub-form-field">
                                <label for="{{ $passwordField['name'] }}">{{ $passwordField['label'] }} <span aria-hidden="true">*</span></label>
                                <div class="nd-sub-input-wrap nd-sub-password-wrap">
                                    <i class="iconoir-lock" aria-hidden="true"></i>
                                    <input class="form-control {{ $passwordErrors->has($passwordField['name']) ? 'is-invalid' : '' }}" id="{{ $passwordField['name'] }}" name="{{ $passwordField['name'] }}" type="password" required autocomplete="{{ $passwordField['autocomplete'] }}" placeholder="{{ $passwordField['placeholder'] }}" aria-describedby="{{ $passwordField['name'] }}-error">
                                    <button type="button" data-password-toggle="{{ $passwordField['name'] }}" aria-label="{{ __('Show :field', ['field' => strtolower($passwordField['label'])]) }}" aria-pressed="false"><i class="iconoir-eye" aria-hidden="true"></i></button>
                                </div>
                                @if ($passwordErrors->has($passwordField['name']))<div class="invalid-feedback d-block" id="{{ $passwordField['name'] }}-error">{{ $passwordErrors->first($passwordField['name']) }}</div>@endif
                            </div>
                        @endforeach

                        <button class="nd-sub-dark-button" type="submit" data-loading-label="{{ __('Updating…') }}">{{ __('Update password') }}</button>
                    </form>
                </section>
            </div>
        </div>
    </div>

    @if ($avatarUrl)
        <div class="modal fade" id="remove-avatar-modal" tabindex="-1" aria-labelledby="remove-avatar-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content nd-sub-confirm-modal">
                    <div class="modal-header"><h2 class="modal-title fs-5" id="remove-avatar-title">{{ __('Remove profile photo?') }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div>
                    <div class="modal-body">{{ __('Your initials will be shown in place of the current photo.') }}</div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button><form method="POST" action="{{ route('profile.avatar.destroy') }}" data-single-submit>@csrf @method('DELETE')<button class="btn btn-danger" type="submit">{{ __('Remove photo') }}</button></form></div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/subscriber-profile.js') }}"></script>
@endpush
