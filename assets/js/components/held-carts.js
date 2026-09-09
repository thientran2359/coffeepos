(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    const setState = CoffeePOS.core.setState;

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createHeldCartsController = function (root, renderer, api, getCart, onCartChanged, confirmDialog, toast) {
        const listModal = root.querySelector('[data-component="held-carts-modal"]');
        const holdModal = root.querySelector('[data-component="hold-cart-modal"]');
        const listTarget = listModal.querySelector('[data-component="held-carts-list"]');
        const loading = listModal.querySelector('[data-component="held-carts-loading"]');
        const empty = listModal.querySelector('[data-component="held-carts-empty"]');
        const error = listModal.querySelector('[data-component="held-carts-error"]');
        const errorMessage = listModal.querySelector('[data-component="held-carts-error-message"]');
        const holdButton = listModal.querySelector('[data-component="hold-current-cart"]');
        const countBadge = root.querySelector('[data-component="held-carts-count"]');
        const holdForm = holdModal.querySelector('[data-component="hold-cart-form"]');
        const labelInput = holdModal.querySelector('[data-component="hold-cart-label"]');
        const holdError = holdModal.querySelector('[data-component="hold-cart-error"]');
        const holdSubmit = holdModal.querySelector('[data-component="hold-cart-submit"]');
        let listRequest = null;
        let pending = false;
        let pendingDeleteId = 0;

        const listLifecycle = CoffeePOS.ui.createDialogLifecycle(listModal, null, function () {
            if (listRequest) {
                listRequest.abort();
                listRequest = null;
            }
        }, function () { return !pending; });
        const holdLifecycle = CoffeePOS.ui.createDialogLifecycle(holdModal, null, function () {
            holdError.hidden = true;
            holdError.textContent = '';
        }, function () { return !pending; });

        function cartPayload() {
            const cart = getCart();
            return {
                pos_session_id: cart && cart.pos_session_id || '',
                expected_revision: cart && Number(cart.revision)
            };
        }

        function hasCartItems() {
            const cart = getCart();
            return Boolean(cart && Array.isArray(cart.items) && cart.items.length > 0);
        }

        function reconcileConflict(requestError) {
            const latest = requestError && requestError.code === 'cart_revision_conflict'
                && requestError.details && requestError.details.cart;
            if (!latest) {
                return false;
            }
            onCartChanged(latest, false);
            return true;
        }

        function updateHoldButton() {
            holdButton.disabled = pending || !hasCartItems();
        }

        function updateCount(count) {
            const value = Math.max(0, Number(count || 0));
            countBadge.textContent = String(value);
            countBadge.hidden = value === 0;
        }

        function prepareHeldCart(fragment, item) {
            const service = fragment.querySelector('[data-component="held-cart-service"]');
            const article = fragment.querySelector('[data-held-cart-id]');
            if (service) {
                service.textContent = item.order_type === 'dine_in'
                    ? (item.table_label || __('Dine-in', 'coffeepos'))
                    : __('Takeaway', 'coffeepos');
            }
            if (article) {
                article.querySelectorAll('[data-action]').forEach(function (button) {
                    button.setAttribute('data-held-cart-id', String(item.id));
                });
            }
        }

        function render(items) {
            const output = window.document.createDocumentFragment();
            items.forEach(function (item) {
                const fragment = renderer.render('coffeepos-held-cart-template', item);
                prepareHeldCart(fragment, item);
                output.appendChild(fragment);
            });
            listTarget.replaceChildren(output);
            loading.hidden = true;
            error.hidden = true;
            empty.hidden = items.length !== 0;
            listTarget.hidden = items.length === 0;
            updateCount(items.length);
            setState(listModal, items.length === 0 ? 'empty' : 'ready');
            updateHoldButton();
        }

        async function load(showLoading) {
            if (listRequest) {
                listRequest.abort();
            }
            const request = new window.AbortController();
            listRequest = request;
            if (showLoading !== false) {
                loading.hidden = false;
                empty.hidden = true;
                error.hidden = true;
                listTarget.hidden = true;
                setState(listModal, 'loading');
            }

            try {
                const data = await api.loadHeldCarts(request.signal);
                if (listRequest !== request) {
                    return;
                }
                render(Array.isArray(data.items) ? data.items : []);
            } catch (requestError) {
                if (requestError && requestError.name === 'AbortError') {
                    return;
                }
                loading.hidden = true;
                empty.hidden = true;
                listTarget.hidden = true;
                error.hidden = false;
                errorMessage.textContent = requestError.message || __('Held carts could not be loaded.', 'coffeepos');
                setState(listModal, 'error');
            } finally {
                if (listRequest === request) {
                    listRequest = null;
                }
            }
        }

        function open() {
            updateHoldButton();
            listLifecycle.open();
            load(true);
        }

        function openHold() {
            if (!hasCartItems()) {
                toast.show(__('Add at least one item before holding the cart.', 'coffeepos'), 'warning');
                return;
            }
            listLifecycle.close();
            labelInput.value = '';
            holdError.hidden = true;
            holdLifecycle.open();
            labelInput.focus();
        }

        async function hold(event) {
            event.preventDefault();
            const label = labelInput.value.trim();
            if (label === '') {
                holdError.textContent = __('Enter a label for the held cart.', 'coffeepos');
                holdError.hidden = false;
                return;
            }

            pending = true;
            holdSubmit.disabled = true;
            holdSubmit.textContent = __('Saving...', 'coffeepos');
            holdError.hidden = true;

            try {
                const data = await api.holdCart(Object.assign(cartPayload(), { label: label }));
                pending = false;
                holdLifecycle.close();
                onCartChanged(data.cart, true);
                updateCount(Number(countBadge.textContent || 0) + 1);
                toast.show(__('Cart held successfully.', 'coffeepos'), 'success');
            } catch (requestError) {
                reconcileConflict(requestError);
                holdError.textContent = requestError.message || __('The cart could not be held.', 'coffeepos');
                holdError.hidden = false;
            } finally {
                pending = false;
                holdSubmit.disabled = false;
                holdSubmit.textContent = __('Hold cart', 'coffeepos');
                updateHoldButton();
            }
        }

        async function resume(heldCartId, button) {
            if (hasCartItems()) {
                toast.show(__('Hold or clear the current cart before resuming another cart.', 'coffeepos'), 'warning');
                return;
            }

            pending = true;
            button.disabled = true;
            button.textContent = __('Resuming...', 'coffeepos');
            updateHoldButton();

            try {
                const data = await api.resumeHeldCart(heldCartId, cartPayload());
                pending = false;
                listLifecycle.close();
                onCartChanged(data.cart, false);
                updateCount(Math.max(0, Number(countBadge.textContent || 0) - 1));
                toast.show(__('Held cart resumed.', 'coffeepos'), 'success');
            } catch (requestError) {
                reconcileConflict(requestError);
                toast.show(requestError.message || __('The held cart could not be resumed.', 'coffeepos'), 'error');
                button.disabled = false;
                button.textContent = __('Resume', 'coffeepos');
            } finally {
                pending = false;
                updateHoldButton();
            }
        }

        async function remove(heldCartId) {
            pending = true;
            updateHoldButton();
            try {
                await api.deleteHeldCart(heldCartId);
                pendingDeleteId = 0;
                toast.show(__('Held cart deleted.', 'coffeepos'), 'success');
                await load(false);
            } catch (requestError) {
                toast.show(requestError.message || __('The held cart could not be deleted.', 'coffeepos'), 'error');
            } finally {
                pending = false;
                updateHoldButton();
            }
        }

        function onClick(event) {
            const trigger = event.target.closest('[data-action]');
            if (!trigger || (!listModal.contains(trigger) && !holdModal.contains(trigger))) {
                return;
            }
            const action = trigger.getAttribute('data-action');
            const heldCartId = Number(trigger.getAttribute('data-held-cart-id') || 0);

            if (action === 'close-held-carts') {
                listLifecycle.close();
            } else if (action === 'close-hold-cart') {
                holdLifecycle.close();
            } else if (action === 'open-hold-cart') {
                openHold();
            } else if (action === 'retry-held-carts') {
                load(true);
            } else if (action === 'resume-held-cart' && heldCartId > 0) {
                resume(heldCartId, trigger);
            } else if (action === 'delete-held-cart' && heldCartId > 0) {
                pendingDeleteId = heldCartId;
                confirmDialog.open({
                    title: __('Delete held cart?', 'coffeepos'),
                    message: __('This suspended cart will be permanently removed.', 'coffeepos'),
                    action: 'delete-held-cart'
                });
            }
        }

        function onConfirm(event) {
            if (event.detail && event.detail.action === 'delete-held-cart' && pendingDeleteId > 0) {
                remove(pendingDeleteId);
            }
        }

        function init() {
            root.addEventListener('click', onClick);
            root.addEventListener('coffeepos:confirm', onConfirm);
            holdForm.addEventListener('submit', hold);
            load(false);
        }

        return {
            init: init,
            open: open,
            setCart: updateHoldButton
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
