@php
    $toastItems = collect();
    $flashToast = session('toast');

    if (is_array($flashToast) && isset($flashToast['message'])) {
        $toastItems->push([
            'type' => in_array($flashToast['type'] ?? null, ['success', 'error', 'warning'], true) ? $flashToast['type'] : 'error',
            'title' => $flashToast['title'] ?? __('Notification'),
            'message' => $flashToast['message'],
            'messages' => [],
            'action_url' => is_string($flashToast['action_url'] ?? null) ? $flashToast['action_url'] : null,
            'action_label' => is_string($flashToast['action_label'] ?? null) ? $flashToast['action_label'] : null,
        ]);
    }

    foreach (['error' => __('Error'), 'warning' => __('Warning')] as $sessionKey => $title) {
        if (is_string(session($sessionKey)) && session($sessionKey) !== '') {
            $toastItems->push([
                'type' => $sessionKey,
                'title' => $title,
                'message' => session($sessionKey),
                'messages' => [],
                'action_url' => null,
                'action_label' => null,
            ]);
        }
    }

    $validationMessages = collect($errors->getBags())
        ->flatMap(fn ($bag) => $bag->all())
        ->unique()
        ->values();

    if ($validationMessages->isNotEmpty()) {
        $toastItems->push([
            'type' => 'error',
            'title' => __('Please review the form'),
            'message' => null,
            'messages' => $validationMessages,
            'action_url' => null,
            'action_label' => null,
        ]);
    }

    $toastIcons = [
        'success' => 'iconoir-check-circle',
        'warning' => 'iconoir-warning-circle',
        'error' => 'iconoir-xmark-circle',
    ];
@endphp

@if ($toastItems->isNotEmpty())
    <div class="toast-container position-fixed p-3 nd-toast-container" aria-live="polite" aria-atomic="true">
        @foreach ($toastItems as $toast)
            <div class="toast nd-toast nd-toast-{{ $toast['type'] }} show" role="{{ $toast['type'] === 'error' ? 'alert' : 'status' }}" aria-live="{{ $toast['type'] === 'error' ? 'assertive' : 'polite' }}" aria-atomic="true" data-bs-autohide="false">
                <div class="toast-header">
                    <span class="nd-toast-icon" aria-hidden="true"><i class="{{ $toastIcons[$toast['type']] }}"></i></span>
                    <strong class="me-auto">{{ $toast['title'] }}</strong>
                    <small>{{ __('Just now') }}</small>
                    <button type="button" class="btn-close ms-2" data-bs-dismiss="toast" aria-label="{{ __('Close notification') }}"></button>
                </div>
                <div class="toast-body">
                    @if ($toast['message'])
                        <p class="mb-0">{{ $toast['message'] }}</p>
                    @endif
                    @if (count($toast['messages']) > 0)
                        <ul class="mb-0 ps-3">
                            @foreach ($toast['messages'] as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($toast['action_url'] && $toast['action_label'])
                        <a class="btn btn-sm btn-dark mt-2" href="{{ $toast['action_url'] }}">{{ $toast['action_label'] }}</a>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endif
