(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.components = CoffeePOS.components || {};
    CoffeePOS.components.createCustomerCart = function (root, renderer) {
        const panel = root.querySelector('[data-component="customer-cart"]');
        const itemsTarget = root.querySelector('[data-component="customer-cart-items"]');
        const empty = root.querySelector('[data-component="customer-cart-empty"]');
        function text(field, value) { const element = panel.querySelector('[data-field="' + field + '"]'); if (element) { element.textContent = String(value || ''); } }
        function render(cart, screenState) {
            const items = cart && Array.isArray(cart.items) ? cart.items : [];
            renderer.renderList('coffeepos-customer-cart-item-template', items, itemsTarget); empty.hidden = items.length > 0;
            text('customer-subtotal', cart && cart.subtotal && cart.subtotal.display);
            text('customer-discount', cart && cart.discount && cart.discount.display);
            text('customer-total', cart && cart.total && cart.total.display);
            const customer = cart && cart.customer || {};
            text('customer-name', customer.is_guest ? '' : customer.display_name);
            const membership = customer.membership || {};
            text('customer-membership', membership.tier_label || membership.status_label || membership.points_display || '');
            const service = cart && cart.order_type === 'dine_in'
                ? 'Dine-in' + (cart.table && cart.table.table_label ? ' — ' + cart.table.table_label : '') : 'Takeaway';
            text('customer-service', cart ? service : '');
            const titles = { idle: 'Ready when you are', cart: 'Your order', checkout: 'Please review your order', payment_pending: 'Payment pending', payment_success: 'Payment successful', thank_you: 'Thank you!' };
            text('customer-state-title', titles[screenState] || titles.idle);
            panel.setAttribute('data-state', screenState || 'idle');
        }
        return { render: render };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
