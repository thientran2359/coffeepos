(function (window, document) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.components = CoffeePOS.components || {};
    CoffeePOS.components.createKdsOrderGrid = function (root, renderer) {
        const target = root.querySelector('[data-component="kds-order-grid"]');
        return { render: function (orders, pendingId) {
            const output = document.createDocumentFragment();
            (orders || []).forEach(function (order) {
                const fragment = renderer.render('coffeepos-kds-order-card-template', order);
                renderer.renderList('coffeepos-kds-order-item-template', order.items || [], fragment.querySelector('[data-component="kds-order-items"]'));
                const button = fragment.querySelector('[data-action="transition-kds-order"]');
                if (button) { button.disabled = Number(order.id) === Number(pendingId); }
                output.appendChild(fragment);
            });
            target.replaceChildren(output);
        } };
    };
    window.CoffeePOS = CoffeePOS;
}(window, document));
