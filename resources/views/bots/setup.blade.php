@extends('subscriber.layouts.app')

@section('title', __('Bot setup'))
@section('header-title', __('Bot setup'))
@section('header-subtitle', $bot->display_name)

@section('header-actions')
    <a class="nd-sub-setup-back" href="{{ route('bots.index') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back to bots') }}</a>
@endsection

@section('content')
    <div class="nd-sub-bot-setup-page">
        <section class="nd-sub-bot-setup-card" aria-labelledby="bot-setup-title">
            <span class="nd-sub-bot-icon large" aria-hidden="true"><i class="iconoir-brain-electricity"></i></span>
            <div class="nd-sub-bot-setup-copy">
                <span class="nd-sub-bot-state {{ $bot->is_active ? 'is-live' : 'is-draft' }}"><i class="iconoir-{{ $bot->is_active ? 'check-circle' : 'clock' }}" aria-hidden="true"></i>{{ $bot->is_active ? __('Live') : __('Inactive draft') }}</span>
                <h2 id="bot-setup-title">{{ $bot->name }}</h2>
                <p>{{ $bot->is_active ? __('Review this bot before continuing to its configuration and knowledge tools.') : __('Your bot identity and safe default behavior have been created. Continue through the setup stages before publishing it.') }}</p>
                <dl><div><dt>{{ __('Public identifier') }}</dt><dd>{{ $bot->public_id }}</dd></div><div><dt>{{ __('Slug') }}</dt><dd>{{ $bot->slug }}</dd></div></dl>
            </div>
        </section>

        <section class="nd-sub-setup-steps" aria-labelledby="setup-steps-title">
            <header><h2 id="setup-steps-title">{{ __('Setup progress') }}</h2><p>{{ __('Your draft remains unavailable to visitors until configuration and training are complete.') }}</p></header>
            <ol>
                <li class="complete"><span><i class="iconoir-check" aria-hidden="true"></i></span><div><strong>{{ __('Create bot identity') }}</strong><small>{{ __('Completed') }}</small></div></li>
                <li><span>2</span><div><strong>{{ __('Configure behavior') }}</strong><small>{{ __('Settings are the next setup stage') }}</small></div></li>
                <li><span>3</span><div><strong>{{ __('Add trusted knowledge') }}</strong><small>{{ __('Training is not available yet') }}</small></div></li>
                <li><span>4</span><div><strong>{{ __('Publish and share') }}</strong><small><a href="{{ route('bots.embed.edit', $bot) }}">{{ __('Configure embed code and appearance') }}</a></small></div></li>
            </ol>
            <a class="nd-sub-dark-button" href="{{ route('bots.index') }}">{{ __('Return to bots') }}</a>
        </section>
    </div>
@endsection
