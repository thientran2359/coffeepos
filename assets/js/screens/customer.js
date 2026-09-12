(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    CoffeePOS.screens = CoffeePOS.screens || {};

    CoffeePOS.screens.createCustomerController = function (root) {
        const config = window.CoffeePOSConfig || {};
        const protocol = CoffeePOS.sync.protocol;
        const renderer = new CoffeePOS.ui.TemplateRenderer();
        renderer.registerUrlValidator('src', function (value) { return protocol.safeQrUrl(value) !== ''; });
        const api = CoffeePOS.api.createPosApi(CoffeePOS.api.createClient());
        const store = CoffeePOS.state.createCustomerDisplayStore();
        const catalog = CoffeePOS.components.createCustomerCatalog(root, renderer);
        const cartView = CoffeePOS.components.createCustomerCart(root, renderer);
        const paymentView = CoffeePOS.components.createCustomerPayment(root, renderer);
        const connection = root.querySelector('[data-component="customer-connection"]');
        const syncState = root.querySelector('[data-component="customer-sync-state"]');
        const syncMessage = root.querySelector('[data-field="customer-sync-message"]');
        const memberPinDialog = root.querySelector('[data-component="customer-member-pin-dialog"]');
        const memberPinChannel = window.BroadcastChannel && config.displayPairingScope
            ? new window.BroadcastChannel('coffeepos:member-pin:' + String(config.displayPairingScope))
            : null;
        let transport = null;
        let pairing = null;
        let recoveryPending = false;
        let rolloverPending = false;
        let rolloverTimer = 0;
        let handshakeTimer = 0;
        let disconnectedReloadTimer = 0;
        let memberPinTimer = 0;

        function onMemberPinMessage(event) {
            const message = event.data && typeof event.data === 'object' ? event.data : {};
            if (!memberPinDialog || message.type !== 'coffeepos:show-member-pin'
                || !/^[0-9]{6}$/.test(String(message.pin || ''))) {
                return;
            }
            const output = memberPinDialog.querySelector('[data-field="customer-member-pin"]');
            output.textContent = String(message.pin);
            memberPinDialog.showModal();
            window.clearTimeout(memberPinTimer);
            memberPinTimer = window.setTimeout(closeMemberPin, 12000);
            memberPinChannel.postMessage({type: 'coffeepos:member-pin-shown', request_id: String(message.request_id || '')});
        }

        function closeMemberPin() {
            if (!memberPinDialog) { return; }
            window.clearTimeout(memberPinTimer);
            memberPinTimer = 0;
            const output = memberPinDialog.querySelector('[data-field="customer-member-pin"]');
            output.textContent = '';
            if (memberPinDialog.open) { memberPinDialog.close(); }
        }

        function updateDisconnectedReload(state) {
            if (state === 'connected' || state === 'unsupported') {
                window.clearTimeout(disconnectedReloadTimer);
                disconnectedReloadTimer = 0;
                return;
            }
            if (disconnectedReloadTimer) { return; }
            disconnectedReloadTimer = window.setTimeout(function () {
                if (store.getState().connection !== 'connected') { window.location.reload(); }
            }, 30000);
        }

        function setConnection(state, message, actionable) {
            store.setConnection(state); connection.setAttribute('data-state', state); connection.textContent = message;
            syncState.hidden = !actionable; syncMessage.textContent = actionable ? message : '';
            updateDisconnectedReload(state);
        }
        function render() {
            const state = store.getState();
            root.querySelector('[data-component="customer-display"]').setAttribute('data-state', state.screenState);
            cartView.render(state.cart, state.screenState);
            paymentView.render(state.screenState, state.payment, state.order, state.cart);
        }
        function requestSnapshot() {
            if (!transport) { return; }
            recoveryPending = true; setConnection('reconnecting', __('Recovering display state…', 'coffeepos'), false);
            const state = store.getState();
            transport.post('state.requested', { last_known_revision: Math.max(0, state.revision) }, Math.max(0, state.revision), Math.max(0, state.workflowSequence), 'cashier');
            scheduleHandshake(2000);
        }
        function scheduleHandshake(delay) {
            window.clearTimeout(handshakeTimer);
            handshakeTimer = window.setTimeout(function () {
                if (!transport || rolloverPending) { return; }
                const state = store.getState();
                transport.post('display.ready', { last_known_revision: Math.max(0, state.revision) }, Math.max(0, state.revision), Math.max(0, state.workflowSequence), 'cashier');
                scheduleHandshake(state.connection === 'connected' ? 8000 : 2000);
            }, delay);
        }
        function validCart(cart) {
            return protocol.plainObject(cart) && cart.pos_session_id === store.getState().posSessionId && Number.isInteger(cart.revision) && Array.isArray(cart.items);
        }
        function hydrate(message) {
            const snapshot = message.payload;
            if (!protocol.plainObject(snapshot) || !validCart(snapshot.cart) || !Number.isInteger(snapshot.workflow_sequence)) { return; }
            store.hydrate(snapshot, message.revision, snapshot.workflow_sequence);
            recoveryPending = false; setConnection('connected', __('Display connected', 'coffeepos'), false); render(); scheduleHandshake(8000);
        }
        function acceptCart(message) {
            const state = store.getState();
            if (!validCart(message.payload.cart) || recoveryPending || state.revision < 0) { requestSnapshot(); return; }
            if (message.revision <= state.revision) { return; }
            if (message.revision !== state.revision + 1) { requestSnapshot(); return; }
            store.acceptCart(message.payload.cart, message.revision); render();
        }
        function workflowState(type, payment) {
            if (type === 'checkout.started') { return 'checkout'; }
            if (type === 'payment.started') { return 'payment_pending'; }
            if (type === 'payment.updated') { return payment && payment.state === 'paid' ? 'payment_success' : (payment && payment.state === 'failed' ? 'checkout' : 'payment_pending'); }
            if (type === 'sale.completed') { return 'payment_success'; }
            return store.getState().screenState;
        }
        function acceptWorkflow(message) {
            const state = store.getState();
            if (recoveryPending || state.workflowSequence < 0) { requestSnapshot(); return; }
            if (message.workflow_sequence <= state.workflowSequence) { return; }
            if (message.workflow_sequence !== state.workflowSequence + 1) { requestSnapshot(); return; }
            const payment = message.payload.payment;
            const order = message.payload.order;
            if (state.screenState === 'payment_success' && payment && payment.state !== 'paid') { return; }
            if (message.type === 'sale.completed' && (!payment || payment.state !== 'paid' || !order)) { return; }
            store.acceptWorkflow(workflowState(message.type, payment), payment, order, message.workflow_sequence); render();
        }
        function rollover(message) {
            const state = store.getState();
            if (message.workflow_sequence !== state.workflowSequence + 1 || !protocol.validSessionId(message.payload.next_pos_session_id)) { requestSnapshot(); return; }
            store.acceptWorkflow(state.screenState, state.payment, state.order, message.workflow_sequence);
            rolloverPending = true;
            window.clearTimeout(rolloverTimer);
            rolloverTimer = window.setTimeout(function () {
                const nextId = message.payload.next_pos_session_id;
                try {
                    transport.bind(nextId); store.pair(nextId); rolloverPending = false;
                    setConnection('hydrating', __('Connecting next order…', 'coffeepos'), false);
                    transport.post('display.ready', { last_known_revision: 0 }, 0, 0, 'cashier');
                    recoverCart(nextId);
                } catch (error) { setConnection('reconnecting', __('Could not pair the next order. Reconnect this display.', 'coffeepos'), true); }
            }, 0);
        }
        function onMessage(message) {
            if (rolloverPending && message.type !== 'display.reset') { return; }
            if (message.source !== 'cashier') { return; }
            if (message.type === 'state.snapshot') { hydrate(message); return; }
            if (message.type === 'cart.updated') { acceptCart(message); return; }
            if (message.type === 'catalog.invalidated') { loadCatalog(); return; }
            if (message.type === 'display.reset') { rollover(message); return; }
            if (['checkout.started', 'payment.started', 'payment.updated', 'sale.completed'].indexOf(message.type) !== -1) { acceptWorkflow(message); }
        }
        async function recoverCart(sessionId) {
            try {
                const data = await api.getCustomerCart(sessionId);
                if (sessionId !== store.getState().posSessionId || !validCart(data.cart)) { return; }
                store.acceptCart(data.cart, data.cart.revision); render();
                setConnection('reconnecting', __('Cart recovered; waiting for Cashier state…', 'coffeepos'), false); requestSnapshot();
            } catch (error) {
                const state = error && error.code === 'cart_session_not_found' ? 'expired' : 'reconnecting';
                setConnection(state, state === 'expired' ? __('This paired cart has expired. Open the display again from Cashier.', 'coffeepos') : __('Display sync is unavailable. Reconnect to try again.', 'coffeepos'), true);
            }
        }
        function connect(sessionId) {
            if (!protocol.validSessionId(sessionId)) { return; }
            if (transport) { transport.close(); transport = null; }
            window.clearTimeout(rolloverTimer);
            window.clearTimeout(handshakeTimer);
            recoveryPending = false;
            rolloverPending = false;
            store.pair(sessionId);
            try {
                transport = CoffeePOS.sync.createChannel({ source: 'customer', target: 'cashier', onMessage: onMessage, onError: function () { setConnection('reconnecting', __('Display connection was interrupted.', 'coffeepos'), true); } });
                transport.bind(sessionId);
                setConnection('hydrating', __('Connecting display…', 'coffeepos'), false);
                transport.post('display.ready', { last_known_revision: 0 }, 0, 0, 'cashier');
                scheduleHandshake(2000);
            } catch (error) {
                setConnection('unsupported', __('This display must use the same browser profile and requires BroadcastChannel support.', 'coffeepos'), true);
            }
            recoverCart(sessionId);
        }
        function startPairing() {
            try {
                pairing = CoffeePOS.sync.createDisplayPairing({
                    scope: String(config.displayPairingScope || ''),
                    source: 'customer',
                    onMessage: function (message) {
                        const sessionId = String(message.payload.pos_session_id || '');
                        if (message.type !== 'display.control.pair' || !protocol.validSessionId(sessionId)) { return; }
                        if (transport && store.getState().posSessionId === sessionId) { requestSnapshot(); return; }
                        connect(sessionId);
                    },
                    onError: function () {
                        setConnection('reconnecting', __('Customer Display pairing is unavailable.', 'coffeepos'), true);
                    }
                });
                pairing.post('display.control.ready', {}, 'cashier');
            } catch (error) {
                setConnection('reconnecting', __('Customer Display pairing is unavailable.', 'coffeepos'), true);
            }
        }
        async function loadCatalog() {
            catalog.setStatus('loading', __('Loading menu…', 'coffeepos'));
            try { const data = await api.loadCatalog(); store.setCatalog(data.catalog); catalog.render(data.catalog); }
            catch (error) { store.setCatalogStatus('error'); catalog.setStatus('error', error.message || __('The menu could not be loaded.', 'coffeepos')); }
        }
        function onClick(event) {
            const trigger = event.target.closest('[data-action]'); if (!trigger) { return; }
            const action = trigger.getAttribute('data-action');
            if (action === 'retry-customer-catalog') { loadCatalog(); }
            if (action === 'retry-display-sync' && protocol.validSessionId(store.getState().posSessionId)) { recoverCart(store.getState().posSessionId); requestSnapshot(); }
        }
        function init() {
            window.name = 'coffeepos-customer-display';
            root.addEventListener('click', onClick); loadCatalog(); startPairing();
            if (memberPinChannel) { memberPinChannel.addEventListener('message', onMemberPinMessage); }
            if (memberPinDialog) {
                memberPinDialog.addEventListener('click', closeMemberPin);
                memberPinDialog.addEventListener('close', function () { window.clearTimeout(memberPinTimer); memberPinTimer = 0; memberPinDialog.querySelector('[data-field="customer-member-pin"]').textContent = ''; });
            }
            if (config.pairingState !== 'paired' || !protocol.validSessionId(config.posSessionId)) {
                setConnection('unpaired', config.pairingState === 'invalid' ? __('The pairing link is invalid. Open Customer Display from Cashier.', 'coffeepos') : __('Waiting for Cashier pairing…', 'coffeepos'), false);
                render(); return;
            }
            if (window.history && typeof window.history.replaceState === 'function') {
                const cleanUrl = new window.URL(window.location.href);
                cleanUrl.searchParams.delete('pos_session_id');
                window.history.replaceState({}, '', cleanUrl.pathname + cleanUrl.search + cleanUrl.hash);
            }
            connect(config.posSessionId);
        }
        return { init: init, getState: store.getState, destroy: function () { if (transport) { transport.close(); } if (pairing) { pairing.close(); } if (memberPinChannel) { memberPinChannel.close(); } window.clearTimeout(memberPinTimer); window.clearTimeout(rolloverTimer); window.clearTimeout(handshakeTimer); window.clearTimeout(disconnectedReloadTimer); } };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
