<x-subscriber-auth-layout>
    <div class="space-y-2">
        <p class="text-sm font-semibold text-blue-600">{{ __('Welcome back') }}</p>
        <h1 class="text-3xl font-semibold tracking-tight text-slate-950">{{ __('Sign in to NeuralDesk') }}</h1>
        <p class="text-sm leading-6 text-slate-500">{{ __('Continue to your customer support workspace.') }}</p>
    </div>

    <x-auth-session-status class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div class="space-y-2">
            <x-input-label for="email" :value="__('Work email')" class="font-semibold text-slate-700" />
            <x-text-input id="email" class="block w-full rounded-xl border-slate-300 px-4 py-3 shadow-sm focus:border-blue-600 focus:ring-blue-600" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="space-y-2">
            <x-input-label for="password" :value="__('Password')" class="font-semibold text-slate-700" />
            <x-text-input id="password" class="block w-full rounded-xl border-slate-300 px-4 py-3 shadow-sm focus:border-blue-600 focus:ring-blue-600" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600">
                <input id="remember_me" type="checkbox" class="rounded border-slate-300 text-blue-600 shadow-sm focus:ring-blue-600" name="remember" value="1">
                <span>{{ __('Keep me logged in') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="rounded text-sm font-semibold text-blue-700 hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2" href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-slate-950 px-5 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
            {{ __('Log in') }}
        </button>
    </form>

    <p class="text-center text-sm text-slate-600">
        {{ __('New to NeuralDesk?') }}
        <a href="{{ route('register') }}" class="rounded font-semibold text-blue-700 hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
            {{ __('Create a free workspace') }}
        </a>
    </p>
</x-subscriber-auth-layout>
