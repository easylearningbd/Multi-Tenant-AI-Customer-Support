<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Widget demo — :bot', ['bot' => $widget->bot->display_name]) }}</title>
    <link rel="stylesheet" href="{{ asset('css/neuraldesk-widget-demo.css') }}">
</head>
<body>
    <header><strong>{{ __('Demo storefront') }}</strong><span>{{ __('External-site simulation') }}</span></header>
    <main>
        <h1>{{ __('A real widget on an isolated page') }}</h1>
        <p>{{ __('This page deliberately uses a different visual style. The chat in the corner remains isolated inside its own frame and uses the bot’s saved appearance.') }}</p>
        <section class="demo-grid" aria-label="{{ __('Example content') }}">
            <article><h2>{{ __('Independent styles') }}</h2><p>{{ __('The host page cannot override the chat interface typography or layout.') }}</p></article>
            <article><h2>{{ __('Grounded answers') }}</h2><p>{{ __('Messages use the same tenant-scoped knowledge and RAG pipeline as hosted chat.') }}</p></article>
            <article><h2>{{ __('Secure sessions') }}</h2><p>{{ __('The browser receives an opaque, expiring visitor token without tenant identifiers.') }}</p></article>
        </section>
        <div class="demo-note"><strong>{{ __('Live integration:') }}</strong> {{ __('the corner launcher below was added with the same single versioned script tag shown in Embed & Share.') }}</div>
    </main>
    <script src="{{ $loaderUrl }}" data-neuraldesk-widget="{{ $widget->public_id }}" defer></script>
</body>
</html>
