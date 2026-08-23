(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.state = CoffeePOS.state || {};
    CoffeePOS.state.createCustomerDisplayStore = function () {
        const state = {
            posSessionId: '', connection: 'unpaired', catalogStatus: 'loading', catalog: null,
            screenState: 'idle', cart: null, payment: null, order: null,
            revision: -1, workflowSequence: -1, hydrating: true
        };
        return {
            getState: function () { return state; },
            pair: function (id) { state.posSessionId = id; state.revision = -1; state.workflowSequence = -1; state.hydrating = true; },
            setConnection: function (value) { state.connection = value; },
            setCatalog: function (catalog) { state.catalog = catalog; state.catalogStatus = 'normal'; },
            setCatalogStatus: function (value) { state.catalogStatus = value; },
            hydrate: function (snapshot, revision, sequence) {
                state.screenState = String(snapshot.screen_state || 'idle'); state.cart = snapshot.cart || null;
                state.payment = snapshot.payment || null; state.order = snapshot.order || null;
                state.revision = revision; state.workflowSequence = sequence; state.hydrating = false; state.connection = 'connected';
            },
            acceptCart: function (cart, revision) { state.cart = cart; state.revision = revision; state.hydrating = false; },
            acceptWorkflow: function (screenState, payment, order, sequence) {
                state.screenState = screenState; state.payment = payment === undefined ? state.payment : payment;
                state.order = order === undefined ? state.order : order; state.workflowSequence = sequence;
            }
        };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
