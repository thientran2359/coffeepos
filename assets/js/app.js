(function (window, document) {
    'use strict';

    function runBootstrap() {
        if (!window.CoffeePOS || !window.CoffeePOS.core || typeof window.CoffeePOS.core.bootstrap !== 'function') {
            return;
        }

        window.CoffeePOS.core.bootstrap();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runBootstrap);
        return;
    }

    runBootstrap();
}(window, document));
