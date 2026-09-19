@extends('subscriber.layouts.app')

@section('title', __('Embed & Share'))
@section('header-title', __('Embed & Share'))
@section('header-subtitle', $bot->display_name)

@section('header-actions')
    <a class="nd-sub-settings-action secondary" href="{{ route('bots.index') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back') }}</a>
    <span class="nd-sub-widget-status {{ $widget->isLive() ? 'is-ready' : 'is-offline' }}">
        <i class="iconoir-{{ $widget->isLive() ? 'check-circle' : 'pause' }}" aria-hidden="true"></i>
        {{ $widget->isLive() ? __('Widget configured') : __('Widget offline') }}
    </span>
@endsection

@section('content')
    @php
        $appearanceErrors = $errors->getBag('widgetAppearance');
        $selectedColor = old('accent_color', $widget->accent_color);
        $selectedPosition = old('position', $widget->position->value);
        $welcomeMessage = old('welcome_message', $widget->welcome_message);
        $enabled = filter_var(old('is_enabled', $widget->is_enabled), FILTER_VALIDATE_BOOL);
    @endphp

    <div class="nd-sub-embed-page" data-widget-settings>
        <nav class="nd-sub-embed-breadcrumb" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ route('bots.index') }}">{{ __('Bots') }}</a><span aria-hidden="true">/</span><strong>{{ $bot->display_name }}</strong>
        </nav>

        <div class="nd-sub-embed-layout">
            <div class="nd-sub-embed-main">
                <section class="nd-sub-embed-card" aria-labelledby="embed-code-title">
                    <header class="nd-sub-embed-card-heading">
                        <div>
                            <h2 id="embed-code-title">{{ __('Embed code') }}</h2>
                            <p>{{ __('Paste this before the closing body tag on any page.') }}</p>
                        </div>
                        <button class="nd-sub-embed-copy" type="button" data-copy-target="widget-embed-code" data-copy-label="{{ __('Copy') }}" data-copied-label="{{ __('Copied') }}">
                            <i class="iconoir-copy" aria-hidden="true"></i><span>{{ __('Copy') }}</span>
                        </button>
                    </header>

                    <textarea class="nd-sub-embed-code" id="widget-embed-code" rows="4" readonly spellcheck="false" aria-label="{{ __('Widget embed code') }}">{{ $embedCode }}</textarea>
                    <p class="nd-sub-embed-help">{{ __('Works on any website that permits this script and is listed in Allowed website origins.') }}</p>

                    @if ($demoAvailable)
                        <a class="nd-sub-embed-gradient" href="{{ $demoUrl }}" target="_blank" rel="noopener noreferrer">{{ __('See it live on a demo site') }}<i class="iconoir-arrow-right" aria-hidden="true"></i></a>
                    @else
                        <span class="nd-sub-embed-gradient is-disabled" aria-disabled="true" title="{{ __('Public widget delivery is not enabled yet.') }}">{{ __('Demo site coming soon') }}<i class="iconoir-clock" aria-hidden="true"></i></span>
                    @endif
                </section>

                <section class="nd-sub-embed-card" aria-labelledby="appearance-title">
                    <header class="nd-sub-embed-card-heading">
                        <div><h2 id="appearance-title">{{ __('Appearance') }}</h2><p>{{ __('Choose how the launcher appears on customer sites.') }}</p></div>
                    </header>

                    <form method="POST" action="{{ route('bots.embed.update', $bot) }}" data-widget-form>
                        @csrf
                        @method('PUT')

                        <fieldset class="nd-sub-widget-fieldset">
                            <legend>{{ __('Accent color') }}</legend>
                            <div class="nd-sub-color-options">
                                @foreach ($accentColors as $color => $label)
                                    <label class="nd-sub-color-option" title="{{ __($label) }}">
                                        <input type="radio" name="accent_color" value="{{ $color }}" @checked($selectedColor === $color)>
                                        <span style="--nd-widget-swatch: {{ $color }}" aria-hidden="true"></span>
                                        <span class="visually-hidden">{{ __($label) }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if ($appearanceErrors->has('accent_color'))<div class="nd-sub-settings-error" role="alert">{{ $appearanceErrors->first('accent_color') }}</div>@endif
                        </fieldset>

                        <fieldset class="nd-sub-widget-fieldset">
                            <legend>{{ __('Position') }}</legend>
                            <div class="nd-sub-position-options">
                                @foreach (\App\Enums\WidgetPosition::cases() as $position)
                                    <label>
                                        <input type="radio" name="position" value="{{ $position->value }}" @checked($selectedPosition === $position->value)>
                                        <span>{{ $position->label() }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @if ($appearanceErrors->has('position'))<div class="nd-sub-settings-error" role="alert">{{ $appearanceErrors->first('position') }}</div>@endif
                        </fieldset>

                        <div class="nd-sub-settings-field">
                            <label for="widget-welcome-message">{{ __('Welcome message') }}</label>
                            <input class="form-control @if ($appearanceErrors->has('welcome_message')) is-invalid @endif" id="widget-welcome-message" name="welcome_message" type="text" maxlength="{{ config('neuraldesk.widgets.welcome_message_max') }}" value="{{ $welcomeMessage }}" required @if ($appearanceErrors->has('welcome_message')) aria-invalid="true" aria-describedby="widget-welcome-error" @endif>
                            @if ($appearanceErrors->has('welcome_message'))<div class="invalid-feedback" id="widget-welcome-error">{{ $appearanceErrors->first('welcome_message') }}</div>@endif
                        </div>

                        <div class="nd-sub-widget-enabled">
                            <div><strong>{{ __('Widget enabled') }}</strong><small>{{ __('The bot must also be active before public delivery can answer visitors.') }}</small></div>
                            <div class="form-check form-switch">
                                <input type="hidden" name="is_enabled" value="0">
                                <input class="form-check-input" id="widget-enabled" name="is_enabled" type="checkbox" value="1" role="switch" @checked($enabled)>
                                <label class="visually-hidden" for="widget-enabled">{{ __('Widget enabled') }}</label>
                            </div>
                        </div>
                        @if ($appearanceErrors->has('is_enabled'))<div class="nd-sub-settings-error" role="alert">{{ $appearanceErrors->first('is_enabled') }}</div>@endif

                        <div class="nd-sub-settings-field">
                            <label for="widget-allowed-origins">{{ __('Allowed website origins') }}</label>
                            <small>{{ __('One complete HTTP or HTTPS origin per line. Hosted chat and the NeuralDesk demo are always allowed.') }}</small>
                            <textarea class="form-control @if ($appearanceErrors->has('allowed_origins') || $appearanceErrors->has('allowed_origins.*')) is-invalid @endif" id="widget-allowed-origins" name="allowed_origins_text" rows="4" placeholder="https://support.example.com">{{ old('allowed_origins_text', $allowedOriginsText) }}</textarea>
                            @if ($appearanceErrors->has('allowed_origins'))<div class="invalid-feedback">{{ $appearanceErrors->first('allowed_origins') }}</div>@endif
                            @if ($appearanceErrors->has('allowed_origins.*'))<div class="invalid-feedback d-block">{{ $appearanceErrors->first('allowed_origins.*') }}</div>@endif
                        </div>

                        <button class="nd-sub-embed-save" type="submit">{{ __('Save changes') }}</button>
                    </form>
                </section>
            </div>

            <aside class="nd-sub-embed-side">
                <section class="nd-sub-embed-card" aria-labelledby="hosted-chat-title">
                    <header class="nd-sub-embed-card-heading"><div><h2 id="hosted-chat-title">{{ __('Hosted chat page') }}</h2><p>{{ __('Share this stable link without changing a website.') }}</p></div></header>
                    <div class="nd-sub-hosted-url">
                        <i class="iconoir-globe" aria-hidden="true"></i>
                        <input id="hosted-chat-url" type="text" value="{{ $hostedUrl }}" readonly aria-label="{{ __('Hosted chat URL') }}">
                        <button type="button" data-copy-target="hosted-chat-url" data-copy-label="{{ __('Copy hosted chat URL') }}" data-copied-label="{{ __('Copied') }}" aria-label="{{ __('Copy hosted chat URL') }}"><i class="iconoir-copy" aria-hidden="true"></i></button>
                    </div>
                    @if ($hostedAvailable)
                        <a class="nd-sub-hosted-link" href="{{ $hostedUrl }}" target="_blank" rel="noopener noreferrer">{{ __('Open the chat page') }}<i class="iconoir-arrow-up-right" aria-hidden="true"></i></a>
                    @else
                        <p class="nd-sub-delivery-note"><i class="iconoir-info-circle" aria-hidden="true"></i>{{ __('The URL is reserved. Public hosted-chat delivery is not enabled yet.') }}</p>
                    @endif
                </section>

                <section class="nd-sub-embed-card nd-sub-preview-card" aria-labelledby="widget-preview-title">
                    <header class="nd-sub-embed-card-heading"><div><h2 id="widget-preview-title">{{ __('Preview') }}</h2><p>{{ __('How the widget appears on your site.') }}</p></div></header>
                    <div class="nd-sub-site-preview {{ $selectedPosition === 'bottom_left' ? 'is-left' : 'is-right' }}" data-widget-preview style="--nd-widget-accent: {{ $selectedColor }}">
                        <div class="nd-sub-site-lines" aria-hidden="true"><span></span><span></span><span></span></div>
                        <p data-preview-message>{{ $welcomeMessage }}</p>
                        <span class="nd-sub-widget-launcher" aria-hidden="true"><i class="iconoir-chat-bubble"></i></span>
                    </div>
                    <p class="nd-sub-preview-status" data-preview-status>{{ $enabled ? __('Launcher is enabled') : __('Launcher is disabled') }}</p>
                </section>
            </aside>
        </div>

        <p class="visually-hidden" data-copy-status role="status" aria-live="polite"></p>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/subscriber-widget-settings.js') }}"></script>
@endpush
