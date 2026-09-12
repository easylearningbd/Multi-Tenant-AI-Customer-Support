@extends('admin.layouts.app')

@section('title', __('Edit Profile'))

@php
    $avatarUrl = $admin->avatarUrl();
    $avatarMaxMegabytes = max(1, (int) ceil(config('admin.profile.avatar_max_kilobytes') / 1024));
@endphp

@section('content')
    <div class="nd-page-heading">
        <h1>{{ __('Edit Profile') }}</h1>
        <p>{{ __('Manage your administrator identity and account password.') }}</p>
    </div>

    <div class="row g-3 g-xxl-4 align-items-start">
        <div class="col-xl-6">
            <section class="card nd-card nd-profile-card" id="profile-information" aria-labelledby="profile-information-title">
                <div class="card-body">
                    <div class="nd-section-heading">
                        <span class="nd-section-icon nd-tone-primary" aria-hidden="true"><i class="iconoir-user"></i></span>
                        <div>
                            <h2 id="profile-information-title">{{ __('Profile Information') }}</h2>
                            <p>{{ __('Update your personal details and profile image.') }}</p>
                        </div>
                    </div>

                    <div class="nd-current-profile">
                        <span class="nd-profile-avatar" id="profile-avatar-preview">
                            <img id="profile-avatar-image" class="{{ $avatarUrl ? '' : 'd-none' }}" src="{{ $avatarUrl ?? '' }}" alt="{{ __('Profile image preview') }}">
                            <span id="profile-avatar-initials" class="{{ $avatarUrl ? 'd-none' : '' }}" aria-hidden="true">{{ $admin->initials() }}</span>
                        </span>
                        <div>
                            <strong>{{ $admin->name }}</strong>
                            <span>{{ __('Super Admin account') }}</span>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.profile.update') }}" enctype="multipart/form-data" data-submit-once>
                        @csrf
                        @method('PATCH')

                        <div class="mb-3">
                            <label class="form-label" for="admin-profile-name">{{ __('Name') }} <span aria-hidden="true">*</span></label>
                            <input class="form-control nd-form-control @error('name', 'profileUpdate') is-invalid @enderror" id="admin-profile-name" name="name" type="text" value="{{ old('name', $admin->name) }}" maxlength="255" autocomplete="name" required aria-describedby="admin-profile-name-error">
                            @error('name', 'profileUpdate')
                                <div class="invalid-feedback" id="admin-profile-name-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="admin-profile-email">{{ __('Email address') }} <span aria-hidden="true">*</span></label>
                            <input class="form-control nd-form-control @error('email', 'profileUpdate') is-invalid @enderror" id="admin-profile-email" name="email" type="email" value="{{ old('email', $admin->email) }}" maxlength="255" autocomplete="email" required aria-describedby="admin-profile-email-error">
                            @error('email', 'profileUpdate')
                                <div class="invalid-feedback" id="admin-profile-email-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="admin-profile-phone">{{ __('Phone number') }}</label>
                            <input class="form-control nd-form-control @error('phone', 'profileUpdate') is-invalid @enderror" id="admin-profile-phone" name="phone" type="tel" value="{{ old('phone', $admin->phone) }}" maxlength="32" autocomplete="tel" placeholder="{{ __('Enter an international phone number') }}" aria-describedby="admin-profile-phone-help admin-profile-phone-error">
                            <div class="form-text" id="admin-profile-phone-help">{{ __('Use an international-friendly format, for example +1 202 555 0123.') }}</div>
                            @error('phone', 'profileUpdate')
                                <div class="invalid-feedback" id="admin-profile-phone-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="admin-profile-avatar">{{ __('Profile image') }}</label>
                            <label class="nd-avatar-upload @error('avatar', 'profileUpdate') is-invalid @enderror" for="admin-profile-avatar">
                                <input id="admin-profile-avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" aria-describedby="admin-profile-avatar-help admin-profile-avatar-error">
                                <i class="iconoir-cloud-upload" aria-hidden="true"></i>
                                <strong>{{ __('Drag and drop an image here or browse') }}</strong>
                                <span id="admin-profile-avatar-help">{{ __('JPG, JPEG, PNG, or WEBP up to :size MB.', ['size' => $avatarMaxMegabytes]) }}</span>
                                <span class="nd-selected-file" data-selected-file aria-live="polite"></span>
                            </label>
                            @error('avatar', 'profileUpdate')
                                <div class="invalid-feedback d-block" id="admin-profile-avatar-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="nd-form-actions">
                            <button class="btn nd-btn-primary" type="submit" data-submit-button>
                                <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-submit-spinner></span>
                                <span data-submit-label>{{ __('Update Profile') }}</span>
                            </button>
                            @if ($avatarUrl)
                                <button class="btn nd-btn-danger-outline" type="button" data-bs-toggle="modal" data-bs-target="#remove-avatar-modal">
                                    <i class="iconoir-trash" aria-hidden="true"></i>{{ __('Remove image') }}
                                </button>
                            @endif
                        </div>
                    </form>
                </div>
            </section>
        </div>

        <div class="col-xl-6">
            <section class="card nd-card nd-profile-card" id="change-password" aria-labelledby="change-password-title">
                <div class="card-body">
                    <div class="nd-section-heading">
                        <span class="nd-section-icon nd-tone-warning" aria-hidden="true"><i class="iconoir-key"></i></span>
                        <div>
                            <h2 id="change-password-title">{{ __('Change Password') }}</h2>
                            <p>{{ __('Use a strong password to keep your account secure.') }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.profile.password.update') }}" data-submit-once>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label class="form-label" for="admin-current-password">{{ __('Current password') }} <span aria-hidden="true">*</span></label>
                            <div class="input-group nd-password-group @error('current_password', 'passwordUpdate') is-invalid @enderror">
                                <input class="form-control nd-form-control @error('current_password', 'passwordUpdate') is-invalid @enderror" id="admin-current-password" name="current_password" type="password" autocomplete="current-password" required aria-describedby="admin-current-password-error">
                                <button class="btn nd-password-toggle" type="button" data-password-toggle="admin-current-password" data-show-label="{{ __('Show current password') }}" data-hide-label="{{ __('Hide current password') }}" aria-label="{{ __('Show current password') }}" aria-controls="admin-current-password"><i class="iconoir-eye" aria-hidden="true"></i></button>
                            </div>
                            @error('current_password', 'passwordUpdate')
                                <div class="invalid-feedback d-block" id="admin-current-password-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="admin-new-password">{{ __('New password') }} <span aria-hidden="true">*</span></label>
                            <div class="input-group nd-password-group @error('password', 'passwordUpdate') is-invalid @enderror">
                                <input class="form-control nd-form-control @error('password', 'passwordUpdate') is-invalid @enderror" id="admin-new-password" name="password" type="password" autocomplete="new-password" required aria-describedby="admin-new-password-help admin-new-password-error">
                                <button class="btn nd-password-toggle" type="button" data-password-toggle="admin-new-password" data-show-label="{{ __('Show new password') }}" data-hide-label="{{ __('Hide new password') }}" aria-label="{{ __('Show new password') }}" aria-controls="admin-new-password"><i class="iconoir-eye" aria-hidden="true"></i></button>
                            </div>
                            <div class="form-text" id="admin-new-password-help">{{ __('Use at least 8 characters and avoid your current password.') }}</div>
                            @error('password', 'passwordUpdate')
                                <div class="invalid-feedback d-block" id="admin-new-password-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label class="form-label" for="admin-password-confirmation">{{ __('Confirm new password') }} <span aria-hidden="true">*</span></label>
                            <div class="input-group nd-password-group">
                                <input class="form-control nd-form-control" id="admin-password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                                <button class="btn nd-password-toggle" type="button" data-password-toggle="admin-password-confirmation" data-show-label="{{ __('Show password confirmation') }}" data-hide-label="{{ __('Hide password confirmation') }}" aria-label="{{ __('Show password confirmation') }}" aria-controls="admin-password-confirmation"><i class="iconoir-eye" aria-hidden="true"></i></button>
                            </div>
                        </div>

                        <div class="nd-form-actions">
                            <button class="btn nd-btn-primary" type="submit" data-submit-button>
                                <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-submit-spinner></span>
                                <span data-submit-label>{{ __('Update Password') }}</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>

    @if ($avatarUrl)
        <div class="modal fade" id="remove-avatar-modal" tabindex="-1" aria-labelledby="remove-avatar-modal-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content nd-confirm-modal">
                    <div class="modal-header">
                        <h2 class="modal-title fs-5" id="remove-avatar-modal-title">{{ __('Remove profile image?') }}</h2>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">{{ __('Your saved profile image will be removed and your initials will be displayed instead.') }}</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <form method="POST" action="{{ route('admin.profile.avatar.destroy') }}" data-submit-once>
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-danger" type="submit" data-submit-button>
                                <span class="spinner-border spinner-border-sm d-none" aria-hidden="true" data-submit-spinner></span>
                                <span data-submit-label>{{ __('Remove image') }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/admin-profile.js') }}"></script>
@endpush
