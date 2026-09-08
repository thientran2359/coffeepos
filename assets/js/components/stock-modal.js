(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    const setState = CoffeePOS.core.setState;

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createStockModalController = function (root, renderer, api, onUpdated) {
        const element = root.querySelector('[data-component="stock-modal"]');

        if (!element) {
            return { open: function () {} };
        }

        const title = element.querySelector('[data-component="stock-modal-title"]');
        const loading = element.querySelector('[data-component="stock-modal-loading"]');
        const errorBox = element.querySelector('[data-component="stock-modal-error"]');
        const errorMessage = element.querySelector('[data-component="stock-modal-error-message"]');
        const content = element.querySelector('[data-component="stock-modal-content"]');
        const targetField = element.querySelector('[data-component="stock-target-field"]');
        const targetSelect = element.querySelector('[data-component="stock-target"]');
        const currentValue = element.querySelector('[data-component="stock-current-value"]');
        const quantityField = element.querySelector('[data-component="stock-quantity-field"]');
        const quantityInput = element.querySelector('[data-component="stock-quantity"]');
        const statusField = element.querySelector('[data-component="stock-status-field"]');
        const reasonInput = element.querySelector('[data-component="stock-reason"]');
        const validationError = element.querySelector('[data-component="stock-validation-error"]');
        const submit = element.querySelector('[data-component="stock-submit"]');
        let productId = 0;
        let targets = [];
        let pending = false;
        let request = null;

        const lifecycle = CoffeePOS.ui.createDialogLifecycle(element, null, function () {
            if (request) {
                request.abort();
                request = null;
            }
        }, function () { return !pending; });

        function selectedTarget() {
            return targets.find(function (target) { return String(target.id) === String(targetSelect.value); }) || null;
        }

        function showValidation(message) {
            validationError.textContent = message || '';
            validationError.hidden = !message;
        }

        function renderTarget() {
            const target = selectedTarget();
            if (!target) {
                return;
            }

            const inStockLabel = __('In stock', 'coffeepos');
            const outOfStockLabel = __('Out of stock', 'coffeepos');
            currentValue.textContent = target.manages_quantity
                ? String(target.quantity === null ? 0 : target.quantity) + ' · ' + (target.is_in_stock ? inStockLabel : outOfStockLabel)
                : (target.is_in_stock ? inStockLabel : outOfStockLabel);
            quantityField.hidden = !target.manages_quantity;
            statusField.hidden = target.manages_quantity;
            quantityInput.value = target.quantity === null ? '0' : String(target.quantity);
            element.querySelectorAll('input[name="coffeepos_stock_status"]').forEach(function (input) {
                input.checked = input.value === target.stock_status;
            });
            showValidation('');
        }

        function showError(error) {
            loading.hidden = true;
            content.hidden = true;
            errorMessage.textContent = error && error.message ? error.message : __('Stock could not be loaded.', 'coffeepos');
            errorBox.hidden = false;
            setState(element, 'error');
        }

        async function open(item) {
            productId = Number(item && item.productId || 0);
            targets = [];
            title.textContent = String(item && item.name || __('Update stock', 'coffeepos'));
            loading.hidden = false;
            errorBox.hidden = true;
            content.hidden = true;
            reasonInput.value = '';
            showValidation('');
            setState(element, 'loading');
            lifecycle.open();
            request = new window.AbortController();

            try {
                const data = await api.loadProductStock(productId, request.signal);
                targets = Array.isArray(data.stock && data.stock.targets) ? data.stock.targets : [];
                renderer.renderList('coffeepos-stock-target-template', targets, targetSelect);
                targetField.hidden = targets.length < 2;
                loading.hidden = true;
                content.hidden = false;
                setState(element, 'open');
                renderTarget();
            } catch (error) {
                if (!error || error.name !== 'AbortError') {
                    showError(error);
                }
            } finally {
                request = null;
            }
        }

        async function save(event) {
            event.preventDefault();
            const target = selectedTarget();
            const reason = reasonInput.value.trim();

            if (!target || reason.length < 3) {
                showValidation(__('Enter a reason of at least 3 characters.', 'coffeepos'));
                return;
            }

            const payload = { target_id: target.id, reason: reason };
            if (target.manages_quantity) {
                if (!/^\d+$/.test(quantityInput.value) || Number(quantityInput.value) < 0) {
                    showValidation(__('Enter a non-negative whole stock quantity.', 'coffeepos'));
                    return;
                }
                payload.mode = 'quantity';
                payload.quantity = Number(quantityInput.value);
            } else {
                const checked = element.querySelector('input[name="coffeepos_stock_status"]:checked');
                if (!checked) {
                    showValidation(__('Select a stock status.', 'coffeepos'));
                    return;
                }
                payload.mode = 'status';
                payload.stock_status = checked.value;
            }

            pending = true;
            submit.disabled = true;
            submit.textContent = __('Saving...', 'coffeepos');
            showValidation('');

            try {
                await api.updateProductStock(productId, payload);
                pending = false;
                lifecycle.close();
                if (typeof onUpdated === 'function') {
                    onUpdated();
                }
            } catch (error) {
                showValidation(error && error.message ? error.message : __('Stock could not be updated.', 'coffeepos'));
            } finally {
                pending = false;
                submit.disabled = false;
                submit.textContent = __('Update stock', 'coffeepos');
            }
        }

        element.addEventListener('click', function (event) {
            if (event.target.closest('[data-action="close-stock-modal"]')) {
                lifecycle.close();
            }
        });
        targetSelect.addEventListener('change', renderTarget);
        content.addEventListener('submit', save);

        return { open: open };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
