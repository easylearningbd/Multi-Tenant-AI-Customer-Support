@php
    $authenticatedAdmin = auth()->user();
    $adminName = (string) ($authenticatedAdmin?->name ?: __('Super Admin'));
    $adminInitials = $authenticatedAdmin?->initials() ?? 'A';
    $adminAvatarUrl = $authenticatedAdmin?->avatarUrl();
@endphp

<header class="topbar">
    <div class="container-fluid px-3 px-lg-4 px-xxl-5">
        <nav class="topbar-custom" id="topbar-custom" aria-label="{{ __('Admin utility navigation') }}">
            <ul class="topbar-item list-unstyled d-flex align-items-center mb-0">
                <li>
                    <button class="nav-link nav-icon mobile-menu-btn" id="togglemenu" type="button" aria-label="{{ __('Toggle admin sidebar') }}" aria-controls="startbarCollapse">
                        <i class="iconoir-menu-scale" aria-hidden="true"></i>
                    </button>
                </li>
                <li class="d-none d-md-block ms-2">
                    <div class="nd-header-search" title="{{ __('Admin search will be available when searchable modules are connected.') }}">
                        <i class="iconoir-search" aria-hidden="true"></i>
                        <label class="visually-hidden" for="admin-global-search">{{ __('Search Admin') }}</label>
                        <input id="admin-global-search" type="search" placeholder="{{ __('Search...') }}" disabled>
                        <kbd aria-hidden="true">Ctrl+K</kbd>
                    </div>
                </li>
            </ul>

            <ul class="topbar-item list-unstyled d-flex align-items-center gap-1 gap-md-2 mb-0 ms-auto">
                <li class="dropdown d-none d-lg-block">
                    <button class="nav-link dropdown-toggle arrow-none nav-icon-text" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('Select language') }}">
                        <i class="iconoir-language" aria-hidden="true"></i>
                        <span>{{ __('English') }}</span>
                        <i class="iconoir-nav-arrow-down nd-chevron" aria-hidden="true"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end nd-header-menu">
                        <span class="dropdown-item active" aria-current="true">{{ __('English') }}</span>
                        <span class="dropdown-item-text text-body-secondary small">{{ __('Additional languages are not configured.') }}</span>
                    </div>
                </li>
                <li>
                    <button class="nav-link nav-icon" id="light-dark-mode" type="button" aria-label="{{ __('Toggle color theme') }}">
                        <i class="iconoir-half-moon dark-mode" aria-hidden="true"></i>
                        <i class="iconoir-sun-light light-mode" aria-hidden="true"></i>
                    </button>
                </li>
                <li>
                    <button class="nav-link nav-icon position-relative" type="button" aria-label="{{ __('Notifications are not available yet') }}" title="{{ __('Notifications are not available yet') }}" disabled>
                        <i class="iconoir-bell" aria-hidden="true"></i>
                    </button>
                </li>
                <li class="dropdown">
                    <button class="nav-link dropdown-toggle arrow-none nd-profile-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        @if ($adminAvatarUrl)
                            <img class="nd-avatar nd-avatar-image" src="{{ $adminAvatarUrl }}" alt="{{ __('Profile image for :name', ['name' => $adminName]) }}">
                        @else
                            <span class="nd-avatar" aria-hidden="true">{{ $adminInitials }}</span>
                        @endif
                        <span class="d-none d-md-flex flex-column align-items-start lh-sm">
                            <span class="nd-profile-name">{{ $adminName }}</span>
                            <span class="nd-profile-role">{{ __('Super Admin') }}</span>
                        </span>
                        <i class="iconoir-nav-arrow-down nd-chevron d-none d-md-inline" aria-hidden="true"></i>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end nd-header-menu">
                        <h6 class="dropdown-header text-truncate">{{ $adminName }}</h6>
                        <a class="dropdown-item" href="{{ route('admin.dashboard') }}">
                            <i class="iconoir-home-simple me-2" aria-hidden="true"></i>{{ __('Dashboard') }}
                        </a>
                        @if (Route::has('admin.profile.edit'))
                            <a class="dropdown-item" href="{{ route('admin.profile.edit') }}">
                                <i class="iconoir-user me-2" aria-hidden="true"></i>{{ __('Edit Profile') }}
                            </a>
                        @endif
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button class="dropdown-item text-danger" type="submit">
                                <i class="iconoir-log-out me-2" aria-hidden="true"></i>{{ __('Logout') }}
                            </button>
                        </form>
                    </div>
                </li>
            </ul>
        </nav>
    </div>
</header>
