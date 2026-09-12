(function () {
    'use strict';

    if (document.documentElement.dataset.subscriberProfileInitialized === 'true') return;
    document.documentElement.dataset.subscriberProfileInitialized = 'true';

    const avatarInput = document.querySelector('[data-avatar-input]');
    const avatarPreview = document.querySelector('[data-avatar-preview]');
    const avatarFileName = document.querySelector('[data-avatar-file-name]');
    let previewUrl = null;

    avatarInput?.addEventListener('change', function () {
        const file = avatarInput.files?.[0];
        if (!file) return;

        if (previewUrl) URL.revokeObjectURL(previewUrl);
        previewUrl = URL.createObjectURL(file);

        const image = document.createElement('img');
        image.src = previewUrl;
        image.alt = 'Selected profile photo preview';
        avatarPreview?.replaceChildren(image);

        if (avatarFileName) avatarFileName.textContent = file.name;
    });

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.passwordToggle);
            if (!input) return;

            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            button.querySelector('i')?.classList.toggle('iconoir-eye-closed', show);
            button.querySelector('i')?.classList.toggle('iconoir-eye', !show);
        });
    });

    document.querySelectorAll('[data-single-submit]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const button = form.querySelector('button[type="submit"]');
            if (!button || button.disabled) return;

            button.disabled = true;
            if (button.dataset.loadingLabel) button.textContent = button.dataset.loadingLabel;
        });
    });

    window.addEventListener('beforeunload', function () {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
    });
}());
