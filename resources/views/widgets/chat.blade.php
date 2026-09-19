<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ __('Chat with :bot', ['bot' => $widget->bot->display_name]) }}</title>
    <link rel="stylesheet" href="{{ asset('css/neuraldesk-widget.css') }}">
</head>
<body class="nd-widget-body {{ $embedded ? 'is-embedded' : 'is-hosted' }}">
    <main
        class="nd-widget-root {{ $embedded ? 'is-embedded' : 'is-hosted is-open' }}"
        data-widget-root
        data-widget-id="{{ $widget->public_id }}"
        data-access-proof="{{ $accessProof }}"
        data-bootstrap-url="{{ $bootstrapUrl }}"
        data-prechat-url="{{ $prechatUrl }}"
        data-message-url="{{ $messageUrl }}"
        data-conversation-url="{{ $conversationUrlTemplate }}"
        data-handoff-url="{{ $handoffUrl }}"
        data-parent-origin="{{ $parentOrigin }}"
        data-embedded="{{ $embedded ? 'true' : 'false' }}"
    >
        @if ($embedded)
            <button class="nd-widget-launcher" type="button" data-widget-launcher aria-label="{{ __('Open customer support chat') }}" aria-expanded="false" aria-controls="neuraldesk-chat-panel">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 18.2 3.8 21l3.7-1.4A9.5 9.5 0 1 0 5 18.2Zm2.2-2.1A6.8 6.8 0 1 1 12 18a6.8 6.8 0 0 1-4.8-1.9Z"/></svg>
            </button>
        @endif

        <section class="nd-widget-panel" id="neuraldesk-chat-panel" data-widget-panel aria-label="{{ __('Customer support chat') }}" @if ($embedded) hidden @endif>
            <header class="nd-widget-header">
                <span class="nd-widget-avatar" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 7V5.5A2.5 2.5 0 0 1 10.5 3h3A2.5 2.5 0 0 1 16 5.5V7h1.5A2.5 2.5 0 0 1 20 9.5v7a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 16.5v-7A2.5 2.5 0 0 1 6.5 7H8Zm2 0h4V5.5a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5V7Zm-3.5 2a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5v-7a.5.5 0 0 0-.5-.5h-11ZM8 12h2v2H8v-2Zm6 0h2v2h-2v-2Z"/></svg></span>
                <div><strong data-widget-name>{{ __('Support assistant') }}</strong><span><i aria-hidden="true"></i><span data-widget-availability>{{ __('Connecting…') }}</span></span></div>
                @if ($embedded)
                    <button type="button" data-widget-close aria-label="{{ __('Close customer support chat') }}"><span aria-hidden="true">×</span></button>
                @endif
            </header>

            <div class="nd-widget-notice" data-widget-notice role="status" aria-live="polite">{{ __('Connecting securely…') }}</div>

            <div class="nd-widget-stage" data-prechat-stage hidden>
                <div class="nd-widget-stage-copy"><h1>{{ __('Before we begin') }}</h1><p>{{ __('Share the requested details so the support team can help you.') }}</p></div>
                <form data-prechat-form novalidate><div data-prechat-fields></div><button type="submit">{{ __('Start chat') }}</button></form>
            </div>

            <div class="nd-widget-chat" data-chat-stage hidden>
                <div class="nd-widget-messages" data-widget-messages role="log" aria-live="polite" aria-relevant="additions"></div>
                <div class="nd-widget-starters" data-widget-starters></div>
                <button class="nd-widget-handoff" type="button" data-widget-handoff hidden>{{ __('Talk to a person') }}</button>
                <form class="nd-widget-composer" data-message-form>
                    <label class="nd-widget-sr" for="neuraldesk-widget-message">{{ __('Type your question') }}</label>
                    <textarea id="neuraldesk-widget-message" name="message" rows="1" maxlength="{{ config('neuraldesk.rag.message_max_length') }}" placeholder="{{ __('Type your question…') }}" required></textarea>
                    <button type="submit" aria-label="{{ __('Send message') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3.4 2.6 18 8.4a1.1 1.1 0 0 1 0 2l-18 8.4a1 1 0 0 1-1.4-1.2L4 13l9-1-9-1-2-7.2a1 1 0 0 1 1.4-1.2Z"/></svg></button>
                </form>
                <p class="nd-widget-powered">{{ __('Powered by') }} <strong>NeuralDesk</strong></p>
            </div>
        </section>
        <noscript>{{ __('JavaScript is required to use this support chat.') }}</noscript>
    </main>
    <script src="{{ asset('js/neuraldesk-widget.js') }}" defer></script>
</body>
</html>
