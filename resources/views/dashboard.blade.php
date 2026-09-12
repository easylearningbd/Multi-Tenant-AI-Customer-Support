<x-app-layout>
    <x-slot name="header">
        <h1 class="text-xl font-semibold leading-tight text-gray-800">
            {{ __('Subscriber Dashboard') }}
        </h1>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <section class="overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-gray-200 sm:p-8">
                <div class="flex flex-col items-start justify-between gap-6 sm:flex-row sm:items-center">
                    <div class="space-y-2">
                        <p class="text-sm font-medium text-blue-600">{{ __('Subscriber workspace') }}</p>
                        <h2 class="text-2xl font-semibold tracking-tight text-gray-950">{{ __('Welcome, :name', ['name' => Auth::user()->name]) }}</h2>
                        <p class="text-sm text-gray-600">{{ __('Your NeuralDesk workspace modules will appear here in the next phase.') }}</p>
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                            {{ __('Log out') }}
                        </button>
                    </form>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
