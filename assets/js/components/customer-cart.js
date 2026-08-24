(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
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
            const isGuest = customer.mode === 'guest' || customer.is_guest === true;
            text('customer-mode', isGuest ? __('Guest', 'coffeepos') : __('Member', 'coffeepos'));
            text('customer-name', isGuest ? '' : customer.display_name);
            text('customer-phone-masked', isGuest ? '' : customer.phone_masked);
            const membership = customer.membership || {};
            text('customer-membership', membership.tier_label || membership.status_label || membership.points_display || '');
            const service = cart && cart.order_type === 'dine_in'
                ? __('Dine-in', 'coffeepos') + (cart.table && cart.table.table_label ? ' — ' + cart.table.table_label : '') : __('Takeaway', 'coffeepos');
            text('customer-service', cart ? service : '');
            const titles = { idle: __('Ready when you are', 'coffeepos'), cart: __('Your order', 'coffeepos'), checkout: __('Please review your order', 'coffeepos'), payment_pending: __('Payment pending', 'coffeepos'), payment_success: __('Payment successful', 'coffeepos'), thank_you: __('Thank you!', 'coffeepos') };
            text('customer-state-title', titles[screenState] || titles.idle);
            panel.setAttribute('data-state', screenState || 'idle');
        }
        return { render: render };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
