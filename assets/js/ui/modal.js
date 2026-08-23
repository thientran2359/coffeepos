(function (window, document) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const setState = CoffeePOS.core.setState;

    function focusableElements(dialog) {
        return Array.prototype.filter.call(
            dialog.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [href], [tabindex]:not([tabindex="-1"])'),
            function (element) { return !element.hidden; }
        );
    }

    function createDialogLifecycle(element, onConfirm, onClose, canClose) {
        const dialog = element ? element.querySelector('[role="dialog"], [role="alertdialog"]') : null;
        let previousFocus = null;
        let pendingAction = '';

        function onKeydown(event) {
            if (element.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                event.preventDefault();
                close();
                return;
            }

            if (event.key !== 'Tab' || !dialog) {
                return;
            }

            const focusable = focusableElements(dialog);

            if (focusable.length === 0) {
                event.preventDefault();
                dialog.focus();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        function open(action) {
            if (!element || !dialog) {
                return;
            }

            previousFocus = document.activeElement;
            pendingAction = action || '';
            element.hidden = false;
            setState(element, 'open');
            document.body.classList.add('coffeepos-overlay-open');
            document.addEventListener('keydown', onKeydown);

            const focusable = focusableElements(dialog);
            (focusable[0] || dialog).focus();
        }

        function close() {
            if (!element || element.hidden) {
                return;
            }

            if (typeof canClose === 'function' && !canClose()) {
                return;
            }

            element.hidden = true;
            setState(element, 'closed');
            document.removeEventListener('keydown', onKeydown);
            document.body.classList.remove('coffeepos-overlay-open');
            pendingAction = '';

            if (previousFocus && typeof previousFocus.focus === 'function') {
                previousFocus.focus();
            }

            if (typeof onClose === 'function') {
                onClose();
            }
        }

        function confirm() {
            const action = pendingAction;

            if (typeof onConfirm === 'function') {
                onConfirm(action);
            }

            close();
        }

        return {
            open: open,
            close: close,
            confirm: confirm
        };
    }

    CoffeePOS.ui = CoffeePOS.ui || {};
    CoffeePOS.ui.createDialogLifecycle = createDialogLifecycle;

    CoffeePOS.ui.createModalController = function (root) {
        const element = root.querySelector('[data-component="modal"]');
        const title = element && element.querySelector('[data-component="modal-title"]');
        const message = element && element.querySelector('[data-component="modal-message"]');
        const loading = element && element.querySelector('[data-component="modal-loading"]');
        const error = element && element.querySelector('[data-component="modal-error"]');
        const lifecycle = createDialogLifecycle(
            element,
            function (action) {
                root.dispatchEvent(new window.CustomEvent('coffeepos:modal-confirm', {
                    bubbles: true,
                    detail: { action: action }
                }));
            },
            function () {
                root.dispatchEvent(new window.CustomEvent('coffeepos:modal-close', { bubbles: true }));
            }
        );

        if (element) {
            element.addEventListener('click', function (event) {
                const actionElement = event.target.closest('[data-action]');
                const action = actionElement ? actionElement.getAttribute('data-action') : '';

                if (action === 'close-modal' || action === 'cancel-modal') {
                    lifecycle.close();
                } else if (action === 'confirm-modal') {
                    lifecycle.confirm();
                }
            });
        }

        return {
            open: function (options) {
                const settings = options || {};

                if (title) {
                    title.textContent = settings.title || '';
                }

                if (message) {
                    message.textContent = settings.message || '';
                }

                if (loading) {
                    loading.hidden = settings.state !== 'loading';
                }

                if (error) {
                    error.hidden = settings.state !== 'error';
                }

                lifecycle.open(settings.action || '');
            },
            close: lifecycle.close
        };
    };

    CoffeePOS.ui.createConfirmDialogController = function (root) {
        const element = root.querySelector('[data-component="confirm-dialog"]');
        const title = element && element.querySelector('[data-component="confirm-title"]');
        const message = element && element.querySelector('[data-component="confirm-message"]');
        const lifecycle = createDialogLifecycle(element, function (action) {
            root.dispatchEvent(new window.CustomEvent('coffeepos:confirm', {
                bubbles: true,
                detail: { action: action }
            }));
        });

        if (element) {
            element.addEventListener('click', function (event) {
                const actionElement = event.target.closest('[data-action]');
                const action = actionElement ? actionElement.getAttribute('data-action') : '';

                if (action === 'cancel-confirm') {
                    lifecycle.close();
                } else if (action === 'confirm-action') {
                    lifecycle.confirm();
                }
            });
        }

        return {
            open: function (options) {
                const settings = options || {};

                if (title) {
                    title.textContent = settings.title || '';
                }

                if (message) {
                    message.textContent = settings.message || '';
                }

                lifecycle.open(settings.action || '');
            },
            close: lifecycle.close
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window, document));
