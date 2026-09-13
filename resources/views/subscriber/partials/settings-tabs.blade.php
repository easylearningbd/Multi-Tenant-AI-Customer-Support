@php($activeSettingsTab = $activeSettingsTab ?? (request()->routeIs('support-tickets.*') ? 'support' : 'profile'))

<nav class="nd-sub-settings-tabs" aria-label="{{ __('Settings sections') }}">
    <a class="{{ $activeSettingsTab === 'profile' ? 'active' : '' }}" href="{{ route('profile.edit') }}" @if ($activeSettingsTab === 'profile') aria-current="page" @endif><i class="iconoir-user" aria-hidden="true"></i>{{ __('Profile') }}</a>
    <span aria-disabled="true" title="{{ __('Two-factor authentication is not available yet.') }}"><i class="iconoir-shield-check" aria-hidden="true"></i>{{ __('2FA') }}</span>
    <span aria-disabled="true" title="{{ __('Notification settings are not available yet.') }}"><i class="iconoir-bell" aria-hidden="true"></i>{{ __('Notifications') }}</span>
    <a class="{{ $activeSettingsTab === 'support' ? 'active' : '' }}" href="{{ route('support-tickets.index') }}" @if ($activeSettingsTab === 'support') aria-current="page" @endif><i class="iconoir-lifebelt" aria-hidden="true"></i>{{ __('Support') }}</a>
</nav>
