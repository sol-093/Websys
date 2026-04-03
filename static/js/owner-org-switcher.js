(function () {
    const wrappers = Array.from(document.querySelectorAll('[data-dropdown-wrapper]'));
    if (wrappers.length === 0) return;

    const instances = wrappers.map(function (wrapper) {
        const root = wrapper.closest('[data-dropdown-root]') || wrapper;
        const button = wrapper.querySelector('[data-dropdown-toggle]');
        const menu = wrapper.querySelector('[data-dropdown-menu]');
        const hiddenInput = wrapper.querySelector('[data-dropdown-value]') || root.querySelector('[data-dropdown-value]');
        const label = wrapper.querySelector('[data-dropdown-label]') || root.querySelector('[data-dropdown-label]');

        if (!button || !menu || !hiddenInput || !label) {
            return null;
        }

        const setOpen = function (isOpen) {
            menu.classList.toggle('hidden', !isOpen);
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        };

        const setActiveItem = function (activeButton) {
            menu.querySelectorAll('.my-org-switcher-item').forEach(function (item) {
                item.classList.remove('is-active');
            });
            activeButton.classList.add('is-active');
        };

        button.addEventListener('click', function (event) {
            event.preventDefault();
            const shouldOpen = menu.classList.contains('hidden');

            instances.forEach(function (instance) {
                if (instance && instance !== api) {
                    instance.setOpen(false);
                }
            });

            setOpen(shouldOpen);
        });

        menu.querySelectorAll('[data-option-value], [data-org-id]').forEach(function (optionButton) {
            optionButton.addEventListener('click', function () {
                const optionValue = optionButton.getAttribute('data-option-value') ?? optionButton.getAttribute('data-org-id') ?? '';
                const optionLabel = optionButton.getAttribute('data-option-label') || optionButton.getAttribute('data-org-name') || optionButton.textContent || '';

                hiddenInput.value = optionValue;

                label.textContent = optionLabel.trim();
                setActiveItem(optionButton);
                setOpen(false);
            });
        });

        const api = {
            root: root,
            setOpen: setOpen,
        };

        return api;
    }).filter(Boolean);

    document.addEventListener('click', function (event) {
        instances.forEach(function (instance) {
            if (!instance.root.contains(event.target)) {
                instance.setOpen(false);
            }
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            instances.forEach(function (instance) {
                instance.setOpen(false);
            });
        }
    });
})();
