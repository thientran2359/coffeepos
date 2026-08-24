(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.screens = CoffeePOS.screens || {};
    CoffeePOS.screens.createKdsController = function (root) {
        const config = window.CoffeePOSConfig || {};
        const kdsSoundEnabled = config.kdsSoundEnabled === true || config.kdsSoundEnabled === 1 || config.kdsSoundEnabled === '1';
        const renderer = new CoffeePOS.ui.TemplateRenderer();
        const toast = CoffeePOS.ui.createToastController(root, renderer);
        const api = CoffeePOS.api.createPosApi(CoffeePOS.api.createClient());
        const store = CoffeePOS.state.createKdsStore();
        const grid = CoffeePOS.components.createKdsOrderGrid(root, renderer);
        const sound = CoffeePOS.components.createKdsSound(kdsSoundEnabled);
        const timer = CoffeePOS.components.createKdsTimer(root, function () { return store.getState().serverOffset; });
        const status = root.querySelector('[data-component="kds-refresh-status"]');
        const loading = root.querySelector('[data-component="kds-loading"]');
        const empty = root.querySelector('[data-component="kds-empty"]');
        const errorBox = root.querySelector('[data-component="kds-error"]');
        const errorText = root.querySelector('[data-field="kds-error-message"]');
        const soundButton = root.querySelector('[data-action="toggle-kds-sound"]');
        if (soundButton && !kdsSoundEnabled) { soundButton.hidden = true; }
        let poller = null;

        function selectedStates() { const filter = store.getState().filter; return filter === 'all' ? ['new', 'preparing', 'ready'] : [filter]; }
        function render() {
            const state = store.getState();
            root.querySelector('[data-component="kds-screen"]').setAttribute('data-state', state.loaded ? 'ready' : 'loading');
            loading.hidden = state.loaded; empty.hidden = !state.loaded || state.orders.length > 0; errorBox.hidden = state.error === '';
            errorText.textContent = state.error; grid.render(state.orders, state.pendingId); timer.tick();
            if (soundButton) { soundButton.textContent = sound.isEnabled() ? 'Sound on' : 'Sound off'; soundButton.setAttribute('aria-pressed', sound.isEnabled() ? 'true' : 'false'); }
        }
        function operationId() { return 'kds-' + (window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2)); }
        function findOrder(id) { return store.getState().orders.find(function (order) { return Number(order.id) === Number(id); }); }
        async function transition(card, targetState) {
            const order = findOrder(card.getAttribute('data-order-id')); if (!order) { return; }
            store.setPending(order.id); render();
            try {
                const data = await api.transitionKdsOrder(order.id, { expected_state: order.kds.state, expected_revision: order.kds.revision, target_state: targetState, client_operation_id: operationId() });
                store.replace(data.order); toast.show('Order updated.', 'success');
            } catch (error) {
                if (error.code === 'order_state_conflict' && error.details && error.details.order) { store.replace(error.details.order); }
                toast.show(error.message || 'Order could not be updated.', 'error');
            } finally { store.setPending(0); render(); }
        }
        function onClick(event) {
            const trigger = event.target.closest('[data-action]'); if (!trigger) { return; }
            const action = trigger.getAttribute('data-action');
            if (action === 'refresh-kds') { poller.refresh(); }
            if (action === 'toggle-kds-sound') { sound.toggle(); render(); }
            if (action === 'filter-kds-state') { store.setFilter(trigger.getAttribute('data-state-filter')); root.querySelectorAll('[data-action="filter-kds-state"]').forEach(function (button) { button.setAttribute('aria-pressed', button === trigger ? 'true' : 'false'); }); poller.refresh(); }
            if (action === 'transition-kds-order') { transition(trigger.closest('[data-component="kds-order-card"]'), trigger.getAttribute('data-target-state')); }
        }
        function init() {
            root.addEventListener('click', onClick); timer.start(); render();
            poller = CoffeePOS.core.createPollingController({
                interval: Number(config.pollIntervalMs) || 5000,
                load: function (signal) { return api.loadKdsOrders(selectedStates(), signal); },
                onStart: function () { status.textContent = store.isLoaded() ? 'Refreshing…' : 'Loading orders…'; },
                onData: function (data) { sound.accept(data.orders || []); store.accept(data); status.textContent = 'Updated now'; render(); },
                onError: function (error) { store.setError(error.message || 'Unable to load orders.'); status.textContent = 'Refresh failed'; render(); }
            });
            poller.start();
        }
        return { init: init, getState: store.getState, destroy: function () { if (poller) { poller.destroy(); } timer.destroy(); root.removeEventListener('click', onClick); } };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
