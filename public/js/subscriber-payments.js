(function () {
    'use strict';

    if (document.documentElement.dataset.subscriberPaymentsInitialized === 'true') return;
    document.documentElement.dataset.subscriberPaymentsInitialized = 'true';

    document.querySelectorAll('[data-copy-target]').forEach(function (button) {
        button.addEventListener('click', async function () {
            const target = document.getElementById(button.dataset.copyTarget || '');
            if (!target || !navigator.clipboard) return;

            try {
                await navigator.clipboard.writeText(target.textContent.trim());
                const label = button.querySelector('span');
                if (label) label.textContent = 'Copied';
                button.setAttribute('aria-label', 'Copied');
            } catch (_error) {
                button.setAttribute('aria-label', 'Copy failed');
            }
        });
    });

    const invalidField = document.querySelector('[data-payment-form] .is-invalid');
    if (invalidField) invalidField.focus();

    document.querySelectorAll('[data-single-submit]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const button = form.querySelector('button[type="submit"]');
            if (!button || button.disabled) return;
            button.disabled = true;
            if (button.dataset.loadingLabel) button.textContent = button.dataset.loadingLabel;
        });
    });
}());
