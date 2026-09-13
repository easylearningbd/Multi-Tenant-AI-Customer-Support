(function () {
    'use strict';

    if (document.documentElement.dataset.subscriberSupportInitialized === 'true') return;
    document.documentElement.dataset.subscriberSupportInitialized = 'true';

    const invalidField = document.querySelector('[data-support-form] .is-invalid');
    if (invalidField) invalidField.focus();

    document.querySelectorAll('[data-attachment-picker]').forEach(function (picker) {
        const input = picker.querySelector('[data-attachment-input]');
        const list = picker.querySelector('[data-selected-files]');
        if (!input || !list) return;

        function render() {
            list.replaceChildren();

            Array.from(input.files || []).forEach(function (file, index) {
                const item = document.createElement('li');
                const name = document.createElement('span');
                const remove = document.createElement('button');

                name.textContent = file.name;
                remove.type = 'button';
                remove.textContent = 'Remove';
                remove.setAttribute('aria-label', 'Remove ' + file.name);
                remove.addEventListener('click', function () {
                    if (typeof DataTransfer === 'undefined') return;

                    const transfer = new DataTransfer();
                    Array.from(input.files || []).forEach(function (candidate, candidateIndex) {
                        if (candidateIndex !== index) transfer.items.add(candidate);
                    });
                    input.files = transfer.files;
                    render();
                });

                item.append(name, remove);
                list.append(item);
            });
        }

        input.addEventListener('change', render);
    });

    document.querySelectorAll('[data-single-submit]').forEach(function (form) {
        form.addEventListener('submit', function () {
            const button = form.querySelector('button[type="submit"]');
            if (!button || button.disabled) return;

            button.disabled = true;
            if (button.dataset.loadingLabel) button.textContent = button.dataset.loadingLabel;
        });
    });
}());
