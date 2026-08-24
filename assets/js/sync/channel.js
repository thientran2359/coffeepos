(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;

    CoffeePOS.sync.createChannel = function (options) {
        const protocol = CoffeePOS.sync.protocol;
        const settings = options || {};
        const source = String(settings.source || '');
        const target = String(settings.target || '');
        const sourceInstanceId = protocol.instanceId(source);
        let sessionId = '';
        let channel = null;
        let generation = 0;
        let seen = new Set();
        let seenOrder = [];

        function remember(messageId) {
            if (seen.has(messageId)) { return false; }
            seen.add(messageId); seenOrder.push(messageId);
            while (seenOrder.length > 200) { seen.delete(seenOrder.shift()); }
            return true;
        }
        function close() {
            generation++;
            if (channel) { channel.onmessage = null; channel.close(); channel = null; }
        }
        function bind(nextSessionId) {
            close();
            if (!protocol.validSessionId(nextSessionId)) { throw new Error(__('Invalid POS session id.', 'coffeepos')); }
            if (typeof window.BroadcastChannel !== 'function') { throw new Error(__('BroadcastChannel is unavailable.', 'coffeepos')); }
            sessionId = String(nextSessionId); seen = new Set(); seenOrder = [];
            const currentGeneration = generation;
            channel = new window.BroadcastChannel(protocol.channelName(sessionId));
            channel.onmessage = function (event) {
                if (currentGeneration !== generation || !protocol.validateEnvelope(event.data, sessionId)) { return; }
                const message = event.data;
                if (message.source_instance_id === sourceInstanceId || (message.target !== source && message.target !== 'all')) { return; }
                if (!remember(message.message_id)) { return; }
                if (typeof settings.onMessage === 'function') { settings.onMessage(message); }
            };
            channel.onmessageerror = function () { if (typeof settings.onError === 'function') { settings.onError(); } };
            return sessionId;
        }
        function post(type, payload, revision, workflowSequence, overrideTarget) {
            if (!channel) { return false; }
            try {
                channel.postMessage(protocol.createEnvelope(type, {
                    posSessionId: sessionId, revision: revision, workflowSequence: workflowSequence,
                    sourceInstanceId: sourceInstanceId, source: source,
                    target: overrideTarget || target, payload: payload || {}
                }));
                return true;
            } catch (error) {
                if (typeof settings.onError === 'function') { settings.onError(error); }
                return false;
            }
        }
        return { bind: bind, post: post, close: close, getSessionId: function () { return sessionId; }, getSourceInstanceId: function () { return sourceInstanceId; } };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
