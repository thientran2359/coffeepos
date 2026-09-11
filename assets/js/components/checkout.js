(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    const sprintf = window.wp.i18n.sprintf;
    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createCheckoutController = function (root, renderer, api, getCart, onNewCart, toast, onWorkflow) {
        const config = window.CoffeePOSConfig || {};
        const paymentMethods = Array.isArray(config.paymentMethods) && config.paymentMethods.length ? config.paymentMethods : ['cash'];
        const autoPrintReceipt = config.autoPrintReceipt === true || config.autoPrintReceipt === 1 || config.autoPrintReceipt === '1';
        const modal = root.querySelector('[data-component="checkout-modal"]');
        const success = root.querySelector('[data-component="payment-success"]');
        const cashPanel = root.querySelector('[data-component="cash-payment"]');
        const bankPanel = root.querySelector('[data-component="bank-transfer-payment"]');
        const receiptPrinter = CoffeePOS.components.createReceiptPrinter(root, renderer);
        const total = modal.querySelector('[data-field="payment-total"]');
        const received = modal.querySelector('[data-field="cash-received"]');
        const change = modal.querySelector('[data-field="cash-change"]');
        const errorBox = modal.querySelector('[data-component="checkout-error"]');
        const submit = modal.querySelector('[data-action="submit-checkout"]');
        let method = paymentMethods[0] === 'bank_transfer' ? 'bank_transfer' : 'cash';
        let operationId = '';
        let pending = false;
        let result = null;
        let reviewedRevision = -1;
        let statusSequence = 0;
        let publishedPaymentKey = '';
        let publishedCompletedOrder = '';
        let resumedOrderId = 0;
        let autoPrintedOrderId = 0;
        let previewSequence = 0;
        let previewPending = false;

        function notify(type, payload) {
            if (typeof onWorkflow === 'function') { onWorkflow(type, payload || {}); }
        }

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
                ? sprintf(__('Preview change: %s', 'coffeepos'), (entered - due).toFixed(decimals()))
                : __('Received amount is below total.', 'coffeepos');
            submit.disabled = pending || !Number.isFinite(entered) || entered < due;
        }
        function customerPayment(cart, state) {
            return {
                method: method,
                state: state,
                amount: normalizedTotal(cart),
                amount_display: String(cart.total && cart.total.display || ''),
                currency: String(cart.currency || '')
            };
        }
        async function loadBankPreview(cart) {
            const sequence = ++previewSequence;
            previewPending = true; submit.disabled = true; submit.textContent = __('Preparing QR…', 'coffeepos');
            try {
                const data = await api.previewVietQr({ pos_session_id: cart.pos_session_id, expected_revision: cart.revision });
                if (sequence !== previewSequence || method !== 'bank_transfer' || Number(getCart().revision) !== Number(cart.revision)) { return; }
                const payment = data.payment;
                bankPanel.setAttribute('data-state', payment.provider_available ? 'awaiting_confirmation' : 'provider_unavailable');
                bankPanel.querySelector('[data-field="payment-reference"]').textContent = String(payment.reference || '');
                const qr = bankPanel.querySelector('[data-component="vietqr"]');
                qr.hidden = true; qr.querySelector('img').removeAttribute('src');
                bankPanel.querySelector('[data-field="payment-status"]').textContent = payment.provider_available
                    ? __('The QR is displayed on Customer Display. Confirm only after the transfer appears in the bank.', 'coffeepos')
                    : __('VietQR beneficiary is not configured.', 'coffeepos');
                submit.disabled = !payment.provider_available;
                submit.textContent = __('Confirm received & complete', 'coffeepos');
                notify('payment.started', { order: null, payment: payment });
            } catch (error) {
                if (sequence !== previewSequence) { return; }
                errorBox.textContent = error.message || __('VietQR could not be prepared.', 'coffeepos'); errorBox.hidden = false;
                submit.disabled = true; submit.textContent = __('Complete checkout', 'coffeepos');
            } finally { if (sequence === previewSequence) { previewPending = false; } }
        }
        function chooseMethod(next) {
            if (paymentMethods.indexOf(next) === -1) { return; }
            method = next === 'bank_transfer' ? 'bank_transfer' : 'cash';
            modal.querySelectorAll('[data-payment-method]').forEach(function (button) {
                const active = button.getAttribute('data-payment-method') === method;
                button.classList.toggle('is-active', active); button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            previewSequence++;
            previewPending = false;
            cashPanel.hidden = method !== 'cash'; bankPanel.hidden = method !== 'bank_transfer';
            operationId = newOperationId();
            result = null; errorBox.hidden = true;
            const cart = getCart();
            notify('checkout.started', { cart: cart.customer_display || null, payment: customerPayment(cart, method === 'cash' ? 'awaiting_cash' : 'preparing_qr') });
            if (method === 'bank_transfer') { loadBankPreview(cart); return; }
            submit.textContent = __('Complete checkout', 'coffeepos'); submit.disabled = pending || previewPending; preview();
        }
        function setPendingControls(isFrozen) {
            modal.querySelectorAll('[data-action="close-checkout"]').forEach(function (button) { button.hidden = isFrozen; });
            const freshOrder = modal.querySelector('[data-action="start-fresh-order"]');
            if (freshOrder) { freshOrder.hidden = !isFrozen; }
        }
        function open() {
            const cart = getCart();
            if (!cart || !cart.validation || !cart.validation.checkout_ready) { return; }
            reviewedRevision = Number(cart.revision);
            operationId = newOperationId(); result = null; pending = false;
            total.textContent = String(cart.total.display || normalizedTotal(cart));
            received.value = ''; errorBox.hidden = true; submit.hidden = false;
            setPendingControls(false);
            chooseMethod(paymentMethods[0]); setOpen(modal, true, 'normal');
            if (method === 'cash') { received.focus(); }
        }
        function close() {
            if (!pending && !(result && result.payment && result.payment.state === 'pending')) {
                setOpen(modal, false);
                notify('checkout.closed');
            }
        }
        function reconcileCart(cart) {
            if (!modal.hidden && Number(cart.revision) !== reviewedRevision) {
                reviewedRevision = Number(cart.revision); operationId = newOperationId();
                total.textContent = String(cart.total.display || normalizedTotal(cart));
                errorBox.textContent = __('The cart changed. Review the updated total before submitting.', 'coffeepos'); errorBox.hidden = false;
                preview();
            }
        }
        async function submitCheckout() {
            if (pending) { return; }
            const cart = getCart(); pending = true; submit.disabled = true; errorBox.hidden = true;
            modal.setAttribute('data-state', 'submitting');
            const payment = { method: method };
            if (method === 'cash') { payment.received_amount = String(received.value).trim(); }
            if (method === 'bank_transfer') { payment.confirmed_received = true; }
            try {
                result = await api.checkout({ pos_session_id: cart.pos_session_id, expected_revision: cart.revision, client_operation_id: operationId, payment: payment });
                if (result.payment.state === 'paid') {
                    setOpen(modal, false); showSuccess(result, true); return;
                }
                showPending(result);
            } catch (error) {
                if (error.code === 'cart_revision_conflict' && error.details && error.details.cart) { onNewCart(error.details.cart, false); reconcileCart(error.details.cart); }
                if (error.code === 'insufficient_cash' && error.details) {
                    const receivedMinor = Number(error.details.received_minor);
                    const requiredMinor = Number(error.details.required_minor);
                    const divisor = Math.pow(10, decimals());
                    errorBox.textContent = Number.isFinite(receivedMinor) && Number.isFinite(requiredMinor)
                        ? sprintf(__('Cash received (%1$s) is below the required total (%2$s).', 'coffeepos'), (receivedMinor / divisor).toFixed(decimals()), (requiredMinor / divisor).toFixed(decimals()))
                        : (error.message || __('Checkout failed.', 'coffeepos'));
                } else {
                    errorBox.textContent = error.message || __('Checkout failed.', 'coffeepos');
                }
                errorBox.hidden = false;
                modal.setAttribute('data-state', 'error');
            } finally { pending = false; if (!result || result.payment.state !== 'pending') { preview(); } }
        }
        function showPending(data) {
            modal.setAttribute('data-state', 'payment_pending'); submit.hidden = true;
            setPendingControls(true);
            cashPanel.hidden = true; bankPanel.hidden = false;
            bankPanel.setAttribute('data-state', data.payment.provider_available ? 'pending' : 'provider_unavailable');
            bankPanel.querySelector('[data-field="payment-reference"]').textContent = String(data.payment.reference || '');
            bankPanel.querySelector('[data-field="payment-status"]').textContent = data.payment.provider_available ? __('Payment pending verification.', 'coffeepos') : __('VietQR beneficiary is not configured. Order remains pending.', 'coffeepos');
            const qr = bankPanel.querySelector('[data-component="vietqr"]');
            qr.hidden = true; qr.querySelector('img').removeAttribute('src');
            bankPanel.querySelector('[data-action="refresh-payment-status"]').hidden = false;
            const publishKey = String(data.order.id) + ':' + String(data.payment.state);
            if (publishedPaymentKey !== publishKey) {
                publishedPaymentKey = publishKey;
                notify('payment.started', { order: data.order, payment: data.payment });
            }
        }
        function showSuccess(data, allowAutoPrint) {
            success.querySelector('[data-field="success-order-number"]').textContent = String(data.order.number);
            success.querySelector('[data-field="success-total"]').textContent = String(data.order.total_display || '');
            const cashChange = data.payment.change_display ? sprintf(__('Change: %s', 'coffeepos'), data.payment.change_display) : '';
            success.querySelector('[data-field="success-change"]').textContent = cashChange;
            setOpen(success, true, 'paid');
            const orderKey = String(data.order.id);
            if (publishedCompletedOrder !== orderKey) {
                publishedCompletedOrder = orderKey;
                notify('payment.updated', { order: data.order, payment: data.payment });
                notify('sale.completed', { order: data.order, payment: data.payment });
            }
            if (allowAutoPrint === true && autoPrintReceipt && autoPrintedOrderId !== Number(data.order.id)) {
                autoPrintedOrderId = Number(data.order.id);
                printReceipt();
            }
        }
        async function refreshStatus() {
            if (!result || !result.order) { return; }
            const sequence = ++statusSequence;
            try {
                const current = await api.paymentStatus(result.order.id);
                if (sequence !== statusSequence) { return; }
                result = current;
                if (current.payment.state === 'paid') { setOpen(modal, false); showSuccess(current, false); } else { showPending(current); }
            } catch (error) { toast.show(error.message || __('Payment status could not be refreshed.', 'coffeepos'), 'error'); }
        }
        async function resumeCart(cart) {
            const recoverableState = cart && (cart.state === 'checkout' || cart.state === 'completed');
            const orderId = recoverableState ? Number(cart.checkout_order_id || 0) : 0;
            if (orderId <= 0 || (resumedOrderId === orderId && result)) { return; }
            resumedOrderId = orderId;
            total.textContent = String(cart.total && cart.total.display || normalizedTotal(cart));
            received.value = ''; errorBox.hidden = true; submit.hidden = true;
            cashPanel.hidden = true; bankPanel.hidden = false;
            setPendingControls(true); setOpen(modal, true, 'recovering');
            pending = true;
            try {
                result = await api.paymentStatus(orderId);
                if (result.payment && result.payment.state === 'paid') { setOpen(modal, false); showSuccess(result, false); }
                else { showPending(result); }
            } catch (error) {
                errorBox.textContent = error.message || __('The pending checkout could not be recovered.', 'coffeepos');
                errorBox.hidden = false; modal.setAttribute('data-state', 'error');
            } finally { pending = false; }
        }
        async function startFreshOrder() {
            if (pending) { return; }
            pending = true;
            try {
                const data = await api.createCartSession();
                notify('display.reset', {
                    reason: 'new_order',
                    next_pos_session_id: data.cart.pos_session_id,
                    next_revision: Number(data.cart.revision) || 0
                });
                onNewCart(data.cart, true);
                setOpen(modal, false); result = null; resumedOrderId = 0;
            } catch (error) {
                errorBox.textContent = error.message || __('A new order could not be started.', 'coffeepos');
                errorBox.hidden = false;
            } finally { pending = false; }
        }
        async function printReceipt() {
            if (!result || !result.order || result.payment.state !== 'paid') { return; }
            try {
                const data = await api.loadReceipt(result.order.id);
                await receiptPrinter.print(data.receipt);
            } catch (error) { toast.show(error.message || __('Receipt could not be loaded.', 'coffeepos'), 'error'); }
        }
        function startNewOrder() {
            if (result && result.next_cart) {
                notify('display.reset', {
                    reason: 'new_order',
                    next_pos_session_id: result.next_cart.pos_session_id,
                    next_revision: Number(result.next_cart.revision) || 0
                });
            }
            if (result && result.next_cart) { onNewCart(result.next_cart, true); }
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
            else if (action === 'start-fresh-order') { startFreshOrder(); }
            else if (action === 'print-receipt') { printReceipt(); }
            else if (action === 'start-new-order') { startNewOrder(); }
        });
        return { open: open, reconcileCart: reconcileCart, resumeCart: resumeCart };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
