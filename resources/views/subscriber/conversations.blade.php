@extends('subscriber.layouts.app')

@section('title', __('Conversations'))
@section('header-title', __('Conversations'))
@section('header-subtitle', __('Every chat your bots handled, across all sites.'))

@section('content')
    @php
        $statusLabels = [
            'open_ai' => __('Open'), 'needs_human' => __('Needs human reply'),
            'open_manual' => __('Manual'), 'resolved' => __('Resolved'),
            'archived' => __('Archived'), 'spam' => __('Spam'),
        ];
        $activeConversation = $selectedConversation;
        $isManual = $activeConversation?->effectiveHandlingMode() === \App\Enums\ConversationHandlingMode::MANUAL;
        $canReply = $activeConversation?->acceptsAgentReplies() === true;
    @endphp

    <div class="nd-sub-inbox-page {{ $explicitSelection ? 'has-mobile-thread' : '' }}"
         data-conversation-inbox data-poll-seconds="{{ max(2, (int) config('neuraldesk.conversations.poll_seconds', 4)) }}"
         data-activity-url="{{ route('conversations.activity') }}"
         data-latest-activity="{{ $conversations->max('last_message_at')?->toIso8601String() }}">
        <aside class="nd-sub-inbox-list" aria-label="{{ __('Conversation list') }}">
            <form class="nd-sub-inbox-search" method="GET" action="{{ route('conversations.index') }}" data-conversation-search>
                <input type="hidden" name="filter" value="{{ $filter }}">
                <i class="iconoir-search" aria-hidden="true"></i>
                <label class="visually-hidden" for="conversation-search">{{ __('Search conversations') }}</label>
                <input id="conversation-search" type="search" name="q" value="{{ $search }}" maxlength="200" placeholder="{{ __('Search conversations…') }}" autocomplete="off">
            </form>

            <div class="nd-sub-inbox-filter-row">
                <nav class="nd-sub-inbox-filters" aria-label="{{ __('Conversation filters') }}">
                    @foreach (['all' => __('All'), 'open' => __('Open'), 'manual' => __('Manual'), 'resolved' => __('Resolved')] as $key => $label)
                        <a class="{{ $filter === $key ? 'active' : '' }}" href="{{ route('conversations.index', array_filter(['filter' => $key, 'q' => $search])) }}" @if($filter === $key) aria-current="page" @endif>
                            {{ $label }} <span>{{ number_format($counts[$key]) }}</span>
                        </a>
                    @endforeach
                </nav>
                <a class="nd-sub-inbox-archive-filter {{ $filter === 'archived' ? 'active' : '' }}" href="{{ route('conversations.index', array_filter(['filter' => 'archived', 'q' => $search])) }}" aria-label="{{ __('Archived conversations (:count)', ['count' => $counts['archived']]) }}" title="{{ __('Archived conversations') }}">
                    <i class="iconoir-archive" aria-hidden="true"></i>@if($counts['archived'] > 0)<span>{{ $counts['archived'] }}</span>@endif
                </a>
            </div>

            <button class="nd-sub-inbox-refresh" type="button" hidden data-inbox-refresh><i class="iconoir-refresh" aria-hidden="true"></i>{{ __('New conversations available — refresh') }}</button>

            <div class="nd-sub-inbox-items">
                @forelse ($conversations as $conversation)
                    @php
                        $selected = $activeConversation?->is($conversation) === true;
                        $preview = $conversation->last_message_preview ?: $conversation->subject ?: __('No messages yet');
                    @endphp
                    <a class="nd-sub-inbox-item {{ $selected ? 'active' : '' }} {{ $conversation->unread_count > 0 ? 'is-unread' : '' }}"
                       href="{{ route('conversations.index', array_filter(['filter' => $filter, 'q' => $search, 'conversation' => $conversation->uuid])) }}"
                       data-conversation-card="{{ $conversation->uuid }}" @if($selected) aria-current="true" @endif>
                        <span class="nd-sub-inbox-avatar" aria-hidden="true">{{ $conversation->visitorInitials() }}</span>
                        <span class="nd-sub-inbox-item-copy">
                            <span class="nd-sub-inbox-item-line"><strong>{{ $conversation->visitorDisplayName() }}</strong><time datetime="{{ $conversation->last_message_at?->toIso8601String() }}">{{ $conversation->last_message_at?->diffForHumans(short: true) ?? __('New') }}</time></span>
                            <span class="nd-sub-inbox-preview">@if($conversation->last_message_sender_type !== 'visitor')<b>{{ __('Reply:') }}</b>@endif {{ $preview }}</span>
                            <span class="nd-sub-inbox-item-meta">
                                @if($conversation->status === \App\Enums\ConversationStatus::NEEDS_HUMAN)<span class="nd-sub-inbox-needs-human">{{ __('Needs human reply') }}</span>
                                @elseif($conversation->status === \App\Enums\ConversationStatus::RESOLVED)<span class="nd-sub-inbox-mini-status is-resolved">{{ __('Resolved') }}</span>
                                @elseif($conversation->status === \App\Enums\ConversationStatus::ARCHIVED)<span class="nd-sub-inbox-mini-status">{{ __('Archived') }}</span>@endif
                                @if($conversation->unread_count > 0)<span class="nd-sub-inbox-unread" aria-label="{{ trans_choice(':count unread message|:count unread messages', $conversation->unread_count, ['count' => $conversation->unread_count]) }}">{{ $conversation->unread_count > 99 ? '99+' : $conversation->unread_count }}</span>@endif
                            </span>
                        </span>
                    </a>
                @empty
                    <div class="nd-sub-inbox-empty"><i class="iconoir-chat-bubble-empty" aria-hidden="true"></i><h2>{{ $search ? __('No matching conversations') : __('No conversations yet') }}</h2><p>{{ $search ? __('Try a different search term or filter.') : __('Chats from your active bots will appear here.') }}</p></div>
                @endforelse
            </div>

            @if($conversations->hasPages())<div class="nd-sub-inbox-pagination">{{ $conversations->links('pagination::bootstrap-5') }}</div>@endif
        </aside>

        <section class="nd-sub-inbox-thread" aria-label="{{ __('Selected conversation') }}">
            @if($activeConversation)
                <header class="nd-sub-inbox-thread-header">
                    <a class="nd-sub-inbox-mobile-back" href="{{ route('conversations.index', array_filter(['filter' => $filter, 'q' => $search])) }}" aria-label="{{ __('Back to conversations') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i></a>
                    <div class="nd-sub-inbox-thread-title"><h2>{{ $activeConversation->visitorDisplayName() }}</h2><p>{{ $activeConversation->bot?->display_name ?? $activeConversation->bot?->name ?? __('Deleted bot') }} · {{ __('Last active :time', ['time' => $activeConversation->last_message_at?->diffForHumans() ?? __('just now')]) }}</p></div>
                    <div class="nd-sub-inbox-thread-actions">
                        <span class="nd-sub-inbox-status is-{{ $activeConversation->status->value }}" data-conversation-status>{{ $statusLabels[$activeConversation->status->value] ?? $activeConversation->status->value }}</span>
                        @unless($activeConversation->status === \App\Enums\ConversationStatus::ARCHIVED)<form method="POST" action="{{ route('conversations.archive', $activeConversation) }}">@csrf @method('PATCH')<button type="submit" aria-label="{{ __('Archive conversation') }}" title="{{ __('Archive') }}"><i class="iconoir-archive" aria-hidden="true"></i></button></form>@endunless
                        <button type="button" data-bs-toggle="modal" data-bs-target="#delete-conversation-modal" aria-label="{{ __('Delete conversation') }}" title="{{ __('Delete') }}"><i class="iconoir-trash" aria-hidden="true"></i></button>
                        @if($activeConversation->status === \App\Enums\ConversationStatus::RESOLVED)
                            <form method="POST" action="{{ route('conversations.reopen', $activeConversation) }}">@csrf @method('PATCH')<button type="submit" aria-label="{{ __('Reopen conversation') }}" title="{{ __('Reopen') }}"><i class="iconoir-refresh" aria-hidden="true"></i></button></form>
                        @elseif(!in_array($activeConversation->status, [\App\Enums\ConversationStatus::ARCHIVED, \App\Enums\ConversationStatus::SPAM], true))
                            <form method="POST" action="{{ route('conversations.resolve', $activeConversation) }}">@csrf @method('PATCH')<button type="submit" aria-label="{{ __('Resolve conversation') }}" title="{{ __('Resolve') }}"><i class="iconoir-check-circle" aria-hidden="true"></i></button></form>
                        @endif
                        @if(!in_array($activeConversation->status, [\App\Enums\ConversationStatus::RESOLVED, \App\Enums\ConversationStatus::ARCHIVED, \App\Enums\ConversationStatus::SPAM], true))
                            <form class="nd-sub-inbox-mode" method="POST" action="{{ route('conversations.mode.update', $activeConversation) }}" data-mode-form>@csrf @method('PATCH')<input type="hidden" name="mode" value="manual"><label title="{{ $isManual ? __('Turn automatic replies on') : __('Turn automatic replies off') }}"><span class="visually-hidden">{{ __('Automatic replies') }}</span><input type="checkbox" name="mode" value="ai" @checked(!$isManual) data-mode-toggle><span aria-hidden="true"></span></label></form>
                        @endif
                    </div>
                </header>

                <div class="nd-sub-inbox-message-area" data-message-area data-messages-url="{{ route('conversations.messages.index', $activeConversation) }}" data-read-url="{{ route('conversations.read', $activeConversation) }}" data-conversation="{{ $activeConversation->uuid }}">
                    <button class="nd-sub-inbox-load-older" type="button" @if(!$hasOlderMessages) hidden @endif data-load-older>{{ __('Load older messages') }}</button>
                    <div class="nd-sub-inbox-messages" data-message-list aria-live="polite" aria-relevant="additions">
                        @foreach($messages as $message)
                            @php $actor = $message->actor_type->value; $isVisitor = $actor === 'visitor'; $isSystem = $actor === 'system'; @endphp
                            <article class="nd-sub-inbox-message is-{{ $actor }}" data-message-id="{{ $message->uuid }}">
                                @if($isVisitor)<span class="nd-sub-inbox-message-avatar" aria-hidden="true"><i class="iconoir-user"></i></span>@endif
                                <div class="nd-sub-inbox-bubble">
                                    @unless($isSystem || $isVisitor)<strong>{{ $actor === 'ai' ? __('AI assistant') : ($message->sender?->name ?? __('Team member')) }}</strong>@endunless
                                    @if($message->body)<p>{{ $message->body }}</p>@endif
                                    @if($message->attachments->isNotEmpty())<ul class="nd-sub-inbox-attachments">@foreach($message->attachments as $attachment)<li><a href="{{ route('conversations.attachments.download', [$activeConversation, $attachment->uuid]) }}"><i class="iconoir-attachment" aria-hidden="true"></i><span>{{ $attachment->original_name }}</span><small>{{ Number::fileSize($attachment->size) }}</small></a></li>@endforeach</ul>@endif
                                    <footer><time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->timezone(config('app.timezone'))->format('D, d M Y · g:i A') }}</time>@if(!$isVisitor && !$isSystem)<i class="iconoir-check" aria-hidden="true"></i>@endif</footer>
                                </div>
                                @if($actor === 'ai')<span class="nd-sub-inbox-bot-avatar" aria-label="{{ __('AI response') }}"><i class="iconoir-brain-electricity" aria-hidden="true"></i></span>@endif
                            </article>
                        @endforeach
                    </div>
                    <button class="nd-sub-inbox-new-messages" type="button" hidden data-new-messages>{{ __('New messages') }} <i class="iconoir-nav-arrow-down" aria-hidden="true"></i></button>
                </div>

                @if(!$canReply)
                    <div class="nd-sub-inbox-mode-notice" role="status" data-mode-notice>
                        @if($activeConversation->acceptsAiReplies())<i class="iconoir-brain-electricity" aria-hidden="true"></i>{{ __('Auto reply is active. Turn it off or wait for a human handoff to reply manually.') }}
                        @elseif($activeConversation->status === \App\Enums\ConversationStatus::RESOLVED)<i class="iconoir-check-circle" aria-hidden="true"></i>{{ __('This conversation is resolved. Reopen it to reply.') }}
                        @elseif($activeConversation->status === \App\Enums\ConversationStatus::ARCHIVED)<i class="iconoir-archive" aria-hidden="true"></i>{{ __('This conversation is archived and cannot receive replies.') }}
                        @else<i class="iconoir-warning-triangle" aria-hidden="true"></i>{{ __('This conversation cannot receive replies.') }}@endif
                    </div>
                @endif

                <form class="nd-sub-inbox-composer" method="POST" action="{{ route('conversations.messages.store', $activeConversation) }}" enctype="multipart/form-data" data-reply-form>
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}" data-idempotency-key>
                    <label class="nd-sub-inbox-attach" for="conversation-attachments" aria-label="{{ __('Attach files') }}" title="{{ __('Attach files') }}"><i class="iconoir-attachment" aria-hidden="true"></i></label>
                    <input class="visually-hidden" id="conversation-attachments" type="file" name="attachments[]" accept=".pdf,.txt,.png,.jpg,.jpeg,.webp,.docx" multiple @disabled(!$canReply) data-attachment-input>
                    <label class="visually-hidden" for="conversation-reply">{{ __('Reply to visitor') }}</label>
                    <textarea id="conversation-reply" name="message" rows="1" maxlength="{{ config('neuraldesk.rag.message_max_length', 4000) }}" placeholder="{{ $canReply ? __('Type your reply…') : __('Manual replies are unavailable') }}" @disabled(!$canReply) data-reply-input></textarea>
                    <button type="submit" @disabled(!$canReply) aria-label="{{ __('Send reply') }}" data-send-button><i class="iconoir-send-diagonal" aria-hidden="true"></i></button>
                    <div class="nd-sub-inbox-selected-files" hidden data-selected-attachments></div>
                    <p class="nd-sub-inbox-composer-feedback" role="alert" hidden data-composer-feedback></p>
                </form>

                <div class="modal fade" id="delete-conversation-modal" tabindex="-1" aria-labelledby="delete-conversation-title" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content nd-sub-delete-bot-modal"><div class="modal-header"><div><h2 id="delete-conversation-title">{{ __('Delete conversation?') }}</h2><p>{{ $activeConversation->visitorDisplayName() }}</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div><div class="modal-body"><p>{{ __('This removes the conversation from the inbox while preserving it for audit and retention. Its private attachments will not become public.') }}</p></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">{{ __('Cancel') }}</button><form method="POST" action="{{ route('conversations.destroy', $activeConversation) }}">@csrf @method('DELETE')<button class="nd-sub-delete-confirm" type="submit">{{ __('Delete conversation') }}</button></form></div></div></div></div>
            @else
                <div class="nd-sub-inbox-thread-empty"><i class="iconoir-chat-lines" aria-hidden="true"></i><h2>{{ __('Select a conversation') }}</h2><p>{{ __('Choose a chat from the inbox to review its messages and respond.') }}</p></div>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/subscriber-conversations.js') }}"></script>
@endpush
