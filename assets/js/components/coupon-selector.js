(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createCouponSelector = function (root, renderer, api, getCart, onCart) {
        const modal = root.querySelector('[data-component="coupon-selector"]');
        const form = root.querySelector('[data-component="coupon-form"]');
        const input = form.querySelector('[name="coupon_code"]');
        const list = root.querySelector('[data-component="coupon-list"]');
        const status = root.querySelector('[data-component="coupon-status"]');
        let pending = false;
        let sequence = 0;

        function close() {
            modal.hidden = true;
            modal.setAttribute('data-state', 'closed');
            document.body.classList.remove('coffeepos-overlay-open');
        }

        async function open() {
            const cart = getCart();
            if (!cart) { return; }
            modal.hidden = false;
            modal.setAttribute('data-state', 'loading');
            document.body.classList.add('coffeepos-overlay-open');
            input.focus();
            const current = ++sequence;
            status.textContent = 'Loading coupons...';
            try {
                const data = await api.loadApplicableCoupons(cart.pos_session_id);
                if (current !== sequence) { return; }
                const items = Array.isArray(data.items) ? data.items : [];
                renderer.renderList('coffeepos-coupon-option-template', items, list);
                status.textContent = items.length ? 'Select a coupon or enter a code.' : 'No suggested coupons. You can still enter a code.';
                modal.setAttribute('data-state', items.length ? 'normal' : 'empty');
            } catch (error) {
                if (current !== sequence) { return; }
                status.textContent = error.message || 'Coupons could not be loaded.';
                modal.setAttribute('data-state', 'error');
            }
        }

        async function apply(code) {
            if (pending) { return; }
            const cart = getCart();
            pending = true;
            modal.setAttribute('data-state', 'applying');
            status.textContent = 'Applying coupon...';
            try {
                const data = await api.applyCoupon({ pos_session_id: cart.pos_session_id, expected_revision: cart.revision, code: String(code || '').trim() });
                onCart(data.cart);
                input.value = String(code || '').trim();
                close();
            } catch (error) {
                if (error.code === 'cart_revision_conflict' && error.details && error.details.cart) { onCart(error.details.cart); }
                status.textContent = error.message || 'Coupon could not be applied.';
                modal.setAttribute('data-state', 'invalid');
            } finally { pending = false; }
        }

        form.addEventListener('submit', function (event) { event.preventDefault(); apply(input.value); });
        root.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-action]');
            if (!trigger || !root.contains(trigger)) { return; }
            const action = trigger.getAttribute('data-action');
            if (action === 'close-coupon-selector') { close(); }
            if (action === 'apply-coupon-option') { apply(trigger.getAttribute('data-coupon-code')); }
        });
        return { open: open, close: close };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
