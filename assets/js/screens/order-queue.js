(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.screens = CoffeePOS.screens || {};
    CoffeePOS.screens.createOrderQueueController = function (root) {
        const config = window.CoffeePOSConfig || {};
        const renderer = new CoffeePOS.ui.TemplateRenderer();
        const toast = CoffeePOS.ui.createToastController(root, renderer);
        const confirmDialog = CoffeePOS.ui.createConfirmDialogController(root);
        const api = CoffeePOS.api.createPosApi(CoffeePOS.api.createClient());
        const store = CoffeePOS.state.createOrderQueueStore();
        const list = CoffeePOS.components.createOrderQueueList(root, renderer);
        const status = root.querySelector('[data-component="order-queue-refresh-status"]');
        const loading = root.querySelector('[data-component="order-queue-loading"]');
        const empty = root.querySelector('[data-component="order-queue-empty"]');
        const errorBox = root.querySelector('[data-component="order-queue-error"]');
        const errorText = root.querySelector('[data-field="order-queue-error-message"]');
        let poller = null;

        function operationId() { return 'queue-' + (window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2)); }
        function findOrder(id) { return store.getState().orders.find(function (order) { return Number(order.id) === Number(id); }); }
        function render() { const state = store.getState(); loading.hidden = state.loaded; empty.hidden = !state.loaded || state.orders.length > 0; errorBox.hidden = state.error === ''; errorText.textContent = state.error; list.render(state.orders, state.pendingId); }
        async function mutate(order, action) {
            if (!order) { return; } store.setPending(order.id); render();
            const payload = { expected_state: order.kds.state, expected_revision: order.kds.revision, client_operation_id: operationId() };
            try {
                const data = action === 'complete' ? await api.completeOperationalOrder(order.id, payload) : await api.cancelOperationalOrder(order.id, payload);
                if (data.queue_order) { store.replace(data.queue_order); } else { store.remove(order.id); } toast.show(action === 'complete' ? 'Order completed.' : 'Order cancelled.', 'success');
            } catch (error) {
                toast.show(error.message || 'Order could not be updated.', 'error'); poller.refresh();
            } finally { store.setPending(0); render(); }
        }
        async function printReceipt(order) {
            if (!order) { return; }
            try {
                const data = await api.loadReceipt(order.id); const receipt = data.receipt; const view = root.querySelector('[data-component="receipt"]');
                view.querySelector('[data-field="receipt-store-name"]').textContent = String(receipt.store.name || ''); view.querySelector('[data-field="receipt-store-address"]').textContent = String(receipt.store.address || ''); view.querySelector('[data-field="receipt-order-number"]').textContent = String(receipt.order.number || ''); view.querySelector('[data-field="receipt-total"]').textContent = String(receipt.totals.total + ' ' + receipt.totals.currency); renderer.renderList('coffeepos-receipt-item-template', receipt.items || [], view.querySelector('[data-component="receipt-items"]')); view.hidden = false; window.print(); view.hidden = true;
            } catch (error) { toast.show(error.message || 'Receipt could not be loaded.', 'error'); }
        }
        function onClick(event) {
            const trigger = event.target.closest('[data-action]'); if (!trigger) { return; } const action = trigger.getAttribute('data-action');
            if (action === 'refresh-order-queue') { poller.refresh(); return; }
            const card = trigger.closest('[data-component="order-queue-card"]'); const order = card ? findOrder(card.getAttribute('data-order-id')) : null;
            if (action === 'complete-order') { mutate(order, 'complete'); }
            if (action === 'cancel-order' && order) { confirmDialog.open({ title: 'Cancel order ' + order.number + '?', message: 'This cancels preparation and the WooCommerce order. It does not refund payment.', action: 'cancel:' + order.id }); }
            if (action === 'reprint-order') { printReceipt(order); }
        }
        function onChange(event) { const trigger = event.target.closest('[data-action="filter-order-queue"]'); if (!trigger) { return; } store.setFilter(trigger.getAttribute('data-filter'), trigger.value); poller.refresh(); }
        function onConfirm(event) { const action = String(event.detail && event.detail.action || ''); if (action.indexOf('cancel:') === 0) { mutate(findOrder(Number(action.slice(7))), 'cancel'); } }
        function init() {
            root.addEventListener('click', onClick); root.addEventListener('change', onChange); root.addEventListener('coffeepos:confirm', onConfirm); render();
            poller = CoffeePOS.core.createPollingController({ interval: Number(config.pollIntervalMs) || 5000, load: function (signal) { return api.loadOrderQueue(store.getState().filters, signal); }, onStart: function () { status.textContent = store.getState().loaded ? 'Refreshing…' : 'Loading orders…'; }, onData: function (data) { store.accept(data); status.textContent = 'Updated now'; render(); }, onError: function (error) { store.setError(error.message || 'Unable to load orders.'); status.textContent = 'Refresh failed'; render(); } });
            poller.start();
        }
        return { init: init, getState: store.getState, destroy: function () { if (poller) { poller.destroy(); } root.removeEventListener('click', onClick); root.removeEventListener('change', onChange); root.removeEventListener('coffeepos:confirm', onConfirm); } };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
