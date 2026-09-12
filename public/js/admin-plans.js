(function () {
    'use strict';

    if (document.documentElement.dataset.adminPlansInitialized === 'true') return;
    document.documentElement.dataset.adminPlansInitialized = 'true';

    document.querySelectorAll('[data-submit-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const button = form.querySelector('[data-submit-button]');
            if (!button || button.disabled) return;
            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            form.setAttribute('aria-busy', 'true');
            button.querySelector('[data-submit-spinner]')?.classList.remove('d-none');
        });
    });

    const interval = document.querySelector('[data-plan-interval]');
    const trialGroup = document.querySelector('[data-trial-days-group]');
    const trialDays = document.querySelector('[data-trial-days]');

    function syncTrialField() {
        if (!interval || !trialGroup || !trialDays) return;
        const isTrial = interval.value === 'trial';
        trialGroup.classList.toggle('d-none', !isTrial);
        trialDays.disabled = !isTrial;
        trialDays.required = isTrial;
    }

    interval?.addEventListener('change', syncTrialField);
    syncTrialField();

    const deleteModal = document.getElementById('delete-plan-modal');
    deleteModal?.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const form = deleteModal.querySelector('[data-delete-plan-form]');
        const name = deleteModal.querySelector('[data-delete-plan-name]');
        if (!(trigger instanceof HTMLElement) || !(form instanceof HTMLFormElement)) {
            event.preventDefault();
            return;
        }
        form.action = trigger.dataset.deleteUrl || '';
        if (name) name.textContent = trigger.dataset.deleteName || '';
    });
}());
