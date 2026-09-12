@php
    $subscriber = auth()->user();
    $avatarUrl = $subscriber?->avatarUrl();
    $navigation = [
        ['label' => __('Overview'), 'icon' => 'iconoir-view-grid', 'route' => 'dashboard'],
        ['label' => __('Live visitors'), 'icon' => 'iconoir-antenna-signal', 'route' => 'live-visitors.index'],
        ['label' => __('Bots'), 'icon' => 'iconoir-brain-electricity', 'route' => 'bots.index'],
        ['label' => __('Conversations'), 'icon' => 'iconoir-chat-bubble', 'route' => 'conversations.index'],
        ['label' => __('Team'), 'icon' => 'iconoir-community', 'route' => 'team.index'],
        ['label' => __('Billing'), 'icon' => 'iconoir-credit-card', 'route' => 'billing.index'],
        ['label' => __('Settings'), 'icon' => 'iconoir-settings', 'route' => 'profile.edit'],
    ];
@endphp

<aside class="nd-sub-sidebar" id="subscriber-sidebar" aria-label="{{ __('Subscriber Dashboard navigation') }}">
    <div class="nd-sub-brand">
        <a href="{{ route('dashboard') }}" aria-label="{{ __('NeuralDesk overview') }}">
            <span class="nd-sub-brand-mark" aria-hidden="true"><i class="iconoir-network"></i></span>
            <span>Neural<span>Desk</span></span>
        </a>
        <button type="button" data-subscriber-sidebar-close aria-label="{{ __('Close navigation') }}"><i class="iconoir-xmark" aria-hidden="true"></i></button>
    </div>

    <nav class="nd-sub-navigation" aria-label="{{ __('Workspace navigation') }}">
        @foreach ($navigation as $item)
            @if (Route::has($item['route']))
                <a class="{{ request()->routeIs($item['route']) ? 'active' : '' }}" href="{{ route($item['route']) }}" @if (request()->routeIs($item['route'])) aria-current="page" @endif>
                    <i class="{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
                </a>
            @else
                <span class="disabled" aria-disabled="true" title="{{ __('This module is not available yet.') }}">
                    <i class="{{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span>
                </span>
            @endif
        @endforeach
    </nav>

    <div class="nd-sub-account">
        <span class="nd-sub-account-avatar">
            @if ($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="">
            @else
                <span aria-hidden="true">{{ $subscriber?->initials() ?? 'U' }}</span>
            @endif
        </span>
        <span class="nd-sub-account-copy"><strong>{{ $subscriber?->name }}</strong><small>{{ $dashboard['planName'] }}</small></span>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" aria-label="{{ __('Log out') }}" title="{{ __('Log out') }}"><i class="iconoir-log-out" aria-hidden="true"></i></button>
        </form>
    </div>
</aside>
