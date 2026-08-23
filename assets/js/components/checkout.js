(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createCheckoutController = function (root, renderer, api, getCart, onNewCart, toast) {
        const modal = root.querySelector('[data-component="checkout-modal"]');
        const success = root.querySelector('[data-component="payment-success"]');
        const cashPanel = root.querySelector('[data-component="cash-payment"]');
        const bankPanel = root.querySelector('[data-component="bank-transfer-payment"]');
        const total = modal.querySelector('[data-field="payment-total"]');
        const received = modal.querySelector('[data-field="cash-received"]');
        const change = modal.querySelector('[data-field="cash-change"]');
        const errorBox = modal.querySelector('[data-component="checkout-error"]');
        const submit = modal.querySelector('[data-action="submit-checkout"]');
        let method = 'cash';
        let operationId = '';
        let pending = false;
        let result = null;
        let reviewedRevision = -1;
        let statusSequence = 0;

        function decimals() { return Number(window.CoffeePOSConfig && window.CoffeePOSConfig.currencyDecimals || 0); }
        function normalizedTotal(cart) { return (Number(cart.total.amount_minor || 0) / Math.pow(10, decimals())).toFixed(decimals()); }
        function newOperationId() {
            if (window.crypto && window.crypto.randomUUID) { return 'cashier-' + window.crypto.randomUUID(); }
            return 'cashier-' + Date.now() + '-' + Math.random().toString(16).slice(2);
        }
        function setOpen(element, open, state) {
            element.hidden = !open;
            element.setAttribute('data-state', open ? (state || 'normal') : 'closed');
            document.body.classList.toggle('coffeepos-overlay-open', open);
        }
        function preview() {
            const cart = getCart();
            if (!cart || method !== 'cash') { return; }
            const entered = Number(received.value);
            const due = Number(normalizedTotal(cart));
            change.textContent = Number.isFinite(entered) && entered >= due
                ? 'Preview change: ' + (entered - due).toFixed(decimals())
                : 'Received amount is below total.';
            submit.disabled = pending || !Number.isFinite(entered) || entered < due;
        }
        function chooseMethod(next) {
            method = next === 'bank_transfer' ? 'bank_transfer' : 'cash';
            modal.querySelectorAll('[data-payment-method]').forEach(function (button) {
                const active = button.getAttribute('data-payment-method') === method;
                button.classList.toggle('is-active', active); button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            cashPanel.hidden = method !== 'cash'; bankPanel.hidden = method !== 'bank_transfer';
            operationId = newOperationId();
            submit.disabled = pending;
            preview();
        }
        function open() {
            const cart = getCart();
            if (!cart || !cart.validation || !cart.validation.checkout_ready) { return; }
            reviewedRevision = Number(cart.revision);
            operationId = newOperationId(); result = null; pending = false;
            total.textContent = String(cart.total.display || normalizedTotal(cart));
            received.value = ''; errorBox.hidden = true; submit.hidden = false;
            chooseMethod('cash'); setOpen(modal, true, 'normal'); received.focus();
        }
        function close() {
            if (!pending && !(result && result.payment && result.payment.state === 'pending')) {
                setOpen(modal, false);
            }
        }
        function reconcileCart(cart) {
            if (!modal.hidden && Number(cart.revision) !== reviewedRevision) {
                reviewedRevision = Number(cart.revision); operationId = newOperationId();
                total.textContent = String(cart.total.display || normalizedTotal(cart));
                errorBox.textContent = 'The cart changed. Review the updated total before submitting.'; errorBox.hidden = false;
                preview();
            }
        }
        async function submitCheckout() {
            if (pending) { return; }
            const cart = getCart(); pending = true; submit.disabled = true; errorBox.hidden = true;
            modal.setAttribute('data-state', 'submitting');
            const payment = { method: method };
            if (method === 'cash') { payment.received_amount = String(received.value).trim(); }
            try {
                result = await api.checkout({ pos_session_id: cart.pos_session_id, expected_revision: cart.revision, client_operation_id: operationId, payment: payment });
                if (result.payment.state === 'paid') {
                    setOpen(modal, false); showSuccess(result); return;
                }
                showPending(result);
            } catch (error) {
                if (error.code === 'cart_revision_conflict' && error.details && error.details.cart) { onNewCart(error.details.cart); reconcileCart(error.details.cart); }
                errorBox.textContent = error.message || 'Checkout failed.'; errorBox.hidden = false;
                modal.setAttribute('data-state', 'error');
            } finally { pending = false; if (!result || result.payment.state !== 'pending') { preview(); } }
        }
        function showPending(data) {
            modal.setAttribute('data-state', 'payment_pending'); submit.hidden = true;
            bankPanel.hidden = false; bankPanel.setAttribute('data-state', data.payment.provider_available ? 'pending' : 'provider_unavailable');
            bankPanel.querySelector('[data-field="payment-reference"]').textContent = String(data.payment.reference || '');
            bankPanel.querySelector('[data-field="payment-status"]').textContent = data.payment.provider_available ? 'Payment pending verification.' : 'VietQR beneficiary is not configured. Order remains pending.';
            const qr = bankPanel.querySelector('[data-component="vietqr"]');
            qr.hidden = !(data.payment.qr && data.payment.qr.image_url);
            if (!qr.hidden) { qr.querySelector('img').src = data.payment.qr.image_url; }
            bankPanel.querySelector('[data-action="refresh-payment-status"]').hidden = false;
        }
        function showSuccess(data) {
            success.querySelector('[data-field="success-order-number"]').textContent = String(data.order.number);
            success.querySelector('[data-field="success-total"]').textContent = String(data.order.total + ' ' + data.order.currency);
            const cashChange = data.payment.change ? 'Change: ' + data.payment.change : '';
            success.querySelector('[data-field="success-change"]').textContent = cashChange;
            setOpen(success, true, 'paid');
        }
        async function refreshStatus() {
            if (!result || !result.order) { return; }
            const sequence = ++statusSequence;
            try {
                const current = await api.paymentStatus(result.order.id);
                if (sequence !== statusSequence) { return; }
                result = current;
                if (current.payment.state === 'paid') { setOpen(modal, false); showSuccess(current); } else { showPending(current); }
            } catch (error) { toast.show(error.message || 'Payment status could not be refreshed.', 'error'); }
        }
        async function printReceipt() {
            if (!result || !result.order || result.payment.state !== 'paid') { return; }
            try {
                const data = await api.loadReceipt(result.order.id);
                const receipt = data.receipt; const view = root.querySelector('[data-component="receipt"]');
                view.querySelector('[data-field="receipt-store-name"]').textContent = String(receipt.store.name || '');
                view.querySelector('[data-field="receipt-store-address"]').textContent = String(receipt.store.address || '');
                view.querySelector('[data-field="receipt-order-number"]').textContent = String(receipt.order.number || '');
                view.querySelector('[data-field="receipt-total"]').textContent = String(receipt.totals.total + ' ' + receipt.totals.currency);
                renderer.renderList('coffeepos-receipt-item-template', receipt.items || [], view.querySelector('[data-component="receipt-items"]'));
                view.hidden = false; window.print();
            } catch (error) { toast.show(error.message || 'Receipt could not be loaded.', 'error'); }
        }
        function startNewOrder() {
            if (result && result.next_cart) { onNewCart(result.next_cart); }
            setOpen(success, false); result = null;
        }
        received.addEventListener('input', function () { operationId = newOperationId(); preview(); });
        root.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-action]'); if (!trigger) { return; }
            const action = trigger.getAttribute('data-action');
            if (action === 'close-checkout') { close(); }
            else if (action === 'select-payment-method') { chooseMethod(trigger.getAttribute('data-payment-method')); }
            else if (action === 'cash-quick-amount') { received.value = String((Number(received.value) || 0) + Number(trigger.getAttribute('data-amount') || 0)); received.dispatchEvent(new Event('input')); }
            else if (action === 'cash-exact') { received.value = normalizedTotal(getCart()); received.dispatchEvent(new Event('input')); }
            else if (action === 'submit-checkout') { submitCheckout(); }
            else if (action === 'refresh-payment-status') { refreshStatus(); }
            else if (action === 'print-receipt') { printReceipt(); }
            else if (action === 'start-new-order') { startNewOrder(); }
        });
        return { open: open, reconcileCart: reconcileCart };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
