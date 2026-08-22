(function (window) {
    'use strict';

    var CoffeePOS = window.CoffeePOS || {};
    var getConfig = (CoffeePOS.core && CoffeePOS.core.getConfig) || function () {
        return {
            screen: '',
            restBase: '',
            restNonce: ''
        };
    };

    CoffeePOS.core = CoffeePOS.core || {};

    CoffeePOS.core.bootstrap = function () {
        var root = document.getElementById('coffeepos-app');

        if (!root) {
            return;
        }

        var config = getConfig();

        if (config.screen) {
            root.setAttribute('data-coffeepos-screen', config.screen);
        }

        if (!root.getAttribute('data-screen') && config.screen) {
            root.setAttribute('data-screen', config.screen);
        }

        if (config.restBase) {
            root.setAttribute('data-coffeepos-rest-base', config.restBase);
        }

        var screen = root.getAttribute('data-screen') || root.getAttribute('data-coffeepos-screen') || config.screen || '';
        var screenController = null;

        if (screen === 'cashier' && CoffeePOS.screens && typeof CoffeePOS.screens.createCashierController === 'function') {
            screenController = CoffeePOS.screens.createCashierController(root);
            screenController.init();
        }

        window.dispatchEvent(new CustomEvent('coffeepos:ready', {
            detail: {
                screen: screen,
                uiState: screenController ? screenController.getState() : null
            }
        }));
    };

    window.CoffeePOS = CoffeePOS;
}(window));
