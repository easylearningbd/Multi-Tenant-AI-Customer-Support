@extends('subscriber.layouts.app')

@section('title', __('Bot settings'))
@section('header-title', __('Bot settings'))
@section('header-subtitle', $bot->display_name)

@section('header-actions')
    <a class="nd-sub-settings-action secondary" href="{{ route('bots.index') }}"><i class="iconoir-arrow-left" aria-hidden="true"></i>{{ __('Back') }}</a>
    <a class="nd-sub-settings-action secondary" href="{{ route('bots.training.index', $bot) }}"><i class="iconoir-book" aria-hidden="true"></i>{{ __('Train') }}</a>
    <a class="nd-sub-settings-action secondary" href="{{ route('bots.embed.edit', $bot) }}"><i class="iconoir-code" aria-hidden="true"></i>{{ __('Get embed code') }}</a>
    <button class="nd-sub-settings-action primary" type="submit" form="bot-settings-form">{{ __('Save changes') }}<i class="iconoir-check" aria-hidden="true"></i></button>
@endsection

@section('content')
    @php
        $settingsErrors = $errors->getBag('botSettings');
        $questions = old('starter_questions', $starterQuestions);
        $formFields = old('prechat_fields', $prechatFields);
        $value = fn (string $key) => old($key, $settingValues[$key] ?? null);
    @endphp

    <form id="bot-settings-form" method="POST" action="{{ route('bots.settings.update', $bot) }}" data-bot-settings-form data-submit-once>
        @csrf
        @method('PUT')

        <div class="nd-sub-bot-settings-layout">
            <div class="nd-sub-bot-settings-main">
                <section class="nd-sub-settings-card" aria-labelledby="identity-title">
                    <header><h2 id="identity-title">{{ __('Identity') }}</h2><p>{{ __('Choose the names shown in your workspace and visitor chat.') }}</p></header>
                    <div class="nd-sub-settings-grid two">
                        <div class="nd-sub-settings-field">
                            <label for="bot-settings-name">{{ __('Bot name') }} <span>*</span></label>
                            <input class="form-control @if($settingsErrors->has('name')) is-invalid @endif" id="bot-settings-name" name="name" value="{{ old('name', $bot->name) }}" maxlength="{{ config('neuraldesk.bots.limits.name') }}" required @if($settingsErrors->has('name')) aria-describedby="name-error" @endif>
                            @if($settingsErrors->has('name'))<div class="invalid-feedback" id="name-error">{{ $settingsErrors->first('name') }}</div>@endif
                        </div>
                        <div class="nd-sub-settings-field">
                            <label for="bot-settings-display-name">{{ __('Display name') }}</label>
                            <input class="form-control @if($settingsErrors->has('display_name')) is-invalid @endif" id="bot-settings-display-name" name="display_name" value="{{ old('display_name', $bot->display_name) }}" maxlength="{{ config('neuraldesk.bots.limits.display_name') }}" aria-describedby="display-name-help @if($settingsErrors->has('display_name')) display-name-error @endif">
                            <small id="display-name-help">{{ __('Falls back to the bot name when blank.') }}</small>
                            @if($settingsErrors->has('display_name'))<div class="invalid-feedback" id="display-name-error">{{ $settingsErrors->first('display_name') }}</div>@endif
                        </div>
                    </div>
                </section>

                <section class="nd-sub-settings-card nd-sub-unavailable-card" aria-labelledby="integrations-title">
                    <header><h2 id="integrations-title">{{ __('Assigned integrations') }}</h2><p>{{ __('Connections this bot can use.') }}</p></header>
                    <div><i class="iconoir-puzzle" aria-hidden="true"></i><p><strong>{{ __('No integration module is available yet.') }}</strong><span>{{ __('Integration assignment will appear here when the account integrations module is implemented.') }}</span></p><button type="button" disabled>{{ __('Open account integrations') }}</button></div>
                </section>

                <section class="nd-sub-settings-card" aria-labelledby="greeting-title">
                    <header><h2 id="greeting-title">{{ __('Greeting') }}</h2><p>{{ __('Set the opening message and optional conversation shortcuts.') }}</p></header>
                    <div class="nd-sub-settings-field">
                        <label for="bot-welcome-message">{{ __('Welcome message') }} <span>*</span></label>
                        <textarea class="form-control @if($settingsErrors->has('welcome_message')) is-invalid @endif" id="bot-welcome-message" name="welcome_message" rows="3" maxlength="{{ config('neuraldesk.bots.limits.welcome_message') }}" required>{{ $value('welcome_message') }}</textarea>
                        @if($settingsErrors->has('welcome_message'))<div class="invalid-feedback">{{ $settingsErrors->first('welcome_message') }}</div>@endif
                    </div>

                    <div class="nd-sub-settings-section-heading"><div><h3>{{ __('Starter questions') }}</h3><p>{{ __('Visitors can select these prompts to begin a conversation.') }}</p></div><span data-question-count>{{ count($questions) }} / {{ config('neuraldesk.bots.limits.starter_questions') }}</span></div>
                    <div class="nd-sub-sortable-list" data-question-list>
                        @foreach($questions as $index => $question)
                            <div class="nd-sub-sortable-row" data-question-row>
                                <span class="nd-sub-row-number" data-row-number>{{ $index + 1 }}</span>
                                <div><label class="visually-hidden" data-row-label for="starter-question-{{ $index }}">{{ __('Starter question :number', ['number' => $index + 1]) }}</label><input class="form-control @if($settingsErrors->has("starter_questions.$index")) is-invalid @endif" id="starter-question-{{ $index }}" name="starter_questions[{{ $index }}]" value="{{ $question }}" maxlength="{{ config('neuraldesk.bots.limits.starter_question') }}" required>@if($settingsErrors->has("starter_questions.$index"))<div class="invalid-feedback">{{ $settingsErrors->first("starter_questions.$index") }}</div>@endif</div>
                                <div class="nd-sub-row-actions"><button type="button" data-move-up aria-label="{{ __('Move question up') }}"><i class="iconoir-nav-arrow-up" aria-hidden="true"></i></button><button type="button" data-move-down aria-label="{{ __('Move question down') }}"><i class="iconoir-nav-arrow-down" aria-hidden="true"></i></button><button type="button" data-remove-row aria-label="{{ __('Remove question') }}"><i class="iconoir-xmark" aria-hidden="true"></i></button></div>
                            </div>
                        @endforeach
                    </div>
                    @if($settingsErrors->has('starter_questions'))<p class="nd-sub-settings-error" role="alert">{{ $settingsErrors->first('starter_questions') }}</p>@endif
                    <button class="nd-sub-inline-add" type="button" data-add-question><i class="iconoir-plus" aria-hidden="true"></i>{{ __('Add starter question') }}</button>
                </section>

                <section class="nd-sub-settings-card" aria-labelledby="prechat-title">
                    <div class="nd-sub-settings-section-heading has-switch">
                        <div><h2 id="prechat-title">{{ __('Pre-chat form') }}</h2><p>{{ __('Collect approved visitor details before a chat begins.') }}</p></div>
                        <div><span data-field-count>{{ count($formFields) }} / {{ config('neuraldesk.bots.limits.prechat_fields') }}</span><div class="form-check form-switch"><input type="hidden" name="prechat_enabled" value="0"><input class="form-check-input" id="prechat-enabled" name="prechat_enabled" type="checkbox" value="1" @checked((bool) $value('prechat_enabled'))><label class="visually-hidden" for="prechat-enabled">{{ __('Enable pre-chat form') }}</label></div></div>
                    </div>
                    <div class="nd-sub-prechat-list" data-prechat-list>
                        @foreach($formFields as $index => $field)
                            @php($optionsText = $field['options_text'] ?? implode(PHP_EOL, $field['options'] ?? []))
                            <div class="nd-sub-prechat-row" data-prechat-row>
                                <input type="hidden" data-standard-key name="prechat_fields[{{ $index }}][standard_key]" value="{{ $field['standard_key'] ?? '' }}">
                                <div class="nd-sub-prechat-top"><span class="nd-sub-row-number" data-row-number>{{ $index + 1 }}</span><div class="nd-sub-row-actions"><button type="button" data-move-up aria-label="{{ __('Move field up') }}"><i class="iconoir-nav-arrow-up"></i></button><button type="button" data-move-down aria-label="{{ __('Move field down') }}"><i class="iconoir-nav-arrow-down"></i></button><button type="button" data-remove-row aria-label="{{ __('Remove field') }}"><i class="iconoir-xmark"></i></button></div></div>
                                <div class="nd-sub-settings-grid two">
                                    <div class="nd-sub-settings-field"><label data-field-label for="prechat-label-{{ $index }}">{{ __('Field label') }}</label><input class="form-control @if($settingsErrors->has("prechat_fields.$index.label")) is-invalid @endif" id="prechat-label-{{ $index }}" name="prechat_fields[{{ $index }}][label]" value="{{ $field['label'] ?? '' }}" maxlength="100" required>@if($settingsErrors->has("prechat_fields.$index.label"))<div class="invalid-feedback">{{ $settingsErrors->first("prechat_fields.$index.label") }}</div>@endif</div>
                                    <div class="nd-sub-settings-field"><label data-type-label for="prechat-type-{{ $index }}">{{ __('Field type') }}</label><select class="form-select" id="prechat-type-{{ $index }}" name="prechat_fields[{{ $index }}][type]" data-field-type>@foreach($fieldTypes as $type)<option value="{{ $type->value }}" @selected(($field['type'] ?? 'text') === $type->value)>{{ $type->label() }}</option>@endforeach</select></div>
                                    <div class="nd-sub-settings-field"><label data-placeholder-label for="prechat-placeholder-{{ $index }}">{{ __('Placeholder') }}</label><input class="form-control" id="prechat-placeholder-{{ $index }}" name="prechat_fields[{{ $index }}][placeholder]" value="{{ $field['placeholder'] ?? '' }}" maxlength="255"></div>
                                    <div class="nd-sub-settings-field" data-options-field @if(($field['type'] ?? null) !== 'select') hidden @endif><label data-options-label for="prechat-options-{{ $index }}">{{ __('Options, one per line') }}</label><textarea class="form-control @if($settingsErrors->has("prechat_fields.$index.options")) is-invalid @endif" id="prechat-options-{{ $index }}" name="prechat_fields[{{ $index }}][options_text]" rows="2">{{ $optionsText }}</textarea>@if($settingsErrors->has("prechat_fields.$index.options"))<div class="invalid-feedback">{{ $settingsErrors->first("prechat_fields.$index.options") }}</div>@endif</div>
                                </div>
                                <div class="form-check"><input type="hidden" data-required-hidden name="prechat_fields[{{ $index }}][is_required]" value="0"><input class="form-check-input" data-required-check id="prechat-required-{{ $index }}" name="prechat_fields[{{ $index }}][is_required]" type="checkbox" value="1" @checked((bool)($field['is_required'] ?? false))><label class="form-check-label" data-required-label for="prechat-required-{{ $index }}">{{ __('Required') }}</label></div>
                            </div>
                        @endforeach
                    </div>
                    @if($settingsErrors->has('prechat_fields'))<p class="nd-sub-settings-error" role="alert">{{ $settingsErrors->first('prechat_fields') }}</p>@endif
                    <div class="nd-sub-prechat-adds"><button type="button" data-add-standard="name">{{ __('Add name') }}</button><button type="button" data-add-standard="email">{{ __('Add email') }}</button><button type="button" data-add-standard="phone">{{ __('Add phone') }}</button><button class="dark" type="button" data-add-custom><i class="iconoir-plus"></i>{{ __('Custom field') }}</button></div>
                </section>

                <section class="nd-sub-settings-card" aria-labelledby="behavior-title">
                    <header><h2 id="behavior-title">{{ __('Behavior') }}</h2><p>{{ __('Control the bot voice, fallback behavior, and grounding policy.') }}</p></header>
                    <div class="nd-sub-settings-grid two">
                        <div class="nd-sub-settings-field"><label for="bot-tone">{{ __('Tone') }}</label><select class="form-select @if($settingsErrors->has('tone')) is-invalid @endif" id="bot-tone" name="tone">@foreach($tones as $tone)<option value="{{ $tone->value }}" @selected($value('tone') === $tone->value)>{{ $tone->label() }}</option>@endforeach</select>@if($settingsErrors->has('tone'))<div class="invalid-feedback">{{ $settingsErrors->first('tone') }}</div>@endif</div>
                        <div class="nd-sub-settings-field"><label for="bot-language">{{ __('Primary language') }}</label><select class="form-select @if($settingsErrors->has('primary_language')) is-invalid @endif" id="bot-language" name="primary_language">@foreach($languages as $code => $label)<option value="{{ $code }}" @selected($value('primary_language') === $code)>{{ __($label) }}</option>@endforeach</select>@if($settingsErrors->has('primary_language'))<div class="invalid-feedback">{{ $settingsErrors->first('primary_language') }}</div>@endif</div>
                    </div>
                    <div class="nd-sub-settings-field"><label for="bot-fallback">{{ __('Fallback message') }}</label><textarea class="form-control @if($settingsErrors->has('fallback_message')) is-invalid @endif" id="bot-fallback" name="fallback_message" rows="3" maxlength="{{ config('neuraldesk.bots.limits.fallback_message') }}" required>{{ $value('fallback_message') }}</textarea>@if($settingsErrors->has('fallback_message'))<div class="invalid-feedback">{{ $settingsErrors->first('fallback_message') }}</div>@endif</div>
                    <div class="nd-sub-toggle-panel"><div><strong>{{ __('Offer human handoff') }}</strong><small>{{ __('Offer to bring in a teammate when the bot is unsure or asked.') }}</small></div><div class="form-check form-switch"><input type="hidden" name="offer_human_handoff" value="0"><input class="form-check-input" id="human-handoff" name="offer_human_handoff" type="checkbox" value="1" @checked((bool)$value('offer_human_handoff'))><label class="visually-hidden" for="human-handoff">{{ __('Offer human handoff') }}</label></div></div>
                    <div class="nd-sub-toggle-panel"><div><strong>{{ __('Answer only from knowledge base') }}</strong><small>{{ __('Use the fallback message whenever reliable knowledge is unavailable.') }}</small></div><div class="form-check form-switch"><input type="hidden" name="answer_only_from_knowledge_base" value="0"><input class="form-check-input" id="kb-only" name="answer_only_from_knowledge_base" type="checkbox" value="1" @checked((bool)$value('answer_only_from_knowledge_base'))><label class="visually-hidden" for="kb-only">{{ __('Answer only from knowledge base') }}</label></div></div>
                    <div class="nd-sub-settings-field"><label for="bot-persona">{{ __('Persona') }}</label><small>{{ __('Define how this chatbot should behave, answer, and escalate.') }}</small><textarea class="form-control @if($settingsErrors->has('persona')) is-invalid @endif" id="bot-persona" name="persona" rows="4" maxlength="{{ config('neuraldesk.bots.limits.persona') }}" required>{{ $value('persona') }}</textarea>@if($settingsErrors->has('persona'))<div class="invalid-feedback">{{ $settingsErrors->first('persona') }}</div>@endif</div>
                    <div class="nd-sub-module-placeholder"><i class="iconoir-book-stack" aria-hidden="true"></i><div><strong>{{ __('Knowledge bases') }}</strong><span>{{ __('Assignment becomes available with the Phase 4 knowledge module. No sources are assigned yet.') }}</span></div></div>
                </section>

                <section class="nd-sub-settings-card" aria-labelledby="ai-settings-title">
                    <header><h2 id="ai-settings-title">{{ __('AI response settings') }}</h2><p>{{ __('Use platform defaults or tune bounded response behavior.') }}</p></header>
                    <div class="nd-sub-settings-field"><label for="model-override">{{ __('Model override') }}</label><select class="form-select @if($settingsErrors->has('model_override')) is-invalid @endif" id="model-override" name="model_override"><option value="">{{ __('Use platform AI default model') }}</option>@foreach($allowedModels as $model)<option value="{{ $model }}" @selected($value('model_override') === $model)>{{ $model }}</option>@endforeach</select><small>{{ $allowedModels ? __('Only platform-approved models are available.') : __('No subscriber model overrides are configured; the platform default will be used.') }}</small>@if($settingsErrors->has('model_override'))<div class="invalid-feedback">{{ $settingsErrors->first('model_override') }}</div>@endif</div>
                    <div class="nd-sub-settings-grid three">
                        <div class="nd-sub-settings-field"><label for="temperature">{{ __('Temperature') }}</label><input class="form-control @if($settingsErrors->has('temperature')) is-invalid @endif" id="temperature" name="temperature" type="number" min="{{ config('neuraldesk.bots.limits.temperature_min') }}" max="{{ config('neuraldesk.bots.limits.temperature_max') }}" step="0.01" value="{{ $value('temperature') }}" required>@if($settingsErrors->has('temperature'))<div class="invalid-feedback">{{ $settingsErrors->first('temperature') }}</div>@endif</div>
                        <div class="nd-sub-settings-field"><label for="max-output-tokens">{{ __('Max output tokens') }}</label><input class="form-control @if($settingsErrors->has('max_output_tokens')) is-invalid @endif" id="max-output-tokens" name="max_output_tokens" type="number" min="{{ config('neuraldesk.bots.limits.max_output_tokens_min') }}" max="{{ config('neuraldesk.bots.limits.max_output_tokens_max') }}" value="{{ $value('max_output_tokens') }}" required>@if($settingsErrors->has('max_output_tokens'))<div class="invalid-feedback">{{ $settingsErrors->first('max_output_tokens') }}</div>@endif</div>
                        <div class="nd-sub-settings-field"><label for="kb-confidence">{{ __('KB confidence') }}</label><input class="form-control @if($settingsErrors->has('kb_confidence')) is-invalid @endif" id="kb-confidence" name="kb_confidence" type="number" min="0" max="1" step="0.001" value="{{ $value('kb_confidence') }}" required>@if($settingsErrors->has('kb_confidence'))<div class="invalid-feedback">{{ $settingsErrors->first('kb_confidence') }}</div>@endif</div>
                    </div>
                </section>

                <section class="nd-sub-danger-zone" aria-labelledby="danger-zone-title"><div><h2 id="danger-zone-title">{{ __('Danger zone') }}</h2><p>{{ __('Deleting makes this bot inactive and removes it from your workspace. Its configuration is retained for safe recovery and history.') }}</p></div><button type="button" data-bs-toggle="modal" data-bs-target="#delete-bot-modal"><i class="iconoir-trash" aria-hidden="true"></i>{{ __('Delete bot') }}</button></section>

                <div class="nd-sub-bot-active-control"><input type="hidden" name="is_active" value="0"><div class="form-check form-switch"><input class="form-check-input" id="bot-active" name="is_active" type="checkbox" value="1" @checked((bool)old('is_active', $bot->is_active))><label class="form-check-label" for="bot-active"><strong>{{ __('Bot is active') }}</strong><small>{{ __('Active bots are eligible for visitor use after the required training and public deployment phases are implemented.') }}</small></label></div></div>
            </div>

            <aside class="nd-sub-bot-preview" aria-labelledby="live-preview-title">
                <span>{{ __('Live preview') }}</span>
                <div class="nd-sub-preview-window">
                    <header><i class="iconoir-brain-electricity" aria-hidden="true"></i><strong data-preview-name>{{ old('display_name', $bot->display_name) ?: old('name', $bot->name) }}</strong></header>
                    <p data-preview-welcome>{{ $value('welcome_message') }}</p>
                    <div data-preview-questions></div>
                </div>
                <small>{{ __('Preview only. Save changes to apply them.') }}</small>
            </aside>
        </div>
    </form>

    <div class="modal fade" id="delete-bot-modal" tabindex="-1" aria-labelledby="delete-bot-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content nd-sub-delete-bot-modal"><div class="modal-header"><div><h2 class="modal-title" id="delete-bot-title">{{ __('Delete :name?', ['name' => $bot->name]) }}</h2><p>{{ __('This removes the bot from the workspace and stops future activity.') }}</p></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button></div><div class="modal-body"><p>{{ __('Configuration is retained for safe recovery and historical integrity. This action does not delete other workspace records.') }}</p></div><div class="modal-footer"><button type="button" class="nd-sub-modal-cancel" data-bs-dismiss="modal">{{ __('Cancel') }}</button><form method="POST" action="{{ route('bots.destroy', $bot) }}" data-submit-once>@csrf @method('DELETE')<button class="nd-sub-delete-confirm" type="submit">{{ __('Delete bot') }}</button></form></div></div></div>
    </div>

    <template id="question-row-template"><div class="nd-sub-sortable-row" data-question-row><span class="nd-sub-row-number" data-row-number></span><div><label class="visually-hidden" data-row-label></label><input class="form-control" maxlength="{{ config('neuraldesk.bots.limits.starter_question') }}" required></div><div class="nd-sub-row-actions"><button type="button" data-move-up aria-label="{{ __('Move question up') }}"><i class="iconoir-nav-arrow-up"></i></button><button type="button" data-move-down aria-label="{{ __('Move question down') }}"><i class="iconoir-nav-arrow-down"></i></button><button type="button" data-remove-row aria-label="{{ __('Remove question') }}"><i class="iconoir-xmark"></i></button></div></div></template>
    <template id="prechat-row-template"><div class="nd-sub-prechat-row" data-prechat-row><input type="hidden" data-standard-key><div class="nd-sub-prechat-top"><span class="nd-sub-row-number" data-row-number></span><div class="nd-sub-row-actions"><button type="button" data-move-up aria-label="{{ __('Move field up') }}"><i class="iconoir-nav-arrow-up"></i></button><button type="button" data-move-down aria-label="{{ __('Move field down') }}"><i class="iconoir-nav-arrow-down"></i></button><button type="button" data-remove-row aria-label="{{ __('Remove field') }}"><i class="iconoir-xmark"></i></button></div></div><div class="nd-sub-settings-grid two"><div class="nd-sub-settings-field"><label data-field-label>{{ __('Field label') }}</label><input class="form-control" data-field-label-input maxlength="100" required></div><div class="nd-sub-settings-field"><label data-type-label>{{ __('Field type') }}</label><select class="form-select" data-field-type>@foreach($fieldTypes as $type)<option value="{{ $type->value }}">{{ $type->label() }}</option>@endforeach</select></div><div class="nd-sub-settings-field"><label data-placeholder-label>{{ __('Placeholder') }}</label><input class="form-control" data-placeholder-input maxlength="255"></div><div class="nd-sub-settings-field" data-options-field hidden><label data-options-label>{{ __('Options, one per line') }}</label><textarea class="form-control" data-options-input rows="2"></textarea></div></div><div class="form-check"><input type="hidden" data-required-hidden value="0"><input class="form-check-input" data-required-check type="checkbox" value="1"><label class="form-check-label" data-required-label>{{ __('Required') }}</label></div></div></template>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-bot-settings-form]');
            if (!form) return;

            const questionList = form.querySelector('[data-question-list]');
            const prechatList = form.querySelector('[data-prechat-list]');
            const maxQuestions = {{ (int) config('neuraldesk.bots.limits.starter_questions') }};
            const maxFields = {{ (int) config('neuraldesk.bots.limits.prechat_fields') }};
            const standardFields = {
                name: { label: @json(__('Your name')), type: 'text', placeholder: @json(__('Enter your name')) },
                email: { label: @json(__('Email address')), type: 'email', placeholder: @json(__('you@example.com')) },
                phone: { label: @json(__('Phone number')), type: 'phone', placeholder: @json(__('+1 555 0100')) }
            };

            const reindexQuestions = () => {
                const rows = [...questionList.querySelectorAll('[data-question-row]')];
                rows.forEach((row, index) => {
                    const input = row.querySelector('input');
                    const label = row.querySelector('[data-row-label]');
                    row.querySelector('[data-row-number]').textContent = index + 1;
                    input.name = `starter_questions[${index}]`;
                    input.id = `starter-question-${index}`;
                    label.htmlFor = input.id;
                    label.textContent = @json(__('Starter question')) + ` ${index + 1}`;
                    row.querySelector('[data-move-up]').disabled = index === 0;
                    row.querySelector('[data-move-down]').disabled = index === rows.length - 1;
                });
                form.querySelector('[data-question-count]').textContent = `${rows.length} / ${maxQuestions}`;
                form.querySelector('[data-add-question]').disabled = rows.length >= maxQuestions;
                updatePreview();
            };

            const reindexFields = () => {
                const rows = [...prechatList.querySelectorAll('[data-prechat-row]')];
                rows.forEach((row, index) => {
                    row.querySelector('[data-row-number]').textContent = index + 1;
                    const bind = (selector, name, id, labelSelector) => {
                        const input = row.querySelector(selector);
                        if (!input) return;
                        input.name = `prechat_fields[${index}][${name}]`;
                        if (id) { input.id = `${id}-${index}`; row.querySelector(labelSelector)?.setAttribute('for', input.id); }
                    };
                    bind('[data-standard-key]', 'standard_key');
                    bind('[data-field-label-input], .nd-sub-settings-field:first-child input', 'label', 'prechat-label', '[data-field-label]');
                    bind('[data-field-type]', 'type', 'prechat-type', '[data-type-label]');
                    bind('[data-placeholder-input], input[id^="prechat-placeholder"]', 'placeholder', 'prechat-placeholder', '[data-placeholder-label]');
                    bind('[data-options-input], textarea[id^="prechat-options"]', 'options_text', 'prechat-options', '[data-options-label]');
                    bind('[data-required-hidden]', 'is_required');
                    bind('[data-required-check]', 'is_required', 'prechat-required', '[data-required-label]');
                    row.querySelector('[data-move-up]').disabled = index === 0;
                    row.querySelector('[data-move-down]').disabled = index === rows.length - 1;
                });
                form.querySelector('[data-field-count]').textContent = `${rows.length} / ${maxFields}`;
                form.querySelectorAll('[data-add-standard], [data-add-custom]').forEach(button => button.disabled = rows.length >= maxFields);
            };

            const updatePreview = () => {
                const displayName = form.querySelector('#bot-settings-display-name').value.trim() || form.querySelector('#bot-settings-name').value.trim();
                form.querySelector('[data-preview-name]').textContent = displayName || @json(__('Support bot'));
                form.querySelector('[data-preview-welcome]').textContent = form.querySelector('#bot-welcome-message').value.trim() || @json(__('Welcome message'));
                const preview = form.querySelector('[data-preview-questions]');
                preview.replaceChildren();
                questionList.querySelectorAll('input').forEach(input => {
                    if (!input.value.trim()) return;
                    const chip = document.createElement('span');
                    chip.textContent = input.value.trim();
                    preview.appendChild(chip);
                });
            };

            const moveRow = (button, direction) => {
                const row = button.closest('[data-question-row], [data-prechat-row]');
                const sibling = direction === 'up' ? row.previousElementSibling : row.nextElementSibling;
                if (!sibling) return;
                direction === 'up' ? row.parentNode.insertBefore(row, sibling) : row.parentNode.insertBefore(sibling, row);
                row.matches('[data-question-row]') ? reindexQuestions() : reindexFields();
                button.focus();
            };

            form.addEventListener('click', event => {
                const button = event.target.closest('button');
                if (!button) return;
                if (button.matches('[data-move-up]')) moveRow(button, 'up');
                if (button.matches('[data-move-down]')) moveRow(button, 'down');
                if (button.matches('[data-remove-row]')) {
                    const row = button.closest('[data-question-row], [data-prechat-row]');
                    const questions = row.matches('[data-question-row]');
                    row.remove();
                    questions ? reindexQuestions() : reindexFields();
                }
                if (button.matches('[data-add-question]') && questionList.children.length < maxQuestions) {
                    const row = document.getElementById('question-row-template').content.firstElementChild.cloneNode(true);
                    questionList.appendChild(row); reindexQuestions(); row.querySelector('input').focus();
                }
                if ((button.matches('[data-add-custom]') || button.matches('[data-add-standard]')) && prechatList.children.length < maxFields) {
                    const row = document.getElementById('prechat-row-template').content.firstElementChild.cloneNode(true);
                    const key = button.dataset.addStandard || '';
                    if (key) {
                        const definition = standardFields[key];
                        row.querySelector('[data-standard-key]').value = key;
                        row.querySelector('[data-field-label-input]').value = definition.label;
                        row.querySelector('[data-field-type]').value = definition.type;
                        row.querySelector('[data-placeholder-input]').value = definition.placeholder;
                    }
                    prechatList.appendChild(row); reindexFields(); row.querySelector('[data-field-label-input]').focus();
                }
            });

            form.addEventListener('change', event => {
                if (event.target.matches('[data-field-type]')) event.target.closest('[data-prechat-row]').querySelector('[data-options-field]').hidden = event.target.value !== 'select';
                updatePreview();
            });
            form.addEventListener('input', updatePreview);
            form.addEventListener('submit', () => document.querySelectorAll('#bot-settings-form button[type="submit"], button[form="bot-settings-form"]').forEach(button => { button.disabled = true; button.setAttribute('aria-disabled', 'true'); }));

            reindexQuestions(); reindexFields(); updatePreview();
        });
    </script>
@endpush
