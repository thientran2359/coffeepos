(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
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
            const stateText = screenState === 'payment_success'
                ? 'Order completed successfully'
                : (method === 'bank_transfer'
                    ? (payment && payment.provider_available === false ? 'Bank transfer is unavailable.' : 'Please scan the QR to transfer payment')
                    : 'Please give the cash payment to the cashier');
            text('customer-payment-status', stateText);
            text('customer-payment-amount', payment && payment.amount ? payment.amount + ' ' + String(payment.currency || order && order.currency || '') : '');
            text('customer-payment-reference', payment && payment.reference);
            text('customer-payment-change', payment && payment.change ? 'Change: ' + payment.change : '');
            renderer.renderList('coffeepos-customer-payment-item-template', cart && Array.isArray(cart.items) ? cart.items : [], itemsTarget);
            const summary = payment && payment.summary ? payment.summary : cart;
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
