(function (window) {
    'use strict';

    var CoffeePOS = window.CoffeePOS || {};

    CoffeePOS.core = CoffeePOS.core || {};

    CoffeePOS.core.setState = function (element, state) {
        if (!element) {
            return;
        }

        element.setAttribute('data-state', state);
    };

    window.CoffeePOS = CoffeePOS;
}(window));
