@extends('admin.layouts.app')

@section('title', $ticket->reference)

@php
    $replyErrors = $errors->getBag('adminSupportReply');
    $statusErrors = $errors->getBag('adminSupportStatus');
    $mayReply = $ticket->archived_at === null && $ticket->status->acceptsAdminReplies();
    $statusClass = match ($ticket->status) {
        \App\Enums\SupportTicketStatus::RESOLVED => 'nd-status-success',
        \App\Enums\SupportTicketStatus::AWAITING_USER => 'nd-status-warning',
        \App\Enums\SupportTicketStatus::OPEN, \App\Enums\SupportTicketStatus::AWAITING_SUPPORT => 'nd-status-info',
        default => 'nd-status-muted',
    };
    $priorityClass = match ($ticket->priority) {
        \App\Enums\SupportTicketPriority::URGENT => 'nd-ticket-priority-urgent',
        \App\Enums\SupportTicketPriority::HIGH => 'nd-ticket-priority-high',
        \App\Enums\SupportTicketPriority::MEDIUM => 'nd-ticket-priority-medium',
        default => 'nd-ticket-priority-low',
    };
@endphp

@section('content')
    <div class="nd-ticket-detail-header"><div><h1>{{ $ticket->subject }}</h1><p>{{ $ticket->reference }} <span aria-hidden="true">·</span> {{ $ticket->requester?->name ?? __('Former subscriber') }}</p></div><a class="btn nd-btn-secondary" href="{{ route('admin.support-tickets.index') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back') }}</a></div>

    @if ($ticket->archived_at)<div class="nd-ticket-admin-banner" role="status"><i class="iconoir-archive" aria-hidden="true"></i>{{ __('This ticket is archived. Its conversation and files remain available for support history.') }}</div>@elseif (! $mayReply)<div class="nd-ticket-admin-banner" role="status"><i class="iconoir-lock" aria-hidden="true"></i>{{ __('This ticket is read-only. Reopen it explicitly before sending another reply.') }}</div>@endif

    <div class="row g-4 nd-ticket-detail-grid">
        <div class="col-12 col-xl-8">
            @if ($mayReply)
                <section class="card nd-card nd-ticket-reply-card" aria-labelledby="reply-title"><div class="card-body">
                    <h2 id="reply-title">{{ __('Reply') }}</h2>
                    <form method="POST" action="{{ route('admin.support-tickets.replies.store', $ticket) }}" enctype="multipart/form-data" data-admin-ticket-form data-ticket-submit-once>
                        @csrf
                        <input name="submission_token" type="hidden" value="{{ \Illuminate\Support\Str::uuid() }}">
                        <div class="mb-3"><label class="form-label visually-hidden" for="admin-reply-message">{{ __('Reply message') }}</label><textarea class="form-control nd-ticket-reply-editor {{ $replyErrors->has('message') ? 'is-invalid' : '' }}" id="admin-reply-message" name="message" rows="9" minlength="2" maxlength="10000" required aria-describedby="admin-reply-message-help admin-reply-message-error" placeholder="{{ __('Write your reply...') }}">{{ old('message') }}</textarea><div class="form-text" id="admin-reply-message-help">{{ __('Messages are stored as plain text and displayed safely to the subscriber.') }}</div>@if ($replyErrors->has('message'))<div class="invalid-feedback" id="admin-reply-message-error">{{ $replyErrors->first('message') }}</div>@endif</div>
                        <div class="mb-3" data-ticket-attachment-picker><label class="form-label" for="admin-reply-attachments">{{ __('Attachments') }}</label><input class="form-control {{ $replyErrors->has('attachments') || $replyErrors->has('attachments.*') ? 'is-invalid' : '' }}" id="admin-reply-attachments" name="attachments[]" type="file" multiple accept=".pdf,.txt,.jpg,.jpeg,.png,.webp,.doc,.docx" data-ticket-attachment-input><div class="form-text">{{ __('Up to 5 files, 10 MB each. PDF, text, images, and Word documents only.') }}</div>@if ($replyErrors->has('attachments'))<div class="invalid-feedback d-block">{{ $replyErrors->first('attachments') }}</div>@endif @if ($replyErrors->has('attachments.*'))<div class="invalid-feedback d-block">{{ $replyErrors->first('attachments.*') }}</div>@endif<ul class="nd-ticket-selected-files" data-ticket-selected-files aria-live="polite"></ul></div>
                        <div class="d-flex justify-content-end"><button class="btn nd-btn-primary" type="submit" data-ticket-submit-button data-loading-label="{{ __('Sending...') }}"><i class="iconoir-send" aria-hidden="true"></i>{{ __('Send Reply') }}</button></div>
                    </form>
                </div></section>
            @endif

            <section class="card nd-card nd-ticket-conversation-card" aria-labelledby="conversation-title"><div class="card-body">
                <h2 id="conversation-title">{{ __('Conversation') }}</h2>
                @if ($messages->isEmpty())<div class="nd-users-empty"><span aria-hidden="true"><i class="iconoir-chat-bubble-empty"></i></span><h3>{{ __('No messages found') }}</h3></div>@else
                    <div class="nd-admin-ticket-messages">
                        @foreach ($messages as $message)
                            @php $isStaff = $message->sender_type === \App\Enums\SupportTicketSenderType::STAFF && $message->sender?->role === \App\Enums\UserRole::ADMIN; @endphp
                            <article class="nd-admin-ticket-message {{ $isStaff ? 'is-staff' : '' }}"><span class="nd-admin-message-avatar">@if ($message->sender?->avatarUrl())<img src="{{ $message->sender->avatarUrl() }}" alt="">@else<span aria-hidden="true">{{ $message->sender?->initials() ?? ($isStaff ? 'S' : 'U') }}</span>@endif</span><div class="nd-admin-message-content"><header><strong>{{ $message->sender?->name ?? ($isStaff ? __('Former staff member') : __('Former subscriber')) }}</strong>@if ($isStaff)<span class="nd-staff-badge">{{ __('Staff') }}</span>@endif<time datetime="{{ $message->created_at->toIso8601String() }}">{{ $message->created_at->format('d M, Y \a\t h:i A') }}</time></header><p>{{ $message->body }}</p>@if ($message->attachments->isNotEmpty())<ul class="nd-admin-message-attachments">@foreach ($message->attachments as $attachment)<li><a href="{{ route('admin.support-tickets.attachments.download', [$ticket, $attachment->id]) }}"><i class="iconoir-attachment" aria-hidden="true"></i><span>{{ $attachment->original_name }}</span><small>{{ Number::fileSize($attachment->size) }}</small></a></li>@endforeach</ul>@endif</div></article>
                        @endforeach
                    </div>
                    @if ($messages->hasPages())<div class="nd-users-pagination">{{ $messages->links('pagination::bootstrap-5') }}</div>@endif
                @endif
            </div></section>
        </div>

        <div class="col-12 col-xl-4"><aside class="card nd-card nd-ticket-meta-card" aria-labelledby="ticket-details-title"><div class="card-body">
            <h2 id="ticket-details-title">{{ __('Ticket Details') }}</h2>
            @unless ($ticket->archived_at)
                <form method="POST" action="{{ route('admin.support-tickets.status.update', $ticket) }}" data-ticket-submit-once>@csrf @method('PATCH')<label class="form-label" for="ticket-status-select">{{ __('Status') }}</label><div class="nd-ticket-status-form"><select class="form-select {{ $statusErrors->has('status') ? 'is-invalid' : '' }}" id="ticket-status-select" name="status" required>@foreach ($statuses as $status)@if ($ticket->status === $status || $ticket->status->canTransitionTo($status))<option value="{{ $status->value }}" @selected($ticket->status === $status)>{{ $status->adminLabel() }}</option>@endif @endforeach</select><button class="btn nd-btn-primary" type="submit" data-ticket-submit-button>{{ __('Save') }}</button></div>@if ($statusErrors->has('status'))<div class="invalid-feedback d-block">{{ $statusErrors->first('status') }}</div>@endif</form>
            @else <div class="mb-3"><span class="nd-status-badge nd-status-muted">{{ __('Archived') }}</span></div>@endunless
            <dl class="nd-ticket-meta-list">
                <div><dt><span class="nd-meta-icon priority"><i class="iconoir-flag" aria-hidden="true"></i></span>{{ __('Priority') }}</dt><dd><span class="nd-ticket-priority {{ $priorityClass }}">{{ $ticket->priority->label() }}</span></dd></div>
                <div><dt><span class="nd-meta-icon category"><i class="iconoir-label" aria-hidden="true"></i></span>{{ __('Category') }}</dt><dd>{{ $ticket->category ?: __('Not provided') }}</dd></div>
                <div><dt><span class="nd-meta-icon requester"><i class="iconoir-user" aria-hidden="true"></i></span>{{ __('Requester') }}</dt><dd>{{ $ticket->requester?->name ?? __('Former subscriber') }}</dd></div>
                <div><dt><span class="nd-meta-icon email"><i class="iconoir-mail" aria-hidden="true"></i></span>{{ __('Email') }}</dt><dd>{{ $ticket->requester?->email ?? __('Not available') }}</dd></div>
                <div><dt><span class="nd-meta-icon opened"><i class="iconoir-clock" aria-hidden="true"></i></span>{{ __('Opened') }}</dt><dd><time datetime="{{ $ticket->created_at->toIso8601String() }}">{{ $ticket->created_at->diffForHumans() }}</time></dd></div>
                <div><dt><span class="nd-meta-icon activity"><i class="iconoir-refresh" aria-hidden="true"></i></span>{{ __('Last activity') }}</dt><dd><time datetime="{{ $ticket->last_activity_at->toIso8601String() }}">{{ $ticket->last_activity_at->diffForHumans() }}</time></dd></div>
            </dl>
            <div class="mt-3"><span class="nd-status-badge {{ $statusClass }}">{{ $ticket->status->adminLabel() }}</span></div>
        </div></aside></div>
    </div>
@endsection

@push('scripts')<script src="{{ asset('js/admin-support-tickets.js') }}"></script>@endpush
