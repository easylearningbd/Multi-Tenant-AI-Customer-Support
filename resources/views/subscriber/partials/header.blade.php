@php
    $botRoute = collect(['bots.index', 'subscriber.bots.index'])->first(fn (string $route): bool => Route::has($route));
@endphp

<header class="nd-sub-topbar">
    <div class="nd-sub-topbar-title">
        <button type="button" data-subscriber-sidebar-toggle aria-label="{{ __('Toggle workspace navigation') }}" aria-controls="subscriber-sidebar" aria-expanded="false"><i class="iconoir-menu-scale" aria-hidden="true"></i></button>
        <div><h1>{{ __('Overview') }}</h1><p>{{ $dashboard['workspaceName'] }}</p></div>
    </div>
    <div class="nd-sub-topbar-actions">
        <button class="nd-sub-notification" type="button" disabled aria-label="{{ __('Notifications are not available yet') }}" title="{{ __('Notifications are not available yet') }}"><i class="iconoir-bell" aria-hidden="true"></i></button>
        @if ($botRoute)
            <a class="nd-sub-manage-bots" href="{{ route($botRoute) }}">{{ __('Manage bots') }}<i class="iconoir-brain-electricity" aria-hidden="true"></i></a>
        @else
            <button class="nd-sub-manage-bots" type="button" disabled title="{{ __('Bot management is not available yet.') }}">{{ __('Manage bots') }}<i class="iconoir-brain-electricity" aria-hidden="true"></i></button>
        @endif
    </div>
</header>
