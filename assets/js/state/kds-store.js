(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.state = CoffeePOS.state || {};
    CoffeePOS.state.createKdsStore = function () {
        const state = { orders: [], filter: 'all', loaded: false, pendingId: 0, serverOffset: 0, error: '' };
        function orders() { return state.orders.slice(); }
        return {
            getState: function () { return { orders: orders(), filter: state.filter, loaded: state.loaded, pendingId: state.pendingId, serverOffset: state.serverOffset, error: state.error }; },
            accept: function (data) { state.orders = Array.isArray(data.orders) ? data.orders : []; state.serverOffset = Date.parse(data.server_time || '') - Date.now(); if (!Number.isFinite(state.serverOffset)) { state.serverOffset = 0; } state.loaded = true; state.error = ''; },
            setFilter: function (value) { state.filter = ['all', 'new', 'preparing', 'ready'].indexOf(value) === -1 ? 'all' : value; },
            setPending: function (id) { state.pendingId = Number(id) || 0; },
            replace: function (order) { const id = Number(order && order.id); state.orders = state.orders.filter(function (item) { return Number(item.id) !== id; }); if (order && ['new', 'preparing', 'ready'].indexOf(order.kds.state) !== -1) { state.orders.push(order); } },
            setError: function (message) { state.error = String(message || ''); },
            isLoaded: function () { return state.loaded; }
        };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
