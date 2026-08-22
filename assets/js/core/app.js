(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};

    CoffeePOS.core = CoffeePOS.core || {};
    CoffeePOS.ui = CoffeePOS.ui || {};
    CoffeePOS.components = CoffeePOS.components || {};
    CoffeePOS.screens = CoffeePOS.screens || {};

    CoffeePOS.core.setState = function (element, state) {
        if (element) {
            element.setAttribute('data-state', state);
        }
    };

    CoffeePOS.core.closestAction = function (event, root) {
        const actionElement = event.target.closest('[data-action]');

        if (!actionElement || !root.contains(actionElement)) {
            return null;
        }

        return actionElement;
    };

    window.CoffeePOS = CoffeePOS;
}(window));
