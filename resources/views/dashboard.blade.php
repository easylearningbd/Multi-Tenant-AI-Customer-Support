@extends('subscriber.layouts.app')

@section('title', __('Overview'))

@section('content')
    <section class="row g-3 g-xxl-4 nd-sub-metrics" aria-label="{{ __('Workspace overview metrics') }}">
        <div class="col-sm-6 col-xxl-3"><article class="nd-sub-card nd-sub-metric-card">
            <div class="nd-sub-card-heading"><div><h2>{{ __('Conversations') }}</h2><p>{{ __('Inbox activity') }}</p></div><span class="nd-sub-icon nd-sub-icon-purple" aria-hidden="true"><i class="iconoir-chat-bubble"></i></span></div>
            <strong class="nd-sub-metric-value">{{ number_format($dashboard['conversations']['total']) }}</strong>
            <div class="nd-sub-metric-footer"><span class="text-primary">{{ __(':count open', ['count' => number_format($dashboard['conversations']['open'])]) }}</span><span>{{ __(':count resolved', ['count' => number_format($dashboard['conversations']['resolved'])]) }}</span></div>
        </article></div>
        <div class="col-sm-6 col-xxl-3"><article class="nd-sub-card nd-sub-metric-card">
            <div class="nd-sub-card-heading"><div><h2>{{ $dashboard['aiResolution']['label'] }}</h2><p>{{ __('Automation quality') }}</p></div><span class="nd-sub-icon nd-sub-icon-cyan" aria-hidden="true"><i class="iconoir-sparks"></i></span></div>
            <strong class="nd-sub-metric-value">{{ $dashboard['aiResolution']['percentage'] }}%</strong>
            <div class="nd-sub-progress-copy"><span>{{ __(':count resolved', ['count' => number_format($dashboard['aiResolution']['resolved'])]) }}</span><span>{{ __('of :count total', ['count' => number_format($dashboard['aiResolution']['total'])]) }}</span></div>
            <div class="progress nd-sub-progress" role="progressbar" aria-label="{{ __('AI resolution percentage') }}" aria-valuenow="{{ $dashboard['aiResolution']['percentage'] }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar nd-sub-progress-cyan" style="width: {{ $dashboard['aiResolution']['percentage'] }}%"></div></div>
        </article></div>
        <div class="col-sm-6 col-xxl-3"><article class="nd-sub-card nd-sub-metric-card">
            <div class="nd-sub-card-heading"><div><h2>{{ __('Active chatbots') }}</h2><p>{{ __('Plan capacity') }}</p></div><span class="nd-sub-icon nd-sub-icon-orange" aria-hidden="true"><i class="iconoir-brain-electricity"></i></span></div>
            <strong class="nd-sub-metric-value">{{ number_format($dashboard['chatbots']['active']) }}</strong>
            <div class="nd-sub-progress-copy nd-sub-progress-copy-orange"><span>{{ __(':count in use', ['count' => number_format($dashboard['chatbots']['used'])]) }}</span><span>{{ $dashboard['chatbots']['limitLabel'] }}</span></div>
            <div class="progress nd-sub-progress" role="progressbar" aria-label="{{ __('Chatbot plan usage') }}" aria-valuenow="{{ $dashboard['chatbots']['percentage'] }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar nd-sub-progress-orange" style="width: {{ $dashboard['chatbots']['percentage'] }}%"></div></div>
        </article></div>
        <div class="col-sm-6 col-xxl-3"><article class="nd-sub-card nd-sub-metric-card">
            <div class="nd-sub-card-heading"><div><h2>{{ __('Knowledge bases') }}</h2><p>{{ __('Training coverage') }}</p></div><span class="nd-sub-icon nd-sub-icon-green" aria-hidden="true"><i class="iconoir-book-stack"></i></span></div>
            <strong class="nd-sub-metric-value">{{ number_format($dashboard['knowledge']['bases']) }}</strong>
            <div class="nd-sub-metric-footer nd-sub-metric-footer-green"><span>{{ __(':count sources', ['count' => number_format($dashboard['knowledge']['sources'])]) }}</span><span>{{ __(':count chunks', ['count' => number_format($dashboard['knowledge']['chunks'])]) }}</span></div>
        </article></div>
    </section>

    <section class="nd-sub-card nd-sub-chart-card" aria-labelledby="ai-answers-title">
        <div class="nd-sub-panel-heading"><div><h2 id="ai-answers-title">{{ __('AI answers per day') }}</h2><p>{{ __('Last 14 days') }}</p></div><span class="nd-sub-count-badge">{{ __(':count this week', ['count' => number_format($dashboard['chart']['thisWeek'])]) }}</span></div>
        <p class="visually-hidden" id="ai-answers-summary">{{ __('There were :count AI answers across the last 14 days.', ['count' => number_format($dashboard['chart']['total'])]) }}</p>
        <div id="subscriber-ai-answers-chart" class="nd-sub-chart" role="img" aria-labelledby="ai-answers-title ai-answers-summary"></div>
        @if ($dashboard['chart']['total'] === 0)<p class="nd-sub-chart-empty">{{ __('No AI answers recorded yet. Daily activity will appear after your assistants begin responding.') }}</p>@endif
    </section>

    <div class="row g-3 g-xxl-4 nd-sub-lower-panels">
        <div class="col-xl-7"><section class="nd-sub-card nd-sub-list-card" aria-labelledby="recent-conversations-title">
            <div class="nd-sub-panel-heading"><div><h2 id="recent-conversations-title">{{ __('Recent conversations') }}</h2><p>{{ __('Latest activity across your assistant channels.') }}</p></div>@if (Route::has('conversations.index'))<a href="{{ route('conversations.index') }}">{{ __('View all') }} <i class="iconoir-arrow-up-right" aria-hidden="true"></i></a>@endif</div>
            @forelse ($dashboard['recentConversations'] as $conversation)
                <article class="nd-sub-conversation-row"><span class="nd-sub-row-icon" aria-hidden="true"><i class="iconoir-chat-bubble"></i></span><div><h3>{{ $conversation['visitor'] }}</h3><p>{{ \Illuminate\Support\Str::limit($conversation['preview'], 120) }}</p></div><span class="nd-sub-row-status">{{ $conversation['status'] }}</span></article>
            @empty
                <div class="nd-sub-empty-state" role="status"><span aria-hidden="true"><i class="iconoir-chat-bubble-empty"></i></span><h3>{{ __('No conversations yet') }}</h3><p>{{ __('Conversations will appear here after visitors start chatting.') }}</p></div>
            @endforelse
        </section></div>
        <div class="col-xl-5"><section class="nd-sub-card nd-sub-list-card" aria-labelledby="knowledge-gaps-title">
            <div class="nd-sub-panel-heading"><div><h2 id="knowledge-gaps-title">{{ __('Knowledge gaps') }}</h2><p>{{ __('Questions that need better sources will appear as your inbox grows.') }}</p></div><span class="nd-sub-count-badge">{{ __(':count flagged', ['count' => number_format($dashboard['knowledgeGaps']['total'])]) }}</span></div>
            <div class="nd-sub-gap-counters" aria-label="{{ __('Knowledge gap counts') }}"><span>{{ __('Low confidence') }} <strong>{{ number_format($dashboard['knowledgeGaps']['lowConfidence']) }}</strong></span><span>{{ __('Missing sources') }} <strong>{{ number_format($dashboard['knowledgeGaps']['missingSources']) }}</strong></span></div>
            @forelse ($dashboard['knowledgeGaps']['items'] as $gap)
                <article class="nd-sub-gap-row"><span class="nd-sub-row-icon" aria-hidden="true"><i class="iconoir-warning-circle"></i></span><div><h3>{{ $gap['label'] }}</h3><p>{{ $gap['preview'] }}</p></div><span class="nd-sub-row-status">{{ $gap['count'] }}</span></article>
            @empty
                <div class="nd-sub-empty-state" role="status"><span aria-hidden="true"><i class="iconoir-book-stack"></i></span><h3>{{ __('No knowledge gaps recorded') }}</h3><p>{{ __('Gap tracking will appear here when retrieval analytics are available.') }}</p></div>
            @endforelse
        </section></div>
    </div>

    <script id="subscriber-dashboard-chart-data" type="application/json">@json($dashboard['chart'])</script>
@endsection

@push('vendor-scripts')<script src="{{ asset('theme/assets/libs/apexcharts/apexcharts.min.js') }}"></script>@endpush
