(function (window, document) {
    'use strict';

    function initializeStaffNavigation(root) {
        const navigation = root.querySelector('[data-component="staff-navigation"]');
        const toggle = navigation ? navigation.querySelector('[data-action="toggle-staff-navigation"]') : null;

        if (!navigation || !toggle) {
            return;
        }

        const icon = toggle.querySelector('[aria-hidden="true"]');
        const update = function (collapsed) {
            const label = collapsed ? toggle.dataset.labelExpand : toggle.dataset.labelCollapse;

            root.classList.toggle('is-staff-nav-collapsed', collapsed);
            navigation.classList.toggle('is-collapsed', collapsed);
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
            toggle.setAttribute('aria-label', label);
            toggle.setAttribute('title', label);

            if (icon) {
                icon.textContent = collapsed ? '›' : '‹';
            }

        };

        update(true);
        toggle.addEventListener('click', function () {
            update(!root.classList.contains('is-staff-nav-collapsed'));
        });
    }

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

        initializeStaffNavigation(root);

        if (screen === 'cashier' && window.CoffeePOS.screens.createCashierController) {
            screenController = window.CoffeePOS.screens.createCashierController(root);
            screenController.init();
        }

        if (screen === 'customer' && window.CoffeePOS.screens.createCustomerController) {
            screenController = window.CoffeePOS.screens.createCustomerController(root);
            screenController.init();
        }

        if (screen === 'kds' && window.CoffeePOS.screens.createKdsController) {
            screenController = window.CoffeePOS.screens.createKdsController(root);
            screenController.init();
        }

        if (screen === 'order-queue' && window.CoffeePOS.screens.createOrderQueueController) {
            screenController = window.CoffeePOS.screens.createOrderQueueController(root);
            screenController.init();
        }

        if (screen === 'shifts' && window.CoffeePOS.screens.createShiftsController) {
            screenController = window.CoffeePOS.screens.createShiftsController(root);
            screenController.init();
        }

        if (screen === 'order-history' && window.CoffeePOS.screens.createOrderHistoryController) {
            screenController = window.CoffeePOS.screens.createOrderHistoryController(root);
            screenController.init();
        }

        if (screen === 'reports' && window.CoffeePOS.screens.createReportsController) {
            screenController = window.CoffeePOS.screens.createReportsController(root);
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
