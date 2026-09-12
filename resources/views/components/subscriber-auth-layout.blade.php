<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ __('Subscriber sign in') }} · {{ config('app.name', 'NeuralDesk') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-slate-900 antialiased">
        <main class="grid min-h-screen bg-white lg:grid-cols-[minmax(0,1.05fr)_minmax(30rem,0.95fr)]">
            <section class="relative hidden overflow-hidden bg-slate-950 px-12 py-10 text-white lg:flex lg:flex-col lg:justify-between xl:px-20 xl:py-14" aria-label="{{ __('NeuralDesk product overview') }}">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(37,99,235,0.32),transparent_35%),radial-gradient(circle_at_80%_65%,rgba(124,58,237,0.26),transparent_38%)]" aria-hidden="true"></div>
                <a href="/" class="relative w-fit rounded-xl focus:outline-none focus:ring-2 focus:ring-cyan-300 focus:ring-offset-4 focus:ring-offset-slate-950">
                    <x-neuraldesk-logo inverse />
                </a>
                <div class="relative max-w-xl space-y-8">
                    <div class="space-y-4">
                        <p class="text-sm font-semibold uppercase tracking-[0.22em] text-cyan-300">{{ __('AI customer support workspace') }}</p>
                        <h1 class="text-4xl font-semibold leading-tight tracking-tight xl:text-5xl">{{ __('Turn your knowledge into dependable support.') }}</h1>
                        <p class="max-w-lg text-lg leading-8 text-slate-300">{{ __('Train grounded assistants, review every conversation, and bring your team in whenever a customer needs a human.') }}</p>
                    </div>
                    <div class="max-w-md space-y-3 rounded-3xl border border-white/10 bg-white/5 p-6 shadow-2xl shadow-blue-950/30 backdrop-blur">
                        <div class="flex gap-3">
                            <span class="mt-1 size-8 shrink-0 rounded-full bg-gradient-to-br from-cyan-300 to-blue-500" aria-hidden="true"></span>
                            <div class="rounded-2xl rounded-tl-sm bg-white/10 px-4 py-3 text-sm leading-6 text-slate-200">{{ __('How can I help your customer today?') }}</div>
                        </div>
                        <div class="ml-auto max-w-xs rounded-2xl rounded-tr-sm bg-blue-600 px-4 py-3 text-sm leading-6 text-white">{{ __('Show me the conversations that need a human reply.') }}</div>
                    </div>
                </div>
                <p class="relative text-sm text-slate-400">{{ __('Grounded answers. Human control. One clear inbox.') }}</p>
            </section>
            <section class="flex min-h-screen items-center justify-center px-6 py-12 sm:px-10 lg:px-14">
                <div class="w-full max-w-md space-y-8">
                    <a href="/" class="inline-flex rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-4 lg:hidden">
                        <x-neuraldesk-logo />
                    </a>
                    {{ $slot }}
                </div>
            </section>
        </main>
    </body>
</html>
