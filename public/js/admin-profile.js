(function () {
    'use strict';

    if (document.documentElement.dataset.adminProfileInitialized === 'true') {
        return;
    }

    document.documentElement.dataset.adminProfileInitialized = 'true';

    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            const input = document.getElementById(button.dataset.passwordToggle);

            if (!input) {
                return;
            }

            const willShow = input.type === 'password';
            input.type = willShow ? 'text' : 'password';
            button.setAttribute('aria-label', willShow ? button.dataset.hideLabel : button.dataset.showLabel);

            const icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('iconoir-eye', !willShow);
                icon.classList.toggle('iconoir-eye-closed', willShow);
            }
        });
    });

    document.querySelectorAll('[data-submit-once]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const button = form.querySelector('[data-submit-button]');

            if (!button || button.disabled) {
                return;
            }

            button.disabled = true;
            button.setAttribute('aria-disabled', 'true');
            form.setAttribute('aria-busy', 'true');

            const spinner = button.querySelector('[data-submit-spinner]');
            if (spinner) {
                spinner.classList.remove('d-none');
            }
        });
    });

    const avatarInput = document.getElementById('admin-profile-avatar');
    const avatarImage = document.getElementById('profile-avatar-image');
    const avatarInitials = document.getElementById('profile-avatar-initials');
    const selectedFile = document.querySelector('[data-selected-file]');

    if (avatarInput && avatarImage && avatarInitials) {
        avatarInput.addEventListener('change', function () {
            const file = avatarInput.files && avatarInput.files[0];

            if (!file) {
                return;
            }

            if (selectedFile) {
                selectedFile.textContent = file.name;
            }

            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                return;
            }

            const previewUrl = URL.createObjectURL(file);
            avatarImage.src = previewUrl;
            avatarImage.classList.remove('d-none');
            avatarInitials.classList.add('d-none');
            avatarImage.addEventListener('load', function () {
                URL.revokeObjectURL(previewUrl);
            }, { once: true });
        });
    }
}());
