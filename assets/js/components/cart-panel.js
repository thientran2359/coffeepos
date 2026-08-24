(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    const setState = CoffeePOS.core.setState;

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createCartPanelController = function (root, renderer) {
        const panel = root.querySelector('[data-component="cart-panel"]');
        const itemsRegion = root.querySelector('[data-component="cart-items"]');
        const itemTarget = root.querySelector('[data-component="cart-item-list"]');
        const empty = root.querySelector('[data-component="cart-empty"]');
        const loading = root.querySelector('[data-component="cart-loading"]');
        const error = root.querySelector('[data-component="cart-error"]');
        const count = root.querySelector('[data-component="cart-count"]');
        const clearButton = root.querySelector('[data-action="clear-cart"]');
        const subtotal = root.querySelector('[data-component="cart-subtotal"]');
        const discount = root.querySelector('[data-component="cart-discount"]');
        const total = root.querySelector('[data-component="cart-total"]');
        const checkoutButton = root.querySelector('[data-action="checkout"]');
        const checkoutRegion = root.querySelector('[data-component="checkout"]');
        const coupon = root.querySelector('[data-component="coupon"]');
        const couponEmpty = root.querySelector('[data-component="coupon-empty"]');
        const couponApplied = root.querySelector('[data-component="coupon-applied"]');
        const couponCode = root.querySelector('[data-component="coupon-code"]');
        const orderNoteDialog = root.querySelector('[data-component="order-note-dialog"]');
        const orderNoteTrigger = root.querySelector('[data-component="order-note-trigger"]');
        const orderNoteSummary = root.querySelector('[data-component="order-note-summary"]');
        const orderNote = root.querySelector('[data-component="order-note-input"]');
        const orderNoteStatus = root.querySelector('[data-component="order-note-status"]');
        const orderNoteLifecycle = CoffeePOS.ui.createDialogLifecycle(orderNoteDialog, null, function () {
            if (orderNoteStatus) {
                orderNoteStatus.textContent = '';
            }
        });

        function setStatus(status) {
            setState(panel, status);
            setState(itemsRegion, status);

            if (loading) {
                loading.hidden = status !== 'loading' && status !== 'updating';
            }

            if (error) {
                error.hidden = status !== 'error';
            }

            if (empty) {
                empty.hidden = status !== 'empty';
            }
        }

        function render(cart) {
            const items = Array.isArray(cart && cart.items) ? cart.items : [];
            renderer.renderList('coffeepos-cart-item-template', items, itemTarget);

            if (count) {
                count.textContent = String(Number(cart && cart.total_quantity) || 0);
            }

            if (subtotal) {
                subtotal.textContent = String((cart && cart.subtotal && cart.subtotal.display) || '');
            }

            if (discount) {
                discount.textContent = String((cart && cart.discount && cart.discount.display) || '');
            }

            if (total) {
                total.textContent = String((cart && cart.total && cart.total.display) || '');
            }

            if (clearButton) {
                clearButton.disabled = items.length === 0 || cart.state !== 'active';
            }

            const appliedCode = cart && cart.payment && cart.payment.coupon_code || '';
            setState(coupon, appliedCode ? 'applied' : 'empty');
            couponEmpty.hidden = Boolean(appliedCode);
            couponApplied.hidden = !appliedCode;
            couponCode.textContent = String(appliedCode);

            const note = String(cart && cart.order_note || '');
            if (orderNote && window.document.activeElement !== orderNote) {
                orderNote.value = note;
            }
            if (orderNoteStatus) {
                orderNoteStatus.textContent = '';
            }
            if (orderNoteSummary) {
                orderNoteSummary.textContent = note || __('No order note', 'coffeepos');
            }
            if (orderNoteTrigger) {
                setState(orderNoteTrigger, note ? 'set' : 'empty');
                orderNoteTrigger.setAttribute('aria-label', note ? __('Edit order note', 'coffeepos') : __('Add order note', 'coffeepos'));
            }

            const ready = Boolean(cart && cart.validation && cart.validation.checkout_ready);
            checkoutButton.disabled = !ready;
            setState(checkoutRegion, ready ? 'ready' : 'disabled');

            setStatus(items.length === 0 ? 'empty' : 'normal');
        }

        return {
            render: render,
            setLoading: function () { setStatus('loading'); },
            setUpdating: function () {
                setStatus('updating');

                if (clearButton) {
                    clearButton.disabled = true;
                }
            },
            setError: function () { setStatus('error'); },
            openOrderNote: function () {
                if (orderNoteStatus) {
                    orderNoteStatus.textContent = '';
                }
                orderNoteLifecycle.open();
            },
            closeOrderNote: function () { orderNoteLifecycle.close(); },
            setOrderNoteStatus: function (message) {
                if (orderNoteStatus) { orderNoteStatus.textContent = String(message || ''); }
            }
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
