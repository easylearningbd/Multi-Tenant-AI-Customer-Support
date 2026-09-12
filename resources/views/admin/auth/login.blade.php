<x-admin-auth-layout>
    <div class="space-y-2 text-center">
        <div class="mx-auto grid size-12 place-items-center rounded-2xl bg-blue-500/15 text-blue-300 ring-1 ring-blue-400/20" aria-hidden="true">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M7 10V8a5 5 0 0110 0v2m-9 0h8a2 2 0 012 2v6a2 2 0 01-2 2H8a2 2 0 01-2-2v-6a2 2 0 012-2z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" /></svg>
        </div>
        <h1 class="text-2xl font-semibold tracking-tight text-white">{{ __('Secure admin access') }}</h1>
        <p class="text-sm leading-6 text-slate-400">{{ __('Sign in with your authorized platform administrator account.') }}</p>
    </div>
    <x-auth-session-status class="mt-6 rounded-xl bg-emerald-400/10 px-4 py-3 text-sm text-emerald-200 ring-1 ring-emerald-400/20" :status="session('status')" />
    <form method="POST" action="{{ route('admin.login.store') }}" class="mt-7 space-y-5" x-data="{ showPassword: false }">
        @csrf
        <div class="space-y-2">
            <label for="email" class="block text-sm font-semibold text-slate-200">{{ __('Email address') }}</label>
            <input id="email" class="block w-full rounded-xl border-white/10 bg-slate-900/80 px-4 py-3 text-white shadow-sm placeholder:text-slate-600 focus:border-blue-400 focus:ring-blue-400" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            <x-input-error :messages="$errors->get('email')" class="text-red-300" />
        </div>
        <div class="space-y-2">
            <label for="password" class="block text-sm font-semibold text-slate-200">{{ __('Password') }}</label>
            <div class="relative">
                <input id="password" class="block w-full rounded-xl border-white/10 bg-slate-900/80 px-4 py-3 pr-20 text-white shadow-sm placeholder:text-slate-600 focus:border-blue-400 focus:ring-blue-400" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="current-password">
                <button type="button" class="absolute inset-y-0 right-0 rounded-r-xl px-4 text-xs font-semibold text-slate-400 hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-400" @click="showPassword = ! showPassword" :aria-pressed="showPassword.toString()">
                    <span x-show="! showPassword">{{ __('Show') }}</span><span x-show="showPassword" style="display: none;">{{ __('Hide') }}</span><span class="sr-only"> {{ __('password') }}</span>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="text-red-300" />
        </div>
        <label for="admin_remember" class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-300">
            <input id="admin_remember" type="checkbox" class="rounded border-white/20 bg-slate-900 text-blue-500 shadow-sm focus:ring-blue-400 focus:ring-offset-slate-950" name="remember" value="1">
            <span>{{ __('Remember me on this device') }}</span>
        </label>
        <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-blue-600 px-5 py-3.5 text-sm font-semibold text-white shadow-lg shadow-blue-950/30 transition hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-300 focus:ring-offset-2 focus:ring-offset-slate-950">{{ __('Sign in securely') }}</button>
    </form>
</x-admin-auth-layout>
