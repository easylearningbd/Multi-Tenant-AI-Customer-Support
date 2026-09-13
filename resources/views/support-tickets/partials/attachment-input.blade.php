<div class="nd-sub-form-field nd-sub-attachment-field" data-attachment-picker>
    <label for="{{ $inputId }}">{{ __('Attachments') }}</label>
    <label class="nd-sub-attachment-control" for="{{ $inputId }}"><i class="iconoir-attachment" aria-hidden="true"></i><span>{{ __('Choose files') }}</span></label>
    <input class="visually-hidden" id="{{ $inputId }}" name="attachments[]" type="file" multiple accept=".pdf,.txt,.jpg,.jpeg,.png,.webp,.doc,.docx" data-attachment-input aria-describedby="{{ $inputId }}-help {{ $inputId }}-error">
    <small id="{{ $inputId }}-help">{{ __('Up to 5 private files, 10 MB each. PDF, TXT, JPEG, PNG, WebP, DOC, or DOCX.') }}</small>
    <ul class="nd-sub-selected-files" data-selected-files aria-live="polite"></ul>
    @if ($errorsBag->has('attachments') || $errorsBag->has('attachments.*'))
        <div class="invalid-feedback d-block" id="{{ $inputId }}-error">{{ $errorsBag->first('attachments') ?: $errorsBag->first('attachments.*') }}</div>
    @endif
</div>
