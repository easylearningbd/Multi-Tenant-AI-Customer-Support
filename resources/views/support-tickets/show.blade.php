@extends('subscriber.layouts.app')

@section('title', $ticket->reference)
@section('header-title', $ticket->reference)
@section('header-subtitle', '')

@section('content')
    @php
        $replyErrors = $errors->getBag('supportReply');
        $mayReply = $ticket->status->acceptsSubscriberReplies();
    @endphp

    <div class="nd-sub-support-page nd-sub-ticket-conversation-page">
        <div class="nd-sub-ticket-detail-heading">
            <div>
                <h2>{{ $ticket->subject }}</h2>
                <div class="nd-sub-ticket-meta"><span>{{ $ticket->reference }}</span><span class="nd-sub-ticket-badge status-{{ $ticket->status->value }}">{{ $ticket->status->label() }}</span><span class="nd-sub-ticket-badge priority-{{ $ticket->priority->value }}">{{ $ticket->priority->label() }}</span>@if ($ticket->category)<span>{{ $ticket->category }}</span>@endif</div>
            </div>
            <a class="nd-sub-back-button" href="{{ route('support-tickets.index') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back') }}</a>
        </div>

        @unless ($mayReply)
            <div class="nd-sub-closed-banner" role="status">{{ __('This ticket is closed. Open a new ticket if you still need help.') }} <a href="{{ route('support-tickets.create') }}">{{ __('Open a new ticket') }}</a></div>
        @endunless

        <section class="nd-sub-support-card nd-sub-conversation-card" aria-labelledby="conversation-title">
            <h2 id="conversation-title">{{ __('Conversation') }}</h2>

            <div class="nd-sub-ticket-messages">
                @foreach ($messages as $message)
                    @php
                        $isStaff = $message->sender_type === \App\Enums\SupportTicketSenderType::STAFF
                            && $message->sender?->role === \App\Enums\UserRole::ADMIN;
                    @endphp
                    <article class="nd-sub-ticket-message {{ $isStaff ? 'is-staff' : '' }}">
                        <span class="nd-sub-message-avatar">
                            @if ($message->sender?->avatarUrl())<img src="{{ $message->sender->avatarUrl() }}" alt="">@else<span aria-hidden="true">{{ $message->sender?->initials() ?? ($isStaff ? 'S' : 'U') }}</span>@endif
                        </span>
                        <div class="nd-sub-message-content">
                            <header><strong>{{ $message->sender?->name ?? ($isStaff ? __('Support Staff') : __('Former subscriber')) }}</strong>@if ($isStaff)<span>{{ __('Staff') }}</span>@endif<time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('d M, Y \a\t h:i A') }}</time></header>
                            <p>{{ $message->body }}</p>
                            @if ($message->attachments->isNotEmpty())
                                <ul class="nd-sub-message-attachments">@foreach ($message->attachments as $attachment)<li><a href="{{ route('support-tickets.attachments.download', [$ticket, $attachment->id]) }}"><i class="iconoir-attachment" aria-hidden="true"></i><span>{{ $attachment->original_name }}</span><small>{{ Number::fileSize($attachment->size) }}</small></a></li>@endforeach</ul>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            @if ($messages->hasPages())<div class="nd-sub-ticket-pagination">{{ $messages->links('pagination::bootstrap-5') }}</div>@endif
        </section>

        @if ($mayReply)
            <section class="nd-sub-support-card nd-sub-reply-card" aria-labelledby="reply-title">
                <h2 id="reply-title">{{ __('Send a reply') }}</h2>
                <form method="POST" action="{{ route('support-tickets.replies.store', $ticket) }}" enctype="multipart/form-data" data-support-form data-single-submit>
                    @csrf
                    <div class="nd-sub-form-field">
                        <label for="reply-message">{{ __('Message') }} <span aria-hidden="true">*</span></label>
                        <textarea class="form-control nd-sub-message-editor {{ $replyErrors->has('message') ? 'is-invalid' : '' }}" id="reply-message" name="message" rows="6" minlength="2" maxlength="10000" required aria-describedby="reply-message-error" placeholder="{{ __('Type your reply here…') }}">{{ old('message') }}</textarea>
                        @if ($replyErrors->has('message'))<div class="invalid-feedback" id="reply-message-error">{{ $replyErrors->first('message') }}</div>@endif
                    </div>
                    @include('support-tickets.partials.attachment-input', ['errorsBag' => $replyErrors, 'inputId' => 'reply-attachments'])
                    <button class="nd-sub-primary-button" type="submit" data-loading-label="{{ __('Sending…') }}">{{ __('Send Reply') }}</button>
                </form>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/subscriber-support.js') }}"></script>
@endpush
