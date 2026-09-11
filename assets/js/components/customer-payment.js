(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    const sprintf = window.wp.i18n.sprintf;
    CoffeePOS.components = CoffeePOS.components || {};
    CoffeePOS.components.createCustomerPayment = function (root, renderer) {
        const protocol = CoffeePOS.sync.protocol;
        const panel = root.querySelector('[data-component="customer-payment"]');
        const thankYou = root.querySelector('[data-component="customer-thank-you"]');
        const qr = root.querySelector('[data-component="customer-vietqr"]');
        const itemsTarget = root.querySelector('[data-component="customer-payment-items"]');
        function text(field, value) { const element = panel.querySelector('[data-field="' + field + '"]'); if (element) { element.textContent = String(value || ''); } }
        function render(screenState, payment, order, cart) {
            const visible = ['checkout', 'payment_pending', 'payment_success'].indexOf(screenState) !== -1;
            panel.hidden = !visible; thankYou.hidden = true;
            if (!visible) { qr.hidden = true; qr.querySelector('img').removeAttribute('src'); return; }
            panel.setAttribute('data-state', screenState);
            const method = payment && payment.method;
            const summary = payment && payment.summary ? payment.summary : cart;
            const stateText = screenState === 'payment_success'
                ? __('Order completed successfully', 'coffeepos')
                : (method === 'bank_transfer'
                    ? (payment && payment.provider_available === false ? __('Bank transfer is unavailable.', 'coffeepos') : __('Please scan the QR to transfer payment', 'coffeepos'))
                    : __('Please give the cash payment to the cashier', 'coffeepos'));
            text('customer-payment-status', stateText);
            text('customer-payment-amount', payment && payment.amount_display || summary && summary.total && summary.total.display);
            text('customer-payment-reference', payment && payment.reference);
            text('customer-payment-change', payment && payment.change_display ? sprintf(__('Change: %s', 'coffeepos'), payment.change_display) : '');
            renderer.renderList('coffeepos-customer-payment-item-template', cart && Array.isArray(cart.items) ? cart.items : [], itemsTarget);
            text('customer-payment-subtotal', summary && summary.subtotal && summary.subtotal.display);
            text('customer-payment-discount', summary && summary.discount && summary.discount.display);
            text('customer-payment-total', summary && summary.total && summary.total.display);
            const url = protocol.safeQrUrl(payment && payment.qr && payment.qr.image_url);
            qr.hidden = !url || method !== 'bank_transfer' || screenState === 'payment_success';
            if (url) { qr.querySelector('img').src = url; } else { qr.querySelector('img').removeAttribute('src'); }
        }
        return { render: render };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
