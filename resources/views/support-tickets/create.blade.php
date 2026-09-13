@extends('subscriber.layouts.app')

@section('title', __('New Ticket'))
@section('header-title', __('New Ticket'))
@section('header-subtitle', '')

@section('content')
    @php($ticketErrors = $errors->getBag('supportTicket'))

    <div class="nd-sub-support-page nd-sub-ticket-form-page">
        <div class="nd-sub-support-heading">
            <h2>{{ __('New Ticket') }}</h2>
            <a class="nd-sub-back-button" href="{{ route('support-tickets.index') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back') }}</a>
        </div>

        <section class="nd-sub-support-card nd-sub-ticket-form-card" aria-labelledby="new-ticket-form-title">
            <h3 class="visually-hidden" id="new-ticket-form-title">{{ __('Create a support ticket') }}</h3>
            <form method="POST" action="{{ route('support-tickets.store') }}" enctype="multipart/form-data" data-support-form data-single-submit>
                @csrf

                <div class="nd-sub-ticket-form-grid">
                    <div class="nd-sub-form-field">
                        <label for="subject">{{ __('Subject') }} <span aria-hidden="true">*</span></label>
                        <input class="form-control {{ $ticketErrors->has('subject') ? 'is-invalid' : '' }}" id="subject" name="subject" type="text" value="{{ old('subject') }}" minlength="5" maxlength="200" required aria-describedby="subject-error">
                        @if ($ticketErrors->has('subject'))<div class="invalid-feedback" id="subject-error">{{ $ticketErrors->first('subject') }}</div>@endif
                    </div>

                    <div class="nd-sub-form-field">
                        <label for="priority">{{ __('Priority') }} <span aria-hidden="true">*</span></label>
                        <select class="form-select {{ $ticketErrors->has('priority') ? 'is-invalid' : '' }}" id="priority" name="priority" required aria-describedby="priority-error">@foreach ($priorities as $priority)<option value="{{ $priority->value }}" @selected(old('priority', 'medium') === $priority->value)>{{ $priority->label() }}</option>@endforeach</select>
                        @if ($ticketErrors->has('priority'))<div class="invalid-feedback" id="priority-error">{{ $ticketErrors->first('priority') }}</div>@endif
                    </div>

                    <div class="nd-sub-form-field">
                        <label for="category">{{ __('Category') }}</label>
                        <input class="form-control {{ $ticketErrors->has('category') ? 'is-invalid' : '' }}" id="category" name="category" type="text" value="{{ old('category') }}" maxlength="100" aria-describedby="category-help category-error">
                        <small id="category-help">{{ __('Optional') }}</small>
                        @if ($ticketErrors->has('category'))<div class="invalid-feedback" id="category-error">{{ $ticketErrors->first('category') }}</div>@endif
                    </div>

                    <div class="nd-sub-form-field">
                        <label for="message">{{ __('Message') }} <span aria-hidden="true">*</span></label>
                        <textarea class="form-control nd-sub-message-editor {{ $ticketErrors->has('message') ? 'is-invalid' : '' }}" id="message" name="message" rows="10" minlength="10" maxlength="10000" required aria-describedby="message-help message-error" placeholder="{{ __('Type your message here…') }}">{{ old('message') }}</textarea>
                        <small id="message-help">{{ __('Messages are stored as plain text for your security.') }}</small>
                        @if ($ticketErrors->has('message'))<div class="invalid-feedback" id="message-error">{{ $ticketErrors->first('message') }}</div>@endif
                    </div>

                    @include('support-tickets.partials.attachment-input', ['errorsBag' => $ticketErrors, 'inputId' => 'ticket-attachments'])
                </div>

                <div class="nd-sub-ticket-form-actions">
                    <button class="nd-sub-primary-button" type="submit" data-loading-label="{{ __('Submitting…') }}">{{ __('Submit Ticket') }}</button>
                    <a href="{{ route('support-tickets.index') }}">{{ __('Cancel') }}</a>
                </div>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/subscriber-support.js') }}"></script>
@endpush
