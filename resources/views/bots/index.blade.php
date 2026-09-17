@extends('subscriber.layouts.app')

@section('title', __('Bots'))
@section('header-title', __('Bots'))
@section('header-subtitle', __('Each bot has its own knowledge, behavior, and deployment settings.'))

@section('header-actions')
    <button
        class="nd-sub-manage-bots"
        type="button"
        @if ($capacity['canCreate']) data-bs-toggle="modal" data-bs-target="#create-bot-modal" @else disabled aria-describedby="bot-capacity-message" @endif
    >
        {{ __('New bot') }}<i class="iconoir-plus" aria-hidden="true"></i>
    </button>
@endsection

@section('content')
    @php($createBotErrors = $errors->getBag('createBot'))

    <div class="nd-sub-bots-page">
        <div class="nd-sub-bots-summary">
            <div>
                <span>{{ __('Workspace bot capacity') }}</span>
                <strong>{{ $capacity['label'] }}</strong>
            </div>
            @unless ($capacity['canCreate'])
                <p id="bot-capacity-message">
                    @if ($capacity['limit'] === null)
                        {{ __('Choose an active plan before creating a bot.') }}
                    @else
                        {{ __('Your current plan bot limit has been reached.') }}
                    @endif
                    <a href="{{ route('billing.index') }}">{{ __('Review billing') }}</a>
                </p>
            @endunless
        </div>

        @if ($bots->isEmpty())
            <section class="nd-sub-bots-empty" aria-labelledby="empty-bots-title">
                <span aria-hidden="true"><i class="iconoir-brain-electricity"></i></span>
                <h2 id="empty-bots-title">{{ __('Create your first support bot') }}</h2>
                <p>{{ __('Give your assistant a name, then configure its behavior and add trusted knowledge.') }}</p>
                <button
                    class="nd-sub-bot-gradient-button"
                    type="button"
                    @if ($capacity['canCreate']) data-bs-toggle="modal" data-bs-target="#create-bot-modal" @else disabled aria-describedby="bot-capacity-message" @endif
                >
                    {{ __('New bot') }}<i class="iconoir-arrow-right" aria-hidden="true"></i>
                </button>
            </section>
        @else
            <section class="nd-sub-bot-grid" aria-label="{{ __('Your bots') }}">
                @foreach ($bots as $bot)
                    <article class="nd-sub-bot-card">
                        <header>
                            <span class="nd-sub-bot-icon" aria-hidden="true"><i class="iconoir-brain-electricity"></i></span>
                            <div>
                                <h2>{{ $bot->name }}</h2>
                                <p>{{ $bot->slug }}</p>
                            </div>
                            <span class="nd-sub-bot-state {{ $bot->is_active ? 'is-live' : 'is-draft' }}">
                                <i class="iconoir-{{ $bot->is_active ? 'check-circle' : 'clock' }}" aria-hidden="true"></i>
                                {{ $bot->is_active ? __('Live') : __('Draft') }}
                            </span>
                        </header>

                        <dl class="nd-sub-bot-metrics">
                            <div><dt>{{ __('Sources') }}</dt><dd>{{ number_format($bot->knowledge_sources_count) }}</dd></div>
                            <div><dt>{{ __('Widgets') }}</dt><dd>0</dd></div>
                            <div><dt>{{ __('Status') }}</dt><dd>{{ $bot->is_active ? __('On') : __('Off') }}</dd></div>
                        </dl>

                        <footer>
                            <a href="{{ route('bots.settings.edit', $bot) }}"><i class="iconoir-settings" aria-hidden="true"></i>{{ __('Settings') }}</a>
                            <a href="{{ route('bots.training.index', $bot) }}"><i class="iconoir-book" aria-hidden="true"></i>{{ __('Train') }}</a>
                            <button class="icon-only" type="button" disabled aria-label="{{ __('Embed controls are not available yet') }}" title="{{ __('Embed controls are not available yet') }}"><i class="iconoir-code" aria-hidden="true"></i></button>
                        </footer>
                    </article>
                @endforeach
            </section>

            @if ($bots->hasPages())
                <div class="nd-sub-bot-pagination">{{ $bots->links('pagination::bootstrap-5') }}</div>
            @endif
        @endif
    </div>

    <div
        class="modal fade"
        id="create-bot-modal"
        tabindex="-1"
        aria-labelledby="create-bot-title"
        aria-hidden="true"
        data-open-on-load="{{ $createBotErrors->isNotEmpty() ? 'true' : 'false' }}"
    >
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content nd-sub-create-bot-modal">
                <div class="modal-header">
                    <div><h2 class="modal-title" id="create-bot-title">{{ __('Create a new bot') }}</h2><p>{{ __('Name it, then set it up and train it.') }}</p></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                </div>
                <form method="POST" action="{{ route('bots.store') }}" data-submit-once>
                    @csrf
                    <div class="modal-body">
                        <label for="bot-name">{{ __('Bot name') }} <span aria-hidden="true">*</span></label>
                        <input
                            class="form-control @if ($createBotErrors->has('name')) is-invalid @endif"
                            id="bot-name"
                            name="name"
                            type="text"
                            maxlength="{{ config('neuraldesk.bots.limits.name') }}"
                            value="{{ old('name') }}"
                            placeholder="{{ __('e.g. Sales Assistant') }}"
                            required
                            @if ($createBotErrors->has('name')) aria-invalid="true" aria-describedby="bot-name-error" @endif
                        >
                        @if ($createBotErrors->has('name'))<div class="invalid-feedback" id="bot-name-error">{{ $createBotErrors->first('name') }}</div>@endif
                        @if ($createBotErrors->has('plan_limit'))<div class="nd-sub-bot-limit-error" role="alert">{{ $createBotErrors->first('plan_limit') }}</div>@endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="nd-sub-modal-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="nd-sub-bot-gradient-button" @disabled(! $capacity['canCreate'])>
                            <span>{{ __('Create bot') }}</span><i class="iconoir-arrow-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modalElement = document.getElementById('create-bot-modal');

            if (modalElement?.dataset.openOnLoad === 'true') {
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }

            modalElement?.addEventListener('shown.bs.modal', () => {
                document.getElementById('bot-name')?.focus();
            });

            document.querySelectorAll('[data-submit-once]').forEach((form) => {
                form.addEventListener('submit', () => {
                    const submit = form.querySelector('button[type="submit"]');

                    if (submit) {
                        submit.disabled = true;
                        submit.setAttribute('aria-disabled', 'true');
                    }
                });
            });
        });
    </script>
@endpush
