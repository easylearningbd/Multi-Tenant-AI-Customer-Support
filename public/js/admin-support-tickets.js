(function () {
    'use strict';

    if (document.documentElement.dataset.adminSupportTicketsInitialized === 'true') return;
    document.documentElement.dataset.adminSupportTicketsInitialized = 'true';

    document.querySelectorAll('[data-ticket-auto-submit]').forEach(function (control) {
        control.addEventListener('change', function () { control.form?.requestSubmit(); });
    });

    document.querySelectorAll('[data-ticket-submit-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const button = form.querySelector('[data-ticket-submit-button]');
            if (!button || button.disabled) return;
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            form.setAttribute('aria-busy', 'true');
            if (button.dataset.loadingLabel) button.textContent = button.dataset.loadingLabel;
        });
    });

    document.querySelectorAll('[data-ticket-attachment-picker]').forEach(function (picker) {
        const input = picker.querySelector('[data-ticket-attachment-input]');
        const list = picker.querySelector('[data-ticket-selected-files]');
        if (!input || !list) return;

        input.addEventListener('change', function () {
            list.replaceChildren();
            Array.from(input.files || []).forEach(function (file) {
                const item = document.createElement('li');
                item.textContent = file.name;
                list.append(item);
            });
        });
    });

    const archiveModal = document.getElementById('archive-ticket-modal');
    archiveModal?.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const form = archiveModal.querySelector('[data-archive-ticket-form]');
        if (!(trigger instanceof HTMLElement) || !(form instanceof HTMLFormElement)) {
            event.preventDefault();
            return;
        }

        form.action = trigger.dataset.archiveUrl || '';
        const reference = archiveModal.querySelector('[data-archive-ticket-reference]');
        const subject = archiveModal.querySelector('[data-archive-ticket-subject]');
        if (reference) reference.textContent = trigger.dataset.archiveReference || '';
        if (subject) subject.textContent = trigger.dataset.archiveSubject || '';
    });

    document.querySelector('[data-admin-ticket-form] .is-invalid')?.focus();
}());
