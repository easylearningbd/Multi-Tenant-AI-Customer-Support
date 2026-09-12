(function () {
    'use strict';

    if (document.documentElement.dataset.adminUsersInitialized === 'true') return;
    document.documentElement.dataset.adminUsersInitialized = 'true';

    document.querySelectorAll('[data-auto-submit]').forEach(function (control) {
        control.addEventListener('change', function () { control.form?.requestSubmit(); });
    });

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

    const deleteModal = document.getElementById('delete-user-modal');
    deleteModal?.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        const form = deleteModal.querySelector('[data-delete-user-form]');
        const name = deleteModal.querySelector('[data-delete-user-name]');
        if (!(trigger instanceof HTMLElement) || !(form instanceof HTMLFormElement)) {
            event.preventDefault();
            return;
        }
        form.action = trigger.dataset.deleteUrl || '';
        if (name) name.textContent = trigger.dataset.deleteName || '';
    });

    const avatarInput = document.querySelector('[data-user-avatar-input]');
    const avatarImage = document.getElementById('profile-avatar-image');
    const avatarInitials = document.getElementById('profile-avatar-initials');
    const selectedFile = document.querySelector('[data-selected-file]');
    avatarInput?.addEventListener('change', function () {
        const file = avatarInput.files?.[0];
        if (!file) return;
        if (selectedFile) selectedFile.textContent = file.name;
        if (!avatarImage || !avatarInitials || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return;
        const previewUrl = URL.createObjectURL(file);
        avatarImage.src = previewUrl;
        avatarImage.classList.remove('d-none');
        avatarInitials.classList.add('d-none');
        avatarImage.addEventListener('load', function () { URL.revokeObjectURL(previewUrl); }, { once: true });
    });
}());
