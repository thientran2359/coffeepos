(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};

    CoffeePOS.sync = CoffeePOS.sync || {};
    CoffeePOS.sync.createDisplayPairing = function (options) {
        const protocol = CoffeePOS.sync.protocol;
        const settings = options || {};
        const scope = String(settings.scope || '');
        const source = String(settings.source || '');
        const sourceInstanceId = protocol.instanceId(source + '-display-control');
        const allowedTypes = ['display.control.ready', 'display.control.pair'];
        let channel = null;

        function validMessage(message) {
            if (!protocol.plainObject(message) || message.version !== 1 || allowedTypes.indexOf(message.type) === -1) { return false; }
            if (['cashier', 'customer'].indexOf(message.source) === -1 || ['cashier', 'customer'].indexOf(message.target) === -1) { return false; }
            if (!/^[A-Za-z0-9._:-]{8,160}$/.test(String(message.message_id || ''))) { return false; }
            if (!/^[A-Za-z0-9._:-]{8,160}$/.test(String(message.source_instance_id || ''))) { return false; }
            if (message.target_instance_id && !/^[A-Za-z0-9._:-]{8,160}$/.test(String(message.target_instance_id))) { return false; }
            return protocol.plainObject(message.payload);
        }

        function post(type, payload, target, targetInstanceId) {
            if (!channel || allowedTypes.indexOf(type) === -1) { return false; }
            channel.postMessage({
                version: 1,
                type: type,
                message_id: protocol.id('display-control'),
                source_instance_id: sourceInstanceId,
                source: source,
                target: String(target || ''),
                target_instance_id: String(targetInstanceId || ''),
                payload: payload || {}
            });
            return true;
        }

        function close() {
            if (channel) { channel.onmessage = null; channel.close(); channel = null; }
        }

        if (!/^[a-f0-9]{32}$/.test(scope) || ['cashier', 'customer'].indexOf(source) === -1 || typeof window.BroadcastChannel !== 'function') {
            throw new Error('Customer Display pairing is unavailable.');
        }

        channel = new window.BroadcastChannel('coffeepos:display-control:' + scope);
        channel.onmessage = function (event) {
            const message = event.data;
            if (!validMessage(message) || message.source === source || message.target !== source) { return; }
            if (message.target_instance_id && message.target_instance_id !== sourceInstanceId) { return; }
            if (typeof settings.onMessage === 'function') { settings.onMessage(message); }
        };
        channel.onmessageerror = function () {
            if (typeof settings.onError === 'function') { settings.onError(); }
        };

        return {
            post: post,
            close: close,
            getSourceInstanceId: function () { return sourceInstanceId; }
        };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
