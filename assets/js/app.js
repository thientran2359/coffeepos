(function (window, document) {
    'use strict';

    function bootstrap() {
        const root = document.getElementById('coffeepos-app');

        if (!root) {
            return;
        }

        const config = typeof window.CoffeePOSConfig === 'object' && window.CoffeePOSConfig !== null
            ? window.CoffeePOSConfig
            : {};
        const screen = root.getAttribute('data-screen') || config.screen || '';
        let screenController = null;

        if (screen === 'cashier' && window.CoffeePOS.screens.createCashierController) {
            screenController = window.CoffeePOS.screens.createCashierController(root);
            screenController.init();
        }

        if (screen === 'customer' && window.CoffeePOS.screens.createCustomerController) {
            screenController = window.CoffeePOS.screens.createCustomerController(root);
            screenController.init();
        }

        window.CoffeePOS.activeScreen = screenController;

        window.dispatchEvent(new CustomEvent('coffeepos:ready', {
            detail: {
                screen: screen,
                uiState: screenController ? screenController.getState() : null
            }
        }));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
        return;
    }

    bootstrap();
}(window, document));
