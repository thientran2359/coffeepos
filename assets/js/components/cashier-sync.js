(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createCashierSyncBridge = function (root, toast) {
        const protocol = CoffeePOS.sync.protocol;
        const displayButton = root.querySelector('[data-action="open-customer-display"]');
        const displayStatus = root.querySelector('[data-component="customer-display-status"]');
        const displayStatusLabel = displayStatus ? displayStatus.querySelector('[data-field="customer-display-status-label"]') : null;
        const config = window.CoffeePOSConfig || {};
        let cart = null;
        let payment = null;
        let order = null;
        let screenState = 'idle';
        let workflowSequence = 0;
        let syncAvailable = true;
        let pairing = null;
        let displayHeartbeatTimer = 0;

        function setDisplayConnection(state) {
            window.clearTimeout(displayHeartbeatTimer);
            if (displayStatus) { displayStatus.setAttribute('data-state', state); }
            if (displayStatusLabel) {
                displayStatusLabel.textContent = state === 'connected'
                    ? __('Customer display: Connected', 'coffeepos')
                    : (state === 'connecting' ? __('Customer display: Connecting…', 'coffeepos') : __('Customer display: Not connected', 'coffeepos'));
            }
            if (state === 'connected') {
                displayHeartbeatTimer = window.setTimeout(function () { setDisplayConnection('disconnected'); }, 12000);
            }
        }

        function pairDisplay(targetInstanceId) {
            if (!pairing || !cart || !protocol.validSessionId(cart.pos_session_id)) { return false; }
            return pairing.post('display.control.pair', { pos_session_id: cart.pos_session_id }, 'customer', targetInstanceId || '');
        }

        try {
            pairing = CoffeePOS.sync.createDisplayPairing({
                scope: String(config.displayPairingScope || ''),
                source: 'cashier',
                onMessage: function (message) {
                    if (message.type === 'display.control.ready') {
                        setDisplayConnection('connecting');
                        pairDisplay(message.source_instance_id);
                    }
                },
                onError: function () {
                    setDisplayConnection('disconnected');
                    if (toast) { toast.show(__('Customer Display pairing is unavailable.', 'coffeepos'), 'warning'); }
                }
            });
        } catch (error) {
            if (toast) { toast.show(__('Customer Display pairing is unavailable.', 'coffeepos'), 'warning'); }
        }

        const transport = CoffeePOS.sync.createChannel({
            source: 'cashier', target: 'customer',
            onMessage: function (message) {
                if (message.source !== 'customer' || ['display.ready', 'state.requested'].indexOf(message.type) === -1) { return; }
                setDisplayConnection('connected');
                snapshot();
            },
            onError: function () {
                setDisplayConnection('disconnected');
                if (toast) { toast.show(__('Customer Display synchronization is unavailable.', 'coffeepos'), 'warning'); }
            }
        });

        function sequenceKey(sessionId) { return 'coffeepos:workflow-sequence:' + sessionId; }
        function loadSequence(sessionId) {
            try { return Math.max(0, Number(window.sessionStorage.getItem(sequenceKey(sessionId))) || 0); }
            catch (error) { return 0; }
        }
        function saveSequence() {
            if (!cart) { return; }
            try { window.sessionStorage.setItem(sequenceKey(cart.pos_session_id), String(workflowSequence)); } catch (error) {}
        }
        function safeCart(value) {
            return value && value.customer_display
                ? protocol.safeCustomerCart(value.customer_display)
                : null;
        }
        function snapshotPayload() {
            const customerCart = safeCart(cart);
            return {
                screen_state: screenState,
                workflow_sequence: workflowSequence,
                cart: customerCart,
                customer: customerCart ? customerCart.customer : null,
                payment: payment,
                order: order
            };
        }
        function snapshot() {
            if (!cart) { return false; }
            return transport.post('state.snapshot', snapshotPayload(), Number(cart.revision), workflowSequence);
        }
        function bind(nextCart) {
            if (!nextCart || !protocol.validSessionId(nextCart.pos_session_id)) { return; }
            const changed = transport.getSessionId() !== nextCart.pos_session_id;
            cart = nextCart;
            if (changed) {
                setDisplayConnection('disconnected');
                try {
                    transport.bind(cart.pos_session_id);
                    syncAvailable = true;
                } catch (error) {
                    syncAvailable = false;
                    if (toast) { toast.show(__('Customer Display synchronization is unavailable.', 'coffeepos'), 'warning'); }
                }
                workflowSequence = loadSequence(cart.pos_session_id);
                payment = null; order = null;
                screenState = Array.isArray(cart.items) && cart.items.length ? 'cart' : 'idle';
            }
            if (displayButton) { displayButton.disabled = false; }
        }
        function publishCart(nextCart) {
            bind(nextCart);
            const customerCart = safeCart(cart);
            if (!customerCart || !syncAvailable) { return; }
            if (screenState === 'idle' || screenState === 'cart') {
                screenState = customerCart.items.length ? 'cart' : 'idle';
            }
            transport.post('cart.updated', { cart: customerCart }, Number(cart.revision), workflowSequence);
        }
        function publishWorkflow(type, payload) {
            if (!cart) { return; }
            if (type === 'state.snapshot') { snapshot(); return; }
            const data = payload || {};
            workflowSequence++; saveSequence();
            if (type === 'checkout.started') { screenState = 'checkout'; payment = data.payment || null; order = null; }
            if (type === 'payment.started') { screenState = 'payment_pending'; payment = data.payment || null; order = data.order || order; }
            if (type === 'payment.updated') {
                payment = data.payment || payment; order = data.order || order;
                screenState = payment && payment.state === 'paid' ? 'payment_success' : (payment && payment.state === 'failed' ? 'checkout' : 'payment_pending');
            }
            if (type === 'sale.completed') { screenState = 'payment_success'; payment = data.payment || payment; order = data.order || order; }
            transport.post(type, data, Number(cart.revision), workflowSequence);
            snapshot();
        }
        function returnToCart() {
            if (!cart) { return; }
            screenState = safeCart(cart) && safeCart(cart).items.length ? 'cart' : 'idle';
            payment = null; order = null;
            snapshot();
        }
        function openDisplay() {
            if (!cart) { return; }
            const base = String(config.customerDisplayUrl || '');
            if (!base) { return; }
            const url = new window.URL(base, window.location.origin);
            const displayWindow = window.open(url.href, 'coffeepos-customer-display');
            if (!displayWindow && toast) {
                toast.show(__('Customer Display was blocked by the browser. Allow pop-ups and try again.', 'coffeepos'), 'warning');
            }
        }
        if (displayButton) { displayButton.addEventListener('click', openDisplay); }
        return {
            publishCart: publishCart,
            publishWorkflow: publishWorkflow,
            returnToCart: returnToCart,
            snapshot: snapshot,
            destroy: function () {
                window.clearTimeout(displayHeartbeatTimer);
                transport.close();
                if (pairing) { pairing.close(); }
            }
        };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
