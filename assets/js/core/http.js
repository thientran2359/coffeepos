(function (window) {
    'use strict';

    var CoffeePOS = window.CoffeePOS || {};
    var getConfig = (CoffeePOS.core && CoffeePOS.core.getConfig) || function () {
        return {
            restNonce: ''
        };
    };

    CoffeePOS.core = CoffeePOS.core || {};

    CoffeePOS.core.request = function (url, options) {
        var config = getConfig();
        var init = options || {};
        var headers = init.headers || {};

        if (config.restNonce) {
            headers['X-WP-Nonce'] = config.restNonce;
        }

        headers.Accept = 'application/json';
        init.headers = headers;

        return window.fetch(url, init).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (json) {
                if (response.ok && json && json.success !== false) {
                    return json;
                }

                var message = (json && json.message) || 'Request failed.';
                throw new Error(message);
            });
        });
    };

    window.CoffeePOS = CoffeePOS;
}(window));
