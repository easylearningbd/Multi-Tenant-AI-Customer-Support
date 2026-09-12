<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" data-startbar="light" data-bs-theme="light">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', __('Super Admin Dashboard')) · {{ config('app.name', 'NeuralDesk') }}</title>

        <link rel="stylesheet" href="{{ asset('theme/assets/css/bootstrap.min.css') }}">
        <link rel="stylesheet" href="{{ asset('theme/assets/css/icons.min.css') }}">
        <link rel="stylesheet" href="{{ asset('theme/assets/css/app.min.css') }}">
        <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
        @stack('styles')
    </head>
    <body class="admin-shell">
        <a class="nd-skip-link" href="#admin-main-content">{{ __('Skip to dashboard content') }}</a>

        @include('admin.partials.header')
        @include('admin.partials.sidebar')
        @include('admin.partials.toasts')

        <div class="startbar-overlay" aria-hidden="true"></div>

        <div class="page-wrapper">
            <main class="page-content" id="admin-main-content" tabindex="-1">
                <div class="container-fluid px-3 px-lg-4 px-xxl-5">
                    @yield('content')
                </div>

                @include('admin.partials.footer')
            </main>
        </div>

        <script src="{{ asset('theme/assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
        <script src="{{ asset('theme/assets/libs/simplebar/simplebar.min.js') }}"></script>
        <script src="{{ asset('theme/assets/js/pages/toast.init.js') }}"></script>
        @stack('vendor-scripts')
        <script src="{{ asset('theme/assets/js/app.js') }}"></script>
        <script src="{{ asset('js/admin-shell.js') }}"></script>
        @stack('scripts')
    </body>
</html>
