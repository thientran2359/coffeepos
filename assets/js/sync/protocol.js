(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    const TYPES = [
        'display.ready', 'state.requested', 'state.snapshot', 'cart.updated',
        'customer.updated', 'checkout.started', 'payment.started',
        'payment.updated', 'sale.completed', 'display.reset',
        'catalog.invalidated'
    ];

    function plainObject(value) {
        return value !== null && typeof value === 'object' && !Array.isArray(value);
    }
    function validSessionId(value) {
        return /^[a-zA-Z0-9-]{16,64}$/.test(String(value || ''));
    }
    function id(prefix) {
        if (window.crypto && window.crypto.randomUUID) { return prefix + '-' + window.crypto.randomUUID(); }
        return prefix + '-' + Date.now() + '-' + Math.random().toString(16).slice(2);
    }
    function instanceId(source) {
        const key = 'coffeepos:' + source + ':source-instance';
        try {
            let value = window.sessionStorage.getItem(key);
            if (!value) { value = id(source); window.sessionStorage.setItem(key, value); }
            return value;
        } catch (error) { return id(source); }
    }
    function channelName(sessionId) {
        if (!validSessionId(sessionId)) { throw new Error(__('Invalid POS session id.', 'coffeepos')); }
        return 'coffeepos:' + sessionId;
    }
    function createEnvelope(type, options) {
        const settings = options || {};
        if (TYPES.indexOf(type) === -1 || !validSessionId(settings.posSessionId) || !plainObject(settings.payload || {})) {
            throw new Error(__('Invalid synchronization envelope input.', 'coffeepos'));
        }
        return {
            version: 1,
            type: type,
            message_id: id('message'),
            timestamp: new Date().toISOString(),
            pos_session_id: String(settings.posSessionId),
            revision: Math.max(0, Number(settings.revision) || 0),
            workflow_sequence: Math.max(0, Number(settings.workflowSequence) || 0),
            source_instance_id: String(settings.sourceInstanceId || ''),
            source: String(settings.source || ''),
            target: String(settings.target || ''),
            payload: settings.payload || {}
        };
    }
    function validateEnvelope(message, expectedSessionId) {
        if (!plainObject(message) || message.version !== 1 || TYPES.indexOf(message.type) === -1) { return false; }
        if (!validSessionId(message.pos_session_id) || message.pos_session_id !== expectedSessionId) { return false; }
        if (!/^[A-Za-z0-9._:-]{8,160}$/.test(String(message.message_id || ''))) { return false; }
        if (!/^[A-Za-z0-9._:-]{8,160}$/.test(String(message.source_instance_id || ''))) { return false; }
        if (['cashier', 'customer'].indexOf(message.source) === -1 || ['cashier', 'customer', 'all'].indexOf(message.target) === -1) { return false; }
        if (!Number.isInteger(message.revision) || message.revision < 0 || !Number.isInteger(message.workflow_sequence) || message.workflow_sequence < 0) { return false; }
        return plainObject(message.payload);
    }
    function safeQrUrl(value) {
        try {
            const parsed = new window.URL(String(value || ''));
            return parsed.protocol === 'https:' && parsed.hostname === 'vietqr.app' && parsed.pathname === '/img' && !parsed.username && !parsed.password
                ? parsed.href : '';
        } catch (error) { return ''; }
    }
    function safeCustomer(value) {
        const customer = plainObject(value) ? value : {};
        const isGuest = customer.mode === 'guest' || customer.is_guest === true;
        const membership = plainObject(customer.membership) ? customer.membership : null;
        const safeMembership = membership ? {
            status_label: String(membership.status_label || ''),
            tier_code: String(membership.tier_code || ''),
            tier_label: String(membership.tier_label || ''),
            points_display: String(membership.points_display || ''),
            balance_display: String(membership.balance_display || '')
        } : null;
        return {
            is_guest: isGuest,
            mode: isGuest ? 'guest' : 'member',
            display_name: isGuest ? 'Guest' : String(customer.display_name || ''),
            phone_masked: isGuest ? '' : String(customer.phone_masked || ''),
            membership: safeMembership
        };
    }
    function safeCustomerCart(value) {
        if (!plainObject(value)) { return null; }
        const cart = Object.assign({}, value);
        cart.customer = safeCustomer(value.customer);
        return cart;
    }

    CoffeePOS.sync = CoffeePOS.sync || {};
    CoffeePOS.sync.protocol = {
        types: TYPES.slice(), plainObject: plainObject, validSessionId: validSessionId,
        channelName: channelName, id: id, instanceId: instanceId, createEnvelope: createEnvelope,
        validateEnvelope: validateEnvelope, safeQrUrl: safeQrUrl,
        safeCustomer: safeCustomer, safeCustomerCart: safeCustomerCart
    };
    window.CoffeePOS = CoffeePOS;
}(window));
