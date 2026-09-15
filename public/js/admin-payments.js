(function () {
    'use strict';

    if (document.documentElement.dataset.adminPaymentsInitialized === 'true') return;
    document.documentElement.dataset.adminPaymentsInitialized = 'true';

    document.querySelectorAll('[data-payment-auto-submit]').forEach(function (control) {
        control.addEventListener('change', function () { control.form?.requestSubmit(); });
    });

    document.querySelectorAll('[data-payment-submit-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const button = form.querySelector('[data-payment-submit-button]');
            if (!button || button.disabled) return;
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            form.setAttribute('aria-busy', 'true');
            if (button.dataset.loadingLabel) button.textContent = button.dataset.loadingLabel;
        });
    });

    if (document.querySelector('#approve-payment-modal .is-invalid')) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('approve-payment-modal')).show();
    } else if (document.querySelector('#reject-payment-modal .is-invalid')) {
        bootstrap.Modal.getOrCreateInstance(document.getElementById('reject-payment-modal')).show();
    }
}());
