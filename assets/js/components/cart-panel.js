(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
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
                clearButton.disabled = items.length === 0;
            }

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
            setError: function () { setStatus('error'); }
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
