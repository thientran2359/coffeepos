(function (window, document) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.components = CoffeePOS.components || {};
    CoffeePOS.components.createOrderQueueList = function (root, renderer) {
        const target = root.querySelector('[data-component="order-queue-list"]');
        return { render: function (orders, pendingId) {
            const output = document.createDocumentFragment();
            (orders || []).forEach(function (order) { const fragment = renderer.render('coffeepos-order-queue-card-template', order); if (Number(order.id) === Number(pendingId)) { fragment.querySelectorAll('button').forEach(function (button) { button.disabled = true; }); } output.appendChild(fragment); });
            target.replaceChildren(output);
        } };
    };
    window.CoffeePOS = CoffeePOS;
}(window, document));
