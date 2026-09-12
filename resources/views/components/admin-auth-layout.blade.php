<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ __('Admin sign in') }} · {{ config('app.name', 'NeuralDesk') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-100 antialiased">
        <main class="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-950 px-5 py-12 sm:px-8">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(59,130,246,0.18),transparent_38%),radial-gradient(circle_at_85%_80%,rgba(124,58,237,0.16),transparent_32%)]" aria-hidden="true"></div>
            <div class="relative w-full max-w-md space-y-8">
                <div class="flex justify-center">
                    <a href="/admin/login" class="rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-4 focus:ring-offset-slate-950">
                        <x-neuraldesk-logo inverse />
                    </a>
                </div>
                <section class="rounded-3xl border border-white/10 bg-white/[0.06] p-6 shadow-2xl shadow-black/30 backdrop-blur sm:p-9">
                    {{ $slot }}
                </section>
                <p class="text-center text-xs leading-5 text-slate-500">{{ __('Restricted system. Authorized administrators only.') }}</p>
            </div>
        </main>
    </body>
</html>
