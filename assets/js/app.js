/* ================================================
   INVOLVE - GLOBAL APP BEHAVIOR
   Shared JavaScript loaded by src/core/layout.php
   ================================================

   TABLE OF CONTENTS:
   1. Navigation Scroll State
   2. Global Search Modal
   3. Toast Notifications
   4. Currency Inputs
   5. Password Visibility Toggles
   6. Form Loading Buttons
   7. Theme, CSRF, Mobile Nav, Modals, and Touch Drag
   8. Student Onboarding Tour

   EDIT GUIDE:
   - Change search behavior in section 2.
   - Change toasts/forms/theme/mobile nav in sections 3 to 7.
   - Change onboarding steps and completion behavior in section 8.
   - Keep page-only behavior in assets/js/<page>.js.
   ================================================ */

// ================================================
// 1. NAVIGATION SCROLL STATE
// ================================================
(function () {
                var nav = document.getElementById('appNav');
                if (!nav) {
                    return;
                }

                var updateScrolledState = function () {
                    nav.classList.toggle('scrolled', window.scrollY > 20);
                };

                updateScrolledState();
                window.addEventListener('scroll', updateScrolledState, { passive: true });
            })();

// ================================================
// 2. GLOBAL SEARCH MODAL
// ================================================
(function () {
                    var modal = document.getElementById('globalSearchModal');
                    var openButtons = [document.getElementById('globalSearchOpen'), document.getElementById('globalSearchOpenMobile')].filter(function (button) { return Boolean(button); });
                    var closeButton = document.getElementById('globalSearchClose');
                    var input = document.getElementById('globalSearchInput');
                    var status = document.getElementById('globalSearchStatus');
                    var resultsContainer = document.getElementById('globalSearchResults');

                    if (!modal || !input || !status || !resultsContainer) {
                        return;
                    }

                    var requestId = 0;
                    var requestController = null;
                    var searchTimer = null;
                    var activeIndex = -1;
                    var currentResults = [];
                    var lastTrigger = null;

                    var typeLabels = {
                        user: 'User',
                        org: 'Organization',
                        announcement: 'Announcement',
                    };

                    var typeIcons = {
                        user: 'U',
                        org: 'O',
                        announcement: 'A',
                    };

                    var setBodyLocked = function (locked) {
                        document.body.style.overflow = locked ? 'hidden' : '';
                    };

                    var setStatus = function (message) {
                        status.textContent = message;
                    };

                    var openModal = function (trigger) {
                        lastTrigger = trigger || document.activeElement;
                        modal.classList.remove('hidden');
                        modal.setAttribute('aria-hidden', 'false');
                        setBodyLocked(true);
                        currentResults = [];
                        activeIndex = -1;
                        resultsContainer.innerHTML = '';
                        setStatus('Type at least 2 characters to search.');
                        window.setTimeout(function () {
                            input.focus();
                            input.select();
                        }, 0);
                    };

                    var closeModal = function () {
                        modal.classList.add('hidden');
                        modal.setAttribute('aria-hidden', 'true');
                        setBodyLocked(false);
                        input.value = '';
                        if (requestController) {
                            requestController.abort();
                            requestController = null;
                        }
                        if (searchTimer) {
                            window.clearTimeout(searchTimer);
                            searchTimer = null;
                        }
                        if (lastTrigger && typeof lastTrigger.focus === 'function') {
                            lastTrigger.focus();
                        }
                    };

                    var renderResults = function (results) {
                        currentResults = results;
                        activeIndex = results.length > 0 ? 0 : -1;
                        resultsContainer.innerHTML = '';

                        if (results.length === 0) {
                            resultsContainer.innerHTML = '<div class="global-search-empty">No results found.</div>';
                            return;
                        }

                        results.forEach(function (result, index) {
                            var link = document.createElement('a');
                            link.href = result.url;
                            link.className = 'global-search-result';
                            link.setAttribute('role', 'option');
                            link.setAttribute('aria-selected', index === activeIndex ? 'true' : 'false');
                            link.dataset.resultIndex = String(index);

                            if (index === activeIndex) {
                                link.classList.add('is-active');
                            }

                            link.innerHTML =
                                '<span class="global-search-result-icon">' + (typeIcons[result.type] || '?') + '</span>' +
                                '<span class="min-w-0 flex-1">' +
                                    '<span class="block font-semibold text-slate-800 truncate">' + result.label + '</span>' +
                                    '<span class="block text-sm global-search-result-meta truncate">' + result.sublabel + '</span>' +
                                '</span>' +
                                '<span class="global-search-shortcut">' + (typeLabels[result.type] || 'Result') + '</span>';

                            link.addEventListener('mouseenter', function () {
                                activeIndex = index;
                                updateActiveState();
                            });

                            link.addEventListener('click', function () {
                                closeModal();
                            });

                            resultsContainer.appendChild(link);
                        });
                    };

                    var updateActiveState = function () {
                        var resultNodes = resultsContainer.querySelectorAll('.global-search-result');
                        resultNodes.forEach(function (node, index) {
                            var isActive = index === activeIndex;
                            node.classList.toggle('is-active', isActive);
                            node.setAttribute('aria-selected', isActive ? 'true' : 'false');
                            if (isActive && typeof node.scrollIntoView === 'function') {
                                node.scrollIntoView({ block: 'nearest' });
                            }
                        });
                    };

                    var moveSelection = function (step) {
                        if (currentResults.length === 0) {
                            return;
                        }

                        activeIndex = (activeIndex + step + currentResults.length) % currentResults.length;
                        updateActiveState();
                    };

                    var search = function (query) {
                        var trimmed = query.trim();
                        if (trimmed.length < 2) {
                            if (requestController) {
                                requestController.abort();
                                requestController = null;
                            }
                            currentResults = [];
                            activeIndex = -1;
                            resultsContainer.innerHTML = '';
                            setStatus('Type at least 2 characters to search.');
                            return;
                        }

                        setStatus('Searching...');
                        var currentRequestId = ++requestId;

                        if (requestController) {
                            requestController.abort();
                        }

                        requestController = window.AbortController ? new AbortController() : null;

                        fetch('?action=search&q=' + encodeURIComponent(trimmed), {
                            headers: {
                                'Accept': 'application/json'
                            },
                            credentials: 'same-origin',
                            signal: requestController ? requestController.signal : undefined,
                        }).then(function (response) {
                            if (!response.ok) {
                                throw new Error('Search request failed');
                            }

                            return response.json();
                        }).then(function (payload) {
                            if (currentRequestId !== requestId) {
                                return;
                            }

                            var results = Array.isArray(payload.results) ? payload.results : [];
                            if (results.length === 0) {
                                setStatus('No results found for "' + trimmed + '".');
                            } else {
                                setStatus('Showing ' + results.length + ' result' + (results.length === 1 ? '' : 's') + ' for "' + trimmed + '".');
                            }

                            renderResults(results);
                        }).catch(function (error) {
                            if (error && error.name === 'AbortError') {
                                return;
                            }

                            if (currentRequestId !== requestId) {
                                return;
                            }

                            currentResults = [];
                            activeIndex = -1;
                            resultsContainer.innerHTML = '<div class="global-search-empty">Search is temporarily unavailable.</div>';
                            setStatus('Search is temporarily unavailable.');
                        }).finally(function () {
                            if (currentRequestId === requestId) {
                                requestController = null;
                            }
                        });
                    };

                    var scheduleSearch = function () {
                        if (searchTimer) {
                            window.clearTimeout(searchTimer);
                        }

                        searchTimer = window.setTimeout(function () {
                            search(input.value);
                        }, 180);
                    };

                    openButtons.forEach(function (button) {
                        button.addEventListener('click', function () {
                            openModal(button);
                        });
                    });

                    if (closeButton) {
                        closeButton.addEventListener('click', closeModal);
                    }

                    modal.addEventListener('click', function (event) {
                        if (event.target === modal) {
                            closeModal();
                        }
                    });

                    input.addEventListener('input', scheduleSearch);
                    input.addEventListener('keydown', function (event) {
                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            moveSelection(1);
                            return;
                        }

                        if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            moveSelection(-1);
                            return;
                        }

                        if (event.key === 'Enter' && currentResults.length > 0) {
                            event.preventDefault();
                            window.location.href = currentResults[activeIndex < 0 ? 0 : activeIndex].url;
                            return;
                        }

                        if (event.key === 'Escape') {
                            event.preventDefault();
                            closeModal();
                        }
                    });

                    document.addEventListener('keydown', function (event) {
                        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
                            event.preventDefault();
                            openModal(document.getElementById('globalSearchOpen') || document.getElementById('globalSearchOpenMobile'));
                        }

                        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                            closeModal();
                        }
                    });
                })();

// ================================================
// 3. TOAST NOTIFICATIONS
// ================================================
(function () {
                var container = document.getElementById('toast-container');
                if (!container) {
                    return;
                }

                var typeLabels = {
                    success: 'Success',
                    error: 'Error',
                    warning: 'Warning',
                    info: 'Info'
                };

                var normalizeType = function (type) {
                    var value = String(type || 'info').toLowerCase();
                    return Object.prototype.hasOwnProperty.call(typeLabels, value) ? value : 'info';
                };

                var dismissToast = function (toast) {
                    if (!toast || toast.dataset.toastState === 'leaving') {
                        return;
                    }

                    toast.dataset.toastState = 'leaving';
                    toast.classList.add('is-leaving');

                    var remove = function () {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    };

                    toast.addEventListener('animationend', remove, { once: true });
                    window.setTimeout(remove, 260);
                };

                var showToast = function (message, type, duration) {
                    var text = String(message || '').trim();
                    if (text === '') {
                        return null;
                    }

                    var toastType = normalizeType(type);
                    var displayDuration = Number(duration);
                    if (!Number.isFinite(displayDuration) || displayDuration < 0) {
                        displayDuration = 4000;
                    }

                    var toast = document.createElement('div');
                    toast.className = 'toast toast-' + toastType;
                    toast.setAttribute('role', 'status');
                    toast.setAttribute('aria-live', toastType === 'error' ? 'assertive' : 'polite');

                    var body = document.createElement('div');
                    body.className = 'toast-body';

                    var accent = document.createElement('div');
                    accent.className = 'toast-accent';

                    var content = document.createElement('div');
                    content.className = 'toast-content';

                    var title = document.createElement('p');
                    title.className = 'toast-title';
                    title.textContent = typeLabels[toastType];

                    var messageNode = document.createElement('div');
                    messageNode.className = 'toast-message';
                    messageNode.textContent = text;

                    content.appendChild(title);
                    content.appendChild(messageNode);

                    var closeButton = document.createElement('button');
                    closeButton.type = 'button';
                    closeButton.className = 'toast-close';
                    closeButton.setAttribute('aria-label', 'Dismiss notification');
                    closeButton.innerHTML = '&times;';
                    closeButton.addEventListener('click', function () {
                        dismissToast(toast);
                    });

                    body.appendChild(accent);
                    body.appendChild(content);
                    body.appendChild(closeButton);
                    toast.appendChild(body);

                    container.appendChild(toast);

                    if (displayDuration > 0) {
                        window.setTimeout(function () {
                            dismissToast(toast);
                        }, displayDuration);
                    }

                    return toast;
                };

                window.Toast = {
                    show: showToast,
                    dismiss: dismissToast
                };

                document.addEventListener('DOMContentLoaded', function () {
                    var flashMessage = document.body.dataset.flash || '';
                    if (flashMessage.trim() === '') {
                        return;
                    }

                    showToast(flashMessage, document.body.dataset.flashType || 'info', 4000);
                });
            })();

// ================================================
// 4. CURRENCY INPUTS
// ================================================
function initCurrencyInput(inputEl) {
                if (!(inputEl instanceof HTMLInputElement) || inputEl.dataset.currencyInitialized === '1') {
                    return;
                }

                const parent = inputEl.parentElement;
                if (!parent) {
                    return;
                }

                let wrapper = parent;
                if (!parent.classList.contains('currency-input-wrap')) {
                    wrapper = document.createElement('div');
                    wrapper.className = 'currency-input-wrap';
                    parent.insertBefore(wrapper, inputEl);
                    wrapper.appendChild(inputEl);
                }

                if (!wrapper.querySelector('.currency-prefix')) {
                    const prefix = document.createElement('span');
                    prefix.className = 'currency-prefix';
                    prefix.textContent = '₱';
                    wrapper.insertBefore(prefix, inputEl);
                }

                if (inputEl.type === 'number') {
                    inputEl.type = 'text';
                }
                inputEl.setAttribute('inputmode', 'decimal');
                inputEl.dataset.currencyInitialized = '1';

                const toRaw = function (value, preserveTrailingDot) {
                    const cleaned = String(value || '').replace(/[^\d.]/g, '');
                    if (cleaned === '') {
                        return '';
                    }

                    const firstDotIndex = cleaned.indexOf('.');
                    const hasTrailingDot = preserveTrailingDot === true && cleaned.endsWith('.') && firstDotIndex !== -1;
                    let integerPart = cleaned;
                    let decimalPart = '';

                    if (firstDotIndex !== -1) {
                        integerPart = cleaned.slice(0, firstDotIndex);
                        decimalPart = cleaned.slice(firstDotIndex + 1).replace(/\./g, '').slice(0, 2);
                    }

                    if (integerPart === '' && decimalPart !== '') {
                        integerPart = '0';
                    }

                    integerPart = integerPart.replace(/^0+(?=\d)/, '');
                    if (integerPart === '' && decimalPart === '') {
                        return '';
                    }

                    if (hasTrailingDot && decimalPart === '') {
                        return (integerPart !== '' ? integerPart : '0') + '.';
                    }

                    return decimalPart !== '' ? integerPart + '.' + decimalPart : integerPart;
                };

                const toFormatted = function (rawValue) {
                    if (rawValue === '') {
                        return '';
                    }

                    const parts = rawValue.split('.');
                    const intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                    if (rawValue.endsWith('.')) {
                        return intPart + '.';
                    }

                    if (parts.length < 2) {
                        return intPart;
                    }

                    return intPart + '.' + parts[1].slice(0, 2);
                };

                const toFixedCurrencyRaw = function (sourceValue) {
                    const raw = toRaw(sourceValue, false);
                    if (raw === '') {
                        return '';
                    }

                    const numberValue = Number.parseFloat(raw);
                    return Number.isFinite(numberValue) ? numberValue.toFixed(2) : raw;
                };

                const syncCurrencyValue = function (sourceValue, preserveTrailingDot) {
                    const raw = toRaw(sourceValue, preserveTrailingDot === true);
                    inputEl.dataset.currencyRaw = raw;
                    inputEl.value = toFormatted(raw);
                };

                inputEl.addEventListener('input', function () {
                    syncCurrencyValue(inputEl.value, true);
                });

                inputEl.addEventListener('blur', function () {
                    const fixedRaw = toFixedCurrencyRaw(inputEl.value);
                    inputEl.dataset.currencyRaw = fixedRaw;
                    inputEl.value = toFormatted(fixedRaw);
                });

                inputEl.form?.addEventListener('submit', function (event) {
                    const raw = toFixedCurrencyRaw(inputEl.value);
                    inputEl.dataset.currencyRaw = raw;
                    inputEl.value = raw;

                    // Restore formatted display if submission is cancelled on the client side.
                    window.setTimeout(function () {
                        if (event.defaultPrevented) {
                            inputEl.value = toFormatted(inputEl.dataset.currencyRaw || '');
                        }
                    }, 0);
                });

                const initialRaw = toFixedCurrencyRaw(inputEl.value);
                inputEl.dataset.currencyRaw = initialRaw;
                inputEl.value = toFormatted(initialRaw);
            }

// ================================================
// 5. PASSWORD VISIBILITY TOGGLES
// ================================================
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('input[data-currency]').forEach(function (inputEl) {
                    initCurrencyInput(inputEl);
                });
            });

// ================================================
// 6. FORM LOADING BUTTONS
// ================================================
            document.addEventListener('DOMContentLoaded', function () {
                var toggleInputs = document.querySelectorAll('input[data-password-toggle]');

                var eyeOpenSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="ui-icon" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6 9.75-6 9.75 6 9.75 6-3.75 6-9.75 6-9.75-6-9.75-6z" /><circle cx="12" cy="12" r="2.25" /></svg>';
                var eyeSlashSvg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="ui-icon" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" /><path stroke-linecap="round" stroke-linejoin="round" d="M10.58 10.58a2 2 0 102.83 2.83" /><path stroke-linecap="round" stroke-linejoin="round" d="M9.88 5.09A10.87 10.87 0 0112 4.88c6 0 9.75 7.12 9.75 7.12a19.27 19.27 0 01-3.04 4.13" /><path stroke-linecap="round" stroke-linejoin="round" d="M6.61 6.61A19.18 19.18 0 002.25 12S6 19.12 12 19.12c1.79 0 3.35-.41 4.72-1.03" /></svg>';

                toggleInputs.forEach(function (input) {
                    var wrapper = input.parentElement;
                    if (!wrapper || !wrapper.classList.contains('relative') || wrapper.getAttribute('data-password-toggle-wrapper') !== '1') {
                        var newWrapper = document.createElement('div');
                        newWrapper.className = 'relative';
                        newWrapper.setAttribute('data-password-toggle-wrapper', '1');
                        input.parentNode.insertBefore(newWrapper, input);
                        newWrapper.appendChild(input);
                        wrapper = newWrapper;
                    }

                    if (wrapper.querySelector('button[data-password-toggle-btn]')) {
                        return;
                    }

                    input.classList.add('pr-10');

                    var button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'absolute inset-y-0 right-0 px-3 inline-flex items-center text-slate-600 hover:text-slate-900';
                    button.setAttribute('data-password-toggle-btn', '1');
                    button.setAttribute('aria-label', 'Show password');
                    button.setAttribute('aria-pressed', 'false');
                    button.innerHTML = eyeOpenSvg;

                    button.addEventListener('click', function () {
                        var showing = input.type === 'text';
                        input.type = showing ? 'password' : 'text';
                        button.setAttribute('aria-pressed', showing ? 'false' : 'true');
                        button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
                        button.innerHTML = showing ? eyeOpenSvg : eyeSlashSvg;
                    });

                    wrapper.appendChild(button);
                });
            });

document.addEventListener('DOMContentLoaded', function () {
                var postForms = document.querySelectorAll('form[method="post"], form[method="POST"]');

                var restoreButton = function (button) {
                    if (!button || button.dataset.loadingState !== '1') {
                        return;
                    }

                    button.disabled = false;
                    button.classList.remove('btn-loading');
                    if (typeof button.dataset.originalHtml === 'string') {
                        button.innerHTML = button.dataset.originalHtml;
                    }
                    delete button.dataset.loadingState;
                    delete button.dataset.originalHtml;
                };

                postForms.forEach(function (form) {
                    if (form.hasAttribute('data-no-loading')) {
                        return;
                    }

                    var submitButtons = form.querySelectorAll('button[type="submit"]');
                    if (submitButtons.length === 0) {
                        return;
                    }

                    form.addEventListener('submit', function (event) {
                        var activeElement = document.activeElement;
                        var submitButton = activeElement instanceof HTMLButtonElement && activeElement.type === 'submit' && form.contains(activeElement)
                            ? activeElement
                            : submitButtons[0];

                        if (!submitButton || submitButton.dataset.loadingState === '1') {
                            return;
                        }

                        submitButton.dataset.loadingState = '1';
                        submitButton.dataset.originalHtml = submitButton.innerHTML;
                        submitButton.disabled = true;
                        submitButton.classList.add('btn-loading');
                        submitButton.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" class="btn-loading-spinner" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="42 18"></circle></svg><span>Processing...</span>';

                        // If a submit handler cancels submission, revert the button state.
                        window.setTimeout(function () {
                            if (event.defaultPrevented) {
                                restoreButton(submitButton);
                            }
                        }, 0);

                        // Safety net for long-running requests or navigation cancelled by the browser.
                        window.setTimeout(function () {
                            restoreButton(submitButton);
                        }, 15000);
                    });
                });

                window.addEventListener('pageshow', function () {
                    var loadingButtons = document.querySelectorAll('button[data-loading-state="1"]');
                    loadingButtons.forEach(function (button) {
                        restoreButton(button);
                    });
                });
            });

// ================================================
// 7. THEME, CSRF, MOBILE NAV, MODALS, AND TOUCH DRAG
// ================================================
(function () {
                const root = document.body;
                const key = 'websys-theme';
                const paginationScrollKey = 'websys-pagination-scroll-y';
                const csrfToken = document.body.dataset.csrfToken || '';

                const savedScroll = sessionStorage.getItem(paginationScrollKey);
                if (savedScroll !== null) {
                    const y = Number.parseInt(savedScroll, 10);
                    if (!Number.isNaN(y) && y >= 0) {
                        window.scrollTo(0, y);
                    }
                    sessionStorage.removeItem(paginationScrollKey);
                }

                const paginationLinks = document.querySelectorAll('a[data-preserve-scroll="1"]');
                paginationLinks.forEach(function (link) {
                    link.addEventListener('click', function () {
                        sessionStorage.setItem(paginationScrollKey, String(window.scrollY || window.pageYOffset || 0));
                    });
                });

                const postForms = document.querySelectorAll('form[method="post"], form[method="POST"]');
                postForms.forEach(function (form) {
                    let csrfInput = form.querySelector('input[name="_csrf"]');
                    if (!csrfInput) {
                        csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_csrf';
                        form.appendChild(csrfInput);
                    }
                    csrfInput.value = csrfToken;
                });

                const themeToggle = document.getElementById('themeToggle');
                if (themeToggle) {
                    themeToggle.checked = root.classList.contains('theme-dark');
                    themeToggle.addEventListener('change', function () {
                        root.classList.toggle('theme-dark', themeToggle.checked);
                        localStorage.setItem(key, themeToggle.checked ? 'dark' : 'light');
                    });
                }

                const navToggle = document.getElementById('navMenuToggle');
                const mobileNavMenu = document.getElementById('mobileNavMenu');
                if (navToggle && mobileNavMenu) {
                    const setMobileNavState = function (open) {
                        mobileNavMenu.classList.toggle('is-open', open);
                        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    };

                    navToggle.addEventListener('click', function () {
                        const isOpen = mobileNavMenu.classList.contains('is-open');
                        setMobileNavState(!isOpen);
                    });

                    const mobileMenuLinks = mobileNavMenu.querySelectorAll('a');
                    mobileMenuLinks.forEach(function (link) {
                        link.addEventListener('click', function () {
                            setMobileNavState(false);
                        });
                    });

                    window.addEventListener('resize', function () {
                        if (window.innerWidth >= 1024) {
                            setMobileNavState(false);
                        }
                    });
                }

                const updatesModal = document.getElementById('loginUpdatesModal');
                const closeUpdatesBtn = document.getElementById('closeLoginUpdatesModal');
                const closeUpdatesBtnFooter = document.getElementById('closeLoginUpdatesModalBtn');
                if (updatesModal) {
                    updatesModal.classList.remove('hidden');

                    const closeUpdates = function () {
                        updatesModal.classList.add('hidden');
                    };

                    if (closeUpdatesBtn) {
                        closeUpdatesBtn.addEventListener('click', closeUpdates);
                    }

                    if (closeUpdatesBtnFooter) {
                        closeUpdatesBtnFooter.addEventListener('click', closeUpdates);
                    }

                    updatesModal.addEventListener('click', function (event) {
                        if (event.target === updatesModal) {
                            closeUpdates();
                        }
                    });
                }

                document.addEventListener('click', function (event) {
                    const overlay = event.target instanceof Element ? event.target.closest('[data-modal-close]') : null;
                    if (!overlay || event.target !== overlay) {
                        return;
                    }

                    overlay.classList.add('hidden');
                    if (overlay.hasAttribute('aria-hidden')) {
                        overlay.setAttribute('aria-hidden', 'true');
                    }

                    const openModal = document.querySelector('[data-modal-close]:not(.hidden)');
                    if (!openModal) {
                        document.body.style.overflow = '';
                    }
                });

                if ('ontouchstart' in window) {
                    document.querySelectorAll('.modal-panel').forEach(function (panel) {
                        let startY = 0;
                        let lastY = 0;
                        let activeTouchId = null;
                        let isDragging = false;

                        const resetPanel = function (animated) {
                            panel.classList.remove('is-dragging');
                            panel.style.transition = animated ? 'transform 0.2s ease' : '';
                            panel.style.transform = animated ? 'translateY(0px)' : '';

                            if (animated) {
                                window.setTimeout(function () {
                                    if (!panel.classList.contains('is-dragging')) {
                                        panel.style.transition = '';
                                    }
                                }, 200);
                            }
                        };

                        const closePanel = function () {
                            const closeButton = panel.querySelector('[data-modal-close-button]');
                            if (closeButton && typeof closeButton.click === 'function') {
                                closeButton.click();
                            }
                        };

                        panel.addEventListener('touchstart', function (event) {
                            if (event.touches.length !== 1 || panel.scrollTop > 0) {
                                return;
                            }

                            const touch = event.touches[0];
                            const bounds = panel.getBoundingClientRect();
                            if ((touch.clientY - bounds.top) > 72) {
                                return;
                            }

                            activeTouchId = touch.identifier;
                            startY = touch.clientY;
                            lastY = touch.clientY;
                            isDragging = true;
                            panel.classList.add('is-dragging');
                            panel.style.transition = 'none';
                        }, { passive: true });

                        panel.addEventListener('touchmove', function (event) {
                            if (!isDragging) {
                                return;
                            }

                            const touch = Array.prototype.find.call(event.touches, function (item) {
                                return item.identifier === activeTouchId;
                            }) || event.touches[0];

                            if (!touch) {
                                return;
                            }

                            lastY = touch.clientY;
                            const deltaY = Math.max(0, lastY - startY);
                            panel.style.transform = 'translateY(' + deltaY + 'px)';

                            if (deltaY > 0) {
                                event.preventDefault();
                            }
                        }, { passive: false });

                        const finishDrag = function () {
                            if (!isDragging) {
                                return;
                            }

                            const deltaY = Math.max(0, lastY - startY);
                            isDragging = false;
                            activeTouchId = null;

                            if (deltaY > 80) {
                                closePanel();
                                panel.style.transform = '';
                                panel.style.transition = '';
                                panel.classList.remove('is-dragging');
                                return;
                            }

                            resetPanel(true);
                        };

                        panel.addEventListener('touchend', finishDrag);
                        panel.addEventListener('touchcancel', function () {
                            isDragging = false;
                            activeTouchId = null;
                            resetPanel(true);
                        });
                    });
                }

            })();

// ================================================
// 8. STUDENT ONBOARDING TOUR
// ================================================
(function () {
                    if (document.body.dataset.showOnboarding !== '1') { return; }
                    const userStorageSuffix = document.body.dataset.onboardingUserSuffix || 'guest';
                    const storageKey = 'websys_onboarding_done_' + userStorageSuffix;
                    const stepStorageKey = 'websys_onboarding_step_' + userStorageSuffix;
                    if (localStorage.getItem(storageKey) === '1') {
                        return;
                    }

                    const steps = [
                        {
                            target: '#dashboardWelcomeMessage',
                            title: 'Welcome',
                            body: 'This area gives you a quick read on your organizations, current activity, and budget status.'
                        },
                        {
                            target: '.nav-organizations-link',
                            title: 'Browse organizations',
                            body: 'Open the organizations directory to see groups you can join and their visibility rules.'
                        },
                        {
                            target: '[data-tour="join-button"]:not([disabled])',
                            title: 'Request to join',
                            body: 'Use a join button on the organizations page when you want to become a member of a group.'
                        },
                        {
                            target: '#dashboardAnnouncementsSection',
                            title: 'Announcements',
                            body: 'Important updates and recent announcements appear here so you can stay informed.'
                        },
                        {
                            target: '#dashboardBudgetTransparencySection',
                            title: 'Budget transparency',
                            body: 'Check the finance status section to review income, expenses, and balance at a glance.'
                        }
                    ];

                    const stepPages = ['dashboard', 'dashboard', 'organizations', 'dashboard', 'dashboard'];
                    const currentPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
                    const root = document.createElement('div');
                    root.className = 'onboarding-layer';
                    root.innerHTML = '<div class="onboarding-backdrop" aria-hidden="true"></div>';

                    const focus = document.createElement('div');
                    focus.className = 'onboarding-focus hidden';

                    const tooltip = document.createElement('div');
                    tooltip.className = 'onboarding-tooltip';
                    tooltip.setAttribute('role', 'dialog');
                    tooltip.setAttribute('aria-live', 'polite');

                    root.appendChild(focus);
                    root.appendChild(tooltip);
                    document.body.appendChild(root);

                    let stepIndex = Number.parseInt(sessionStorage.getItem(stepStorageKey) || '0', 10);
                    if (!Number.isFinite(stepIndex) || stepIndex < 0) {
                        stepIndex = 0;
                    }

                    const normalizeTarget = function (selector) {
                        if (selector === '.nav-organizations-link') {
                            if (window.matchMedia('(max-width: 1023px)').matches) {
                                return document.querySelector('#mobileNavMenu .nav-organizations-link') || document.querySelector(selector);
                            }

                            return document.querySelector('#appNav .nav-desktop .nav-organizations-link') || document.querySelector(selector);
                        }

                        if (selector === '[data-tour="join-button"]:not([disabled])') {
                            return document.querySelector(selector) || document.querySelector('[data-tour="join-button"]');
                        }

                        return document.querySelector(selector);
                    };

                    const getStepElement = function (index) {
                        const step = steps[index];
                        if (!step) {
                            return null;
                        }

                        return normalizeTarget(step.target);
                    };

                    const navigateToStepPage = function (index) {
                        const page = stepPages[index] || 'dashboard';
                        sessionStorage.setItem(stepStorageKey, String(index));
                        const targetUrl = '?page=' + encodeURIComponent(page);
                        if (currentPage !== page) {
                            window.location.href = targetUrl;
                            return true;
                        }

                        return false;
                    };

                    const completeOnboarding = function () {
                        localStorage.setItem(storageKey, '1');
                        sessionStorage.removeItem(stepStorageKey);

                        return fetch('?action=complete_onboarding', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                            },
                            body: 'action=complete_onboarding&_csrf=' + encodeURIComponent(document.body.dataset.csrfToken || ''),
                            credentials: 'same-origin'
                        }).then(function (response) {
                            if (!response.ok) {
                                throw new Error('Unable to complete onboarding');
                            }

                            return response.json();
                        }).catch(function () {
                            return null;
                        }).finally(function () {
                            root.remove();
                        });
                    };

                    const moveToNextAvailableStep = function (startIndex) {
                        for (let index = startIndex; index < steps.length; index += 1) {
                            const page = stepPages[index] || 'dashboard';
                            if (currentPage !== page) {
                                navigateToStepPage(index);
                                return true;
                            }

                            if (getStepElement(index)) {
                                stepIndex = index;
                                sessionStorage.setItem(stepStorageKey, String(index));
                                return false;
                            }
                        }

                        completeOnboarding();
                        return true;
                    };

                    const positionTooltip = function (element) {
                        const rect = element.getBoundingClientRect();
                        const spacing = 16;
                        const tooltipRect = tooltip.getBoundingClientRect();
                        const tooltipHeight = tooltipRect.height || 180;
                        const tooltipWidth = tooltipRect.width || 320;

                        focus.classList.remove('hidden');
                        focus.style.top = Math.max(8, rect.top - 6) + 'px';
                        focus.style.left = Math.max(8, rect.left - 6) + 'px';
                        focus.style.width = Math.min(window.innerWidth - 16, rect.width + 12) + 'px';
                        focus.style.height = Math.min(window.innerHeight - 16, rect.height + 12) + 'px';

                        const placeBelow = rect.bottom + spacing + tooltipHeight < window.innerHeight;
                        const top = placeBelow ? rect.bottom + spacing : Math.max(12, rect.top - spacing - tooltipHeight);
                        let left = rect.left;
                        if (left + tooltipWidth > window.innerWidth - 12) {
                            left = window.innerWidth - tooltipWidth - 12;
                        }
                        left = Math.max(12, left);

                        tooltip.dataset.placement = placeBelow ? 'bottom' : 'top';
                        tooltip.style.top = top + 'px';
                        tooltip.style.left = left + 'px';
                    };

                    const renderStep = function () {
                        const step = steps[stepIndex];
                        if (!step) {
                            sessionStorage.removeItem(stepStorageKey);
                            root.remove();
                            return;
                        }

                        if (stepIndex === 1 && window.matchMedia('(max-width: 1023px)').matches) {
                            const navToggle = document.getElementById('navMenuToggle');
                            const mobileNavMenu = document.getElementById('mobileNavMenu');
                            if (navToggle && mobileNavMenu) {
                                mobileNavMenu.classList.add('is-open');
                                navToggle.setAttribute('aria-expanded', 'true');
                            }
                        }

                        const page = stepPages[stepIndex] || 'dashboard';
                        if (currentPage !== page) {
                            navigateToStepPage(stepIndex);
                            return;
                        }

                        const element = getStepElement(stepIndex);
                        if (!element) {
                            if (moveToNextAvailableStep(stepIndex + 1)) {
                                return;
                            }

                            renderStep();
                            return;
                        }

                        const title = document.createElement('p');
                        title.className = 'onboarding-title';
                        title.textContent = step.title;

                        const body = document.createElement('p');
                        body.className = 'onboarding-body';
                        body.textContent = step.body;

                        const actions = document.createElement('div');
                        actions.className = 'onboarding-actions';

                        const nextButton = document.createElement('button');
                        nextButton.type = 'button';
                        nextButton.className = 'onboarding-button onboarding-button-next';

                        const isLastStep = stepIndex === steps.length - 1;
                        nextButton.textContent = isLastStep ? 'Done' : 'Next';

                        nextButton.addEventListener('click', function () {
                            if (isLastStep) {
                                completeOnboarding();
                                return;
                            }

                            const nextIndex = stepIndex + 1;
                            sessionStorage.setItem(stepStorageKey, String(nextIndex));
                            const nextPage = stepPages[nextIndex] || 'dashboard';
                            if (nextPage !== currentPage) {
                                window.location.href = '?page=' + encodeURIComponent(nextPage);
                                return;
                            }

                            stepIndex = nextIndex;
                            renderStep();
                        });

                        actions.appendChild(nextButton);

                        tooltip.innerHTML = '';
                        tooltip.appendChild(title);
                        tooltip.appendChild(body);
                        tooltip.appendChild(actions);

                        if (!isLastStep) {
                            const code = document.createElement('span');
                            code.className = 'onboarding-tooltip-code';
                            code.textContent = 'Step ' + (stepIndex + 1) + ' of ' + steps.length;
                            tooltip.appendChild(code);
                        }

                        element.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'nearest' });
                        window.setTimeout(function () {
                            positionTooltip(element);
                        }, 220);
                    };

                    const observer = typeof ResizeObserver !== 'undefined'
                        ? new ResizeObserver(function () {
                            const element = getStepElement(stepIndex);
                            if (element && !root.classList.contains('hidden')) {
                                positionTooltip(element);
                            }
                        })
                        : null;

                    window.addEventListener('scroll', function () {
                        const element = getStepElement(stepIndex);
                        if (element && !root.classList.contains('hidden')) {
                            positionTooltip(element);
                        }
                    }, { passive: true });

                    window.addEventListener('resize', function () {
                        const element = getStepElement(stepIndex);
                        if (element && !root.classList.contains('hidden')) {
                            positionTooltip(element);
                        }
                    }, { passive: true });

                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') {
                            localStorage.setItem(storageKey, '1');
                            sessionStorage.removeItem(stepStorageKey);
                            root.remove();
                        }
                    });

                    if (moveToNextAvailableStep(stepIndex)) {
                        return;
                    }

                    const initialElement = getStepElement(stepIndex);

                    if (initialElement && observer) {
                        observer.observe(initialElement);
                    }

                    renderStep();
                })();
