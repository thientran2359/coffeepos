(function (window, document) {
    'use strict';

    var CoffeePOS = window.CoffeePOS || {};

    CoffeePOS.ui = CoffeePOS.ui || {};

    CoffeePOS.ui.createToastController = function (root) {
        var region = root.querySelector('[data-component="toast-region"]');

        function show(message, type) {
            if (!region || !message) {
                return;
            }

            var toast = document.createElement('div');
            toast.className = 'coffeepos-toast is-' + (type || 'info');
            toast.textContent = message;
            region.appendChild(toast);

            window.setTimeout(function () {
                if (toast.parentNode === region) {
                    region.removeChild(toast);
                }
            }, 2600);
        }

        return {
            show: show
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window, document));
