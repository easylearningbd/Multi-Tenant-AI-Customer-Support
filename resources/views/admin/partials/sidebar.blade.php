@php
    $navigationGroups = [
        __('Management') => [
            ['label' => __('Users'), 'icon' => 'iconoir-group', 'route' => 'admin.users.index', 'pattern' => 'admin.users.*'],
            [
                'label' => __('Plans'),
                'icon' => 'iconoir-database',
                'id' => 'admin-plans-menu',
                'children' => [
                    ['label' => __('Plans'), 'route' => 'admin.plans.index', 'pattern' => 'admin.plans.*'],
                    ['label' => __('Subscriptions'), 'route' => 'admin.subscriptions.index', 'pattern' => 'admin.subscriptions.*'],
                ],
            ],
            ['label' => __('Contact'), 'icon' => 'iconoir-page', 'route' => 'admin.contact.index', 'pattern' => 'admin.contact.*'],
            ['label' => __('Notifications'), 'icon' => 'iconoir-bell-notification', 'route' => 'admin.notifications.index', 'pattern' => 'admin.notifications.*'],
            ['label' => __('Newsletter'), 'icon' => 'iconoir-journal', 'route' => 'admin.newsletter.index', 'pattern' => 'admin.newsletter.*'],
            ['label' => __('Support Tickets'), 'icon' => 'iconoir-chat-bubble', 'route' => 'admin.support-tickets.index', 'pattern' => 'admin.support-tickets.*'],
            ['label' => __('Blog'), 'icon' => 'iconoir-page', 'route' => 'admin.blog.index', 'pattern' => 'admin.blog.*'],
            [
                'label' => __('Payments'),
                'icon' => 'iconoir-credit-card',
                'id' => 'admin-payments-menu',
                'children' => [
                    ['label' => __('All Payments'), 'route' => 'admin.payments.index', 'pattern' => 'admin.payments.*'],
                    ['label' => __('Refunds'), 'route' => 'admin.refunds.index', 'pattern' => 'admin.refunds.*'],
                    ['label' => __('Webhook Logs'), 'route' => 'admin.webhooks.index', 'pattern' => 'admin.webhooks.*'],
                ],
            ],
        ],
        __('Staff Management') => [
            ['label' => __('Staffs'), 'icon' => 'iconoir-user', 'route' => 'admin.staff.index', 'pattern' => 'admin.staff.*'],
            ['label' => __('Roles'), 'icon' => 'iconoir-shield', 'route' => 'admin.roles.index', 'pattern' => 'admin.roles.*'],
        ],
        __('System') => [
            ['label' => __('Audit Logs'), 'icon' => 'iconoir-journal', 'route' => 'admin.audit-logs.index', 'pattern' => 'admin.audit-logs.*'],
            ['label' => __('Login Activity'), 'icon' => 'iconoir-clock', 'route' => 'admin.login-activity.index', 'pattern' => 'admin.login-activity.*'],
            ['label' => __('Languages'), 'icon' => 'iconoir-language', 'route' => 'admin.languages.index', 'pattern' => 'admin.languages.*'],
            ['label' => __('Settings'), 'icon' => 'iconoir-settings', 'route' => 'admin.settings.index', 'pattern' => 'admin.settings.*'],
        ],
    ];
@endphp

<aside class="startbar d-print-none" aria-label="{{ __('Super Admin navigation') }}">
    <div class="brand">
        <a class="logo" href="{{ route('admin.dashboard') }}" aria-label="{{ __('NeuralDesk Admin Dashboard') }}">
            <span class="nd-brand-mark" aria-hidden="true"><i class="iconoir-network"></i></span>
            <span class="nd-brand-name">Neural<span>Desk</span></span>
        </a>
    </div>

    <div class="startbar-menu">
        <div class="startbar-collapse" id="startbarCollapse" data-simplebar>
            <nav class="navbar-nav mb-auto" aria-label="{{ __('Admin sections') }}">
                <span class="nav-small-cap">{{ __('Main') }}</span>
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}" @if (request()->routeIs('admin.dashboard')) aria-current="page" @endif>
                    <i class="iconoir-home-simple menu-icon" aria-hidden="true"></i>
                    <span>{{ __('Dashboard') }}</span>
                </a>

                @foreach ($navigationGroups as $groupLabel => $items)
                    <span class="nav-small-cap">{{ $groupLabel }}</span>

                    @foreach ($items as $item)
                        @if (isset($item['children']))
                            @php
                                $parentActive = collect($item['children'])->contains(fn (array $child): bool => request()->routeIs($child['pattern']));
                            @endphp
                            <button class="nav-link nd-nav-parent {{ $parentActive ? 'active' : 'collapsed' }}" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $item['id'] }}" aria-expanded="{{ $parentActive ? 'true' : 'false' }}" aria-controls="{{ $item['id'] }}">
                                <i class="{{ $item['icon'] }} menu-icon" aria-hidden="true"></i>
                                <span>{{ $item['label'] }}</span>
                                <i class="iconoir-nav-arrow-down nd-nav-arrow" aria-hidden="true"></i>
                            </button>
                            <div class="collapse {{ $parentActive ? 'show' : '' }}" id="{{ $item['id'] }}">
                                <ul class="nav flex-column nd-submenu">
                                    @foreach ($item['children'] as $child)
                                        <li class="nav-item">
                                            @if (Route::has($child['route']))
                                                <a class="nav-link {{ request()->routeIs($child['pattern']) ? 'active' : '' }}" href="{{ route($child['route']) }}" @if (request()->routeIs($child['pattern'])) aria-current="page" @endif>{{ $child['label'] }}</a>
                                            @else
                                                <span class="nav-link disabled" aria-disabled="true" title="{{ __('This module is not available yet.') }}">{{ $child['label'] }}</span>
                                            @endif
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            @if (Route::has($item['route']))
                                <a class="nav-link {{ request()->routeIs($item['pattern']) ? 'active' : '' }}" href="{{ route($item['route']) }}" @if (request()->routeIs($item['pattern'])) aria-current="page" @endif>
                                    <i class="{{ $item['icon'] }} menu-icon" aria-hidden="true"></i>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @else
                                <span class="nav-link disabled" aria-disabled="true" title="{{ __('This module is not available yet.') }}">
                                    <i class="{{ $item['icon'] }} menu-icon" aria-hidden="true"></i>
                                    <span>{{ $item['label'] }}</span>
                                </span>
                            @endif
                        @endif
                    @endforeach
                @endforeach

                <span class="nav-small-cap">{{ __('Account') }}</span>
                @if (Route::has('admin.profile.edit'))
                    <a class="nav-link {{ request()->routeIs('admin.profile.*') ? 'active' : '' }}" href="{{ route('admin.profile.edit') }}" @if (request()->routeIs('admin.profile.*')) aria-current="page" @endif>
                        <i class="iconoir-user menu-icon" aria-hidden="true"></i>
                        <span>{{ __('Edit Profile') }}</span>
                    </a>
                @endif
                <form method="POST" action="{{ route('admin.logout') }}" class="nd-sidebar-logout">
                    @csrf
                    <button class="nav-link" type="submit">
                        <i class="iconoir-log-out menu-icon" aria-hidden="true"></i>
                        <span>{{ __('Logout') }}</span>
                    </button>
                </form>
            </nav>
        </div>
    </div>
</aside>
