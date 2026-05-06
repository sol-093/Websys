/* ================================================
   INVOLVE - OWNER ORGANIZATION SWITCHER
   ================================================

    SECTION MAP:
   1. Find Dropdown Wrappers
   2. Apply Light/Dark Menu Styling
   3. Sync Selected Values
   4. Handle Click and Keyboard Behavior

    WORK GUIDE:
   - Edit this file for owner/admin organization switcher dropdown behavior.
   ================================================ */

(function () {
    const wrappers = Array.from(document.querySelectorAll('[data-dropdown-wrapper]'));
    if (wrappers.length === 0) return;

    const buttonThemeClasses = {
        light: ['bg-[#E6FFF4]/85', 'text-emerald-900', 'border-emerald-500/25', 'hover:bg-[#E6FFF4]/95', 'hover:border-emerald-600/35', 'shadow-[0_4px_12px_rgba(2,44,34,0.10)]'],
        dark: ['bg-emerald-950/90', 'text-emerald-100', 'border-emerald-400/35', 'hover:bg-emerald-800/90', 'hover:border-emerald-300/45', 'shadow-[0_4px_12px_rgba(0,0,0,0.12)]'],
    };

    const menuThemeClasses = {
        light: ['bg-[#E6FFF4]/90', 'border-emerald-500/25', 'shadow-[0_6px_16px_rgba(2,44,34,0.10)]'],
        dark: ['bg-emerald-950/95', 'border-emerald-400/30', 'shadow-[0_8px_18px_rgba(0,0,0,0.18)]'],
    };

    const itemThemeClasses = {
        light: ['text-emerald-800', 'hover:bg-emerald-500/40', 'hover:text-emerald-50'],
        dark: ['text-emerald-100', 'hover:bg-emerald-500/35', 'hover:text-emerald-50'],
    };

    const activeThemeClasses = {
        light: ['bg-emerald-600/55', 'text-emerald-50'],
        dark: ['bg-emerald-600/45', 'text-emerald-50'],
    };

    const allThemeClasses = [
        ...buttonThemeClasses.light,
        ...buttonThemeClasses.dark,
        ...menuThemeClasses.light,
        ...menuThemeClasses.dark,
        ...itemThemeClasses.light,
        ...itemThemeClasses.dark,
        ...activeThemeClasses.light,
        ...activeThemeClasses.dark,
    ];

    const isDarkTheme = function () {
        return document.body.classList.contains('theme-dark');
    };

    const applyThemeClasses = function (button, menu, items) {
        const themeKey = isDarkTheme() ? 'dark' : 'light';

        button.classList.remove(...buttonThemeClasses.light, ...buttonThemeClasses.dark);
        button.classList.add(...buttonThemeClasses[themeKey]);

        menu.classList.remove(...menuThemeClasses.light, ...menuThemeClasses.dark);
        menu.classList.add(...menuThemeClasses[themeKey]);

        items.forEach(function (item) {
            const isActive = item.dataset.active === 'true';
            item.classList.remove(...allThemeClasses);
            item.classList.add(...itemThemeClasses[themeKey]);
            if (isActive) {
                item.classList.add(...activeThemeClasses[themeKey]);
            }
        });
    };

    const instances = wrappers.map(function (wrapper) {
        const root = wrapper.closest('[data-dropdown-root]') || wrapper;
        const button = wrapper.querySelector('[data-dropdown-toggle]');
        const menu = wrapper.querySelector('[data-dropdown-menu]');
        const hiddenInput = wrapper.querySelector('[data-dropdown-value]') || root.querySelector('[data-dropdown-value]');
        const label = wrapper.querySelector('[data-dropdown-label]') || root.querySelector('[data-dropdown-label]');
        const optionItems = Array.from(menu ? menu.querySelectorAll('[data-dropdown-option]') : []);

        if (!button || !menu || !hiddenInput || !label) {
            return null;
        }

        const setOpen = function (isOpen) {
            menu.classList.toggle('hidden', !isOpen);
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        };

        const setActiveItem = function (activeButton) {
            optionItems.forEach(function (item) {
                item.dataset.active = item === activeButton ? 'true' : 'false';
            });
            applyThemeClasses(button, menu, optionItems);
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

        optionItems.forEach(function (optionButton) {
            optionButton.addEventListener('click', function () {
                const optionValue = optionButton.getAttribute('data-option-value') ?? optionButton.getAttribute('data-org-id') ?? '';
                const optionLabel = optionButton.getAttribute('data-option-label') || optionButton.getAttribute('data-org-name') || optionButton.textContent || '';

                hiddenInput.value = optionValue;
                hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));

                label.textContent = optionLabel.trim();
                setActiveItem(optionButton);
                setOpen(false);
            });
        });

        const api = {
            root: root,
            setOpen: setOpen,
            applyTheme: function () {
                applyThemeClasses(button, menu, optionItems);
            },
        };

        applyThemeClasses(button, menu, optionItems);

        return api;
    }).filter(Boolean);

    const bodyObserver = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                instances.forEach(function (instance) {
                    instance.applyTheme();
                });
            }
        });
    });

    bodyObserver.observe(document.body, {
        attributes: true,
        attributeFilter: ['class'],
    });

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
