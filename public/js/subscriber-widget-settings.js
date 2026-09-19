(function () {
    'use strict';

    function valueFor(target) {
        return 'value' in target ? target.value : target.textContent;
    }

    async function copyText(value) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(value);
            return;
        }

        const textarea = document.createElement('textarea');
        textarea.value = value;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        const copied = document.execCommand('copy');
        textarea.remove();

        if (!copied) {
            throw new Error('Copy failed');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('[data-widget-settings]');
        if (!root) return;

        const status = root.querySelector('[data-copy-status]');
        root.querySelectorAll('[data-copy-target]').forEach(function (button) {
            button.addEventListener('click', async function () {
                const target = document.getElementById(button.dataset.copyTarget);
                const label = button.querySelector('span');
                if (!target) return;

                try {
                    await copyText(valueFor(target));
                    if (label) label.textContent = button.dataset.copiedLabel;
                    if (status) status.textContent = button.dataset.copiedLabel;
                    window.setTimeout(function () {
                        if (label) label.textContent = button.dataset.copyLabel;
                    }, 1800);
                } catch (error) {
                    if (status) status.textContent = 'Copy failed. Select and copy the value manually.';
                }
            });
        });

        const preview = root.querySelector('[data-widget-preview]');
        const message = root.querySelector('[data-preview-message]');
        const statusLabel = root.querySelector('[data-preview-status]');
        const messageInput = document.getElementById('widget-welcome-message');
        const enabledInput = document.getElementById('widget-enabled');

        root.querySelectorAll('input[name="accent_color"]').forEach(function (input) {
            input.addEventListener('change', function () {
                if (input.checked) preview?.style.setProperty('--nd-widget-accent', input.value);
            });
        });

        root.querySelectorAll('input[name="position"]').forEach(function (input) {
            input.addEventListener('change', function () {
                if (!input.checked || !preview) return;
                preview.classList.toggle('is-left', input.value === 'bottom_left');
                preview.classList.toggle('is-right', input.value === 'bottom_right');
            });
        });

        messageInput?.addEventListener('input', function () {
            if (message) message.textContent = messageInput.value;
        });

        enabledInput?.addEventListener('change', function () {
            preview?.classList.toggle('is-disabled', !enabledInput.checked);
            if (statusLabel) statusLabel.textContent = enabledInput.checked ? 'Launcher is enabled' : 'Launcher is disabled';
        });

        if (!enabledInput?.checked) preview?.classList.add('is-disabled');

        root.querySelector('[data-widget-form]')?.addEventListener('submit', function (event) {
            const submit = event.currentTarget.querySelector('button[type="submit"]');
            if (submit) {
                submit.disabled = true;
                submit.setAttribute('aria-disabled', 'true');
            }
        });
    });
})();
