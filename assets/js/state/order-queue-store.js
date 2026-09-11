(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.state = CoffeePOS.state || {};
    CoffeePOS.state.createOrderQueueStore = function () {
        const sortOrders = CoffeePOS.state.sortOperationalOrders;
        const state = { orders: [], filters: { kds_state: 'all', order_type: 'all' }, loaded: false, pendingId: 0, error: '' };
        return {
            getState: function () { return { orders: state.orders.slice(), filters: Object.assign({}, state.filters), loaded: state.loaded, pendingId: state.pendingId, error: state.error }; },
            accept: function (data) { state.orders = sortOrders(data.orders); state.loaded = true; state.error = ''; },
            setFilter: function (name, value) { if (Object.prototype.hasOwnProperty.call(state.filters, name)) { state.filters[name] = String(value || 'all'); } },
            setPending: function (id) { state.pendingId = Number(id) || 0; },
            replace: function (order) { const id = Number(order && order.id); state.orders = state.orders.filter(function (item) { return Number(item.id) !== id; }); if (order) { state.orders.push(order); } state.orders = sortOrders(state.orders); },
            remove: function (id) { state.orders = state.orders.filter(function (item) { return Number(item.id) !== Number(id); }); },
            setError: function (message) { state.error = String(message || ''); }
        };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
