(function (window) {
    'use strict';

    var CoffeePOS = window.CoffeePOS || {};

    CoffeePOS.core = CoffeePOS.core || {};

    CoffeePOS.core.getConfig = function () {
        if (typeof window.CoffeePOSConfig !== 'object' || window.CoffeePOSConfig === null) {
            return {
                screen: '',
                restBase: '',
                restNonce: ''
            };
        }

        return window.CoffeePOSConfig;
    };

    window.CoffeePOS = CoffeePOS;
}(window));
