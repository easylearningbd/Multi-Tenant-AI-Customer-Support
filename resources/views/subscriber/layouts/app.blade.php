<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', __('Overview')) · {{ config('app.name', 'NeuralDesk') }}</title>

        <link rel="stylesheet" href="{{ asset('theme/assets/css/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('theme/assets/css/icons.min.css') }}">
        <link rel="stylesheet" href="{{ asset('theme/assets/css/app.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/subscriber-dashboard.css') }}">
        @stack('styles')
    </head>
    <body class="nd-subscriber-shell">
        <a class="nd-sub-skip-link" href="#subscriber-main-content">{{ __('Skip to main content') }}</a>

        @include('subscriber.partials.sidebar')
        @include('subscriber.partials.header')
        <button class="nd-sub-sidebar-overlay" type="button" data-subscriber-sidebar-close aria-label="{{ __('Close navigation') }}" tabindex="-1"></button>

        <div class="nd-sub-page-wrapper">
            <main class="nd-sub-page-content" id="subscriber-main-content" tabindex="-1">
                <div class="container-fluid px-3 px-md-4 px-xxl-5">
                    @yield('content')
                </div>
            </main>
        </div>

        @include('admin.partials.toasts')

        <script src="{{ asset('theme/assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('theme/assets/libs/simplebar/simplebar.min.js') }}"></script>
        <script src="{{ asset('theme/assets/js/pages/toast.init.js') }}"></script>
        @stack('vendor-scripts')
        <script src="{{ asset('js/subscriber-dashboard.js') }}"></script>
        @stack('scripts')
    </body>
</html>
