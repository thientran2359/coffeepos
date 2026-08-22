(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};

    CoffeePOS.ui = CoffeePOS.ui || {};

    CoffeePOS.ui.createToastController = function (root, renderer) {
        const region = root.querySelector('[data-component="toast-region"]');
        const allowedTypes = ['success', 'info', 'warning', 'error'];
        let sequence = 0;

        function dismiss(toast) {
            if (toast && toast.parentNode === region) {
                region.removeChild(toast);
            }
        }

        if (region) {
            region.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-action="dismiss-toast"]');

                if (trigger) {
                    dismiss(trigger.closest('[data-component="toast"]'));
                }
            });
        }

        return {
            show: function (message, type) {
                if (!region || !message) {
                    return;
                }

                const normalizedType = allowedTypes.indexOf(type) === -1 ? 'info' : type;
                sequence += 1;
                const fragment = renderer.render('coffeepos-toast-template', {
                    id: 'toast-' + sequence,
                    message: message,
                    type: normalizedType
                });
                const toast = fragment.querySelector('[data-component="toast"]');

                if (toast && (normalizedType === 'warning' || normalizedType === 'error')) {
                    toast.setAttribute('role', 'alert');
                }

                region.appendChild(fragment);
                window.setTimeout(function () { dismiss(toast); }, 3200);
            }
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
