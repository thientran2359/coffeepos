(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    const sprintf = window.wp.i18n.sprintf;
    const setState = CoffeePOS.core.setState;
    const POS_SESSION_STORAGE_KEY = 'coffeepos.pos_session_id';

    CoffeePOS.screens = CoffeePOS.screens || {};

    CoffeePOS.screens.createCashierController = function (root) {
        const config = window.CoffeePOSConfig || {};
        const requireDineInTable = config.requireDineInTable === true || config.requireDineInTable === 1 || config.requireDineInTable === '1';
        const requireOpenShift = config.requireOpenShift === true || config.requireOpenShift === 1 || config.requireOpenShift === '1';
        const shiftsEnabled = config.shiftsEnabled === true || config.shiftsEnabled === 1 || config.shiftsEnabled === '1';
        const renderer = new CoffeePOS.ui.TemplateRenderer();
        renderer.registerUrlValidator('src', function (value) {
            if (value === undefined || value === null || value === '') {
                return true;
            }
            try {
                const url = new window.URL(String(value), window.location.href);
                return url.protocol === 'http:' || url.protocol === 'https:';
            } catch (error) {
                return false;
            }
        });

        const store = CoffeePOS.state.createCashierStore();
        const api = CoffeePOS.api.createPosApi(CoffeePOS.api.createClient());
        const toast = CoffeePOS.ui.createToastController(root, renderer);
        const syncBridge = CoffeePOS.components.createCashierSyncBridge(root, toast);
        const confirmDialog = CoffeePOS.ui.createConfirmDialogController(root);
        const catalogRenderer = CoffeePOS.components.createCatalogRenderer(root, renderer);
        const cartPanel = CoffeePOS.components.createCartPanelController(root, renderer);
        const categoryNav = CoffeePOS.components.createCategoryNavController(root, function (categoryId) {
            store.setActiveCategory(categoryId);
        });
        const search = CoffeePOS.components.createProductSearchController(root, renderer, categoryNav, function (term) {
            store.setSearchTerm(term);
        });
        let contextController = null;
        let couponSelector = null;
        let checkoutController = null;
        let heldCarts = null;
        const orderType = CoffeePOS.components.createOrderTypeController(root, function (requested) {
            if (mutationPending) {
                return;
            }
            if (requested === 'dine_in') {
                if (!requireDineInTable) {
                    setServiceContext('dine_in', 0).catch(function () {});
                } else {
                    contextController.openTables();
                }
                return;
            }
            setServiceContext('takeaway', 0).catch(function () {});
        });
        let catalogRequest = null;
        let catalogSequence = 0;
        let mutationPending = false;
        let activeShift = null;

        async function loadShift() {
            const label = root.querySelector('[data-component="shift-status"]');
            const header = root.querySelector('[data-component="cashier-header"]');
            if (!shiftsEnabled) {
                activeShift = null;
                if (label) {
                    label.hidden = true;
                }
                setState(header, 'shifts_disabled');
                return;
            }
            try {
                const data = await api.getCurrentShift();
                activeShift = data.shift || null;
                label.textContent = activeShift ? sprintf(__('Shift #%s open', 'coffeepos'), activeShift.id) : __('No shift open', 'coffeepos');
                setState(header, activeShift ? 'open' : 'closed');
            } catch (error) {
                label.textContent = __('Shift unavailable', 'coffeepos');
                setState(header, 'error');
            }
        }

        function storedPosSessionId() {
            try {
                return String(window.sessionStorage.getItem(POS_SESSION_STORAGE_KEY) || '');
            } catch (error) {
                return '';
            }
        }

        function rememberPosSessionId(posSessionId) {
            try {
                if (posSessionId) {
                    window.sessionStorage.setItem(POS_SESSION_STORAGE_KEY, String(posSessionId));
                } else {
                    window.sessionStorage.removeItem(POS_SESSION_STORAGE_KEY);
                }
            } catch (error) {
                // The server cart remains authoritative when browser storage is unavailable.
            }
        }

        function setCatalogStatus(status) {
            const scroll = root.querySelector('[data-component="catalog-scroll"]');
            const nav = root.querySelector('[data-component="category-nav"]');
            const loading = root.querySelector('[data-component="catalog-loading"]');
            const empty = root.querySelector('[data-component="catalog-empty"]');
            const error = root.querySelector('[data-component="catalog-error"]');
            store.setCatalogStatus(status);
            setState(scroll, status);
            setState(nav, status);
            loading.hidden = status !== 'loading' && status !== 'refreshing';
            empty.hidden = status !== 'empty';
            error.hidden = status !== 'error';
        }

        async function loadCatalog() {
            if (catalogRequest) {
                catalogRequest.abort();
            }
            catalogRequest = new window.AbortController();
            const sequence = ++catalogSequence;
            setCatalogStatus(store.getState().catalog ? 'refreshing' : 'loading');

            try {
                const data = await api.loadCatalog(catalogRequest.signal);
                if (sequence !== catalogSequence) {
                    return;
                }
                const catalog = data.catalog || { categories: [] };
                catalogRenderer.render(catalog);
                search.setCatalog(catalog);
                store.setCatalog(catalog);
                setCatalogStatus(catalog.categories.length === 0 ? 'empty' : 'normal');
                categoryNav.refresh();
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                setCatalogStatus(store.getState().catalog ? 'normal' : 'error');
                toast.show(error.message || __('The catalog could not be loaded.', 'coffeepos'), 'error');
            }
        }

        function reconcileConflict(error) {
            const latest = error && error.code === 'cart_revision_conflict'
                && error.details && error.details.cart;
            if (!latest) {
                return false;
            }
            applyCart(latest);
            toast.show(error.message || __('The cart changed and was refreshed.', 'coffeepos'), 'warning');
            return true;
        }

        async function mutate(operation) {
            if (mutationPending) {
                throw new Error(__('A cart update is already in progress.', 'coffeepos'));
            }
            mutationPending = true;
            store.setCartStatus('updating');
            cartPanel.setUpdating();
            try {
                const data = await operation();
                applyCart(data.cart);
                return data.cart;
            } catch (error) {
                if (!reconcileConflict(error)) {
                    applyCart(store.getState().cart || { items: [] });
                    toast.show(error.message || __('The cart could not be updated.', 'coffeepos'), 'error');
                }
                throw error;
            } finally {
                mutationPending = false;
            }
        }

        async function createCart() {
            cartPanel.setLoading();
            const existingSessionId = storedPosSessionId();
            try {
                const data = existingSessionId
                    ? await api.getCart(existingSessionId)
                    : await api.createCartSession();
                applyCart(data.cart);
                applyDefaultService(data.cart, !existingSessionId);
            } catch (error) {
                if (existingSessionId && error && error.code === 'cart_session_not_found') {
                    rememberPosSessionId('');
                    try {
                        const replacement = await api.createCartSession();
                        applyCart(replacement.cart);
                        applyDefaultService(replacement.cart, true);
                        return;
                    } catch (replacementError) {
                        error = replacementError;
                    }
                }
                store.setCartStatus('error');
                cartPanel.setError();
                toast.show(error.message || __('The cart could not be loaded.', 'coffeepos'), 'error');
            }
        }

        async function submitProduct(payload, mode, itemId) {
            const cart = store.getState().cart;
            if (!cart) {
                throw new Error(__('The cart is not ready.', 'coffeepos'));
            }
            const request = Object.assign({}, payload, {
                pos_session_id: cart.pos_session_id,
                expected_revision: cart.revision
            });
            if (mode === 'edit') {
                return mutate(function () { return api.updateCartItem(itemId, request); });
            }
            return mutate(function () { return api.addCartItem(request); });
        }

        const productModal = CoffeePOS.components.createProductModalController(
            root,
            renderer,
            api,
            submitProduct,
            function (modalState) { store.setProductModal(modalState); }
        );

        const stockModal = CoffeePOS.components.createStockModalController(root, renderer, api, function () {
            toast.show(__('Stock updated.', 'coffeepos'), 'success');
            syncBridge.publishCatalogInvalidated();
            loadCatalog();
        });

        CoffeePOS.components.createProductCardController(root, function (product) {
            productModal.openAdd(Number(product.productId));
        }, function (product) {
            stockModal.open(product);
        });

        function findItem(itemId) {
            const cart = store.getState().cart;
            return (cart && Array.isArray(cart.items) ? cart.items : []).find(function (item) {
                return String(item.item_id || item.key) === String(itemId);
            }) || null;
        }

        function cartPayload(cart) {
            return { pos_session_id: cart.pos_session_id, expected_revision: cart.revision };
        }

        function renderContext(cart) {
            const customer = cart && cart.customer || { mode: 'guest' };
            const isGuest = customer.mode === 'guest' || customer.is_guest === true;
            const customerSummary = root.querySelector('[data-component="customer-summary"]');
            const customerName = root.querySelector('[data-component="customer-name"]');
            const customerPhone = root.querySelector('[data-component="customer-phone"]');
            const membership = root.querySelector('[data-component="customer-membership"]');
            const openCustomer = root.querySelector('[data-action="open-customer"]');
            const removeCustomer = root.querySelector('[data-action="remove-customer"]');
            const tableLabel = root.querySelector('[data-component="selected-table-label"]');
            const membershipData = customer.membership;

            setState(customerSummary, isGuest ? 'empty' : 'selected');
            customerName.textContent = isGuest ? __('Guest customer', 'coffeepos') : String(customer.display_name || customer.name || __('Customer', 'coffeepos'));
            customerPhone.textContent = String(customer.phone || '');
            customerPhone.hidden = isGuest || customerPhone.textContent === '';
            membership.textContent = membershipData && String(membershipData.status_label || membershipData.tier_label || '');
            membership.hidden = membership.textContent === '';
            openCustomer.hidden = !isGuest;
            removeCustomer.hidden = isGuest;
            orderType.setSelected(cart && cart.order_type || 'takeaway', false);
            tableLabel.textContent = cart && cart.table && cart.table.table_label
                ? String(cart.table.table_label)
                : __('Select table', 'coffeepos');
        }

        function applyCart(cart) {
            if (cart && cart.pos_session_id) {
                rememberPosSessionId(cart.pos_session_id);
            }
            store.setCart(cart);
            cartPanel.render(cart);
            renderContext(cart);
            syncBridge.publishCart(cart);
            if (checkoutController) {
                checkoutController.reconcileCart(cart);
                checkoutController.resumeCart(cart);
            }
            if (heldCarts) {
                heldCarts.setCart(cart);
            }
        }

        function applyDefaultService(cart, isFresh) {
            if (!isFresh || !cart || config.defaultOrderType !== 'dine_in' || cart.order_type === 'dine_in') {
                return;
            }
            if (!requireDineInTable) {
                setServiceContext('dine_in', 0).catch(function () {});
            } else {
                orderType.setSelected('dine_in', false);
                contextController.openTables();
            }
        }

        function acceptCheckoutCart(cart, isFresh) {
            applyCart(cart);
            applyDefaultService(cart, isFresh === true);
        }

        function attachCustomer(customerId) {
            const cart = store.getState().cart;
            return mutate(function () {
                return api.attachCustomer(Object.assign(cartPayload(cart), { customer_id: customerId }));
            });
        }

        async function setServiceContext(requestedOrderType, tableId) {
            const cart = store.getState().cart;
            orderType.setPending(true);
            try {
                return await mutate(function () {
                    return api.setServiceContext(Object.assign(cartPayload(cart), {
                        order_type: requestedOrderType,
                        table_id: tableId
                    }));
                });
            } finally {
                orderType.setPending(false);
            }
        }

        contextController = CoffeePOS.components.createCartContextController(
            root,
            renderer,
            api,
            attachCustomer,
            setServiceContext
        );
        couponSelector = CoffeePOS.components.createCouponSelector(
            root,
            renderer,
            api,
            function () { return store.getState().cart; },
            applyCart
        );
        checkoutController = CoffeePOS.components.createCheckoutController(
            root,
            renderer,
            api,
            function () { return store.getState().cart; },
            acceptCheckoutCart,
            toast,
            function (type, payload) {
                if (type === 'checkout.closed') { syncBridge.returnToCart(); return; }
                syncBridge.publishWorkflow(type, payload);
            }
        );
        heldCarts = CoffeePOS.components.createHeldCartsController(
            root,
            renderer,
            api,
            function () { return store.getState().cart; },
            function (cart, isFresh) {
                applyCart(cart);
                applyDefaultService(cart, isFresh === true);
            },
            confirmDialog,
            toast
        );

        function updateQuantity(item, quantity) {
            const cart = store.getState().cart;
            return mutate(function () {
                return api.updateCartItem(item.item_id, Object.assign(cartPayload(cart), { quantity: quantity }));
            });
        }

        function onClick(event) {
            const trigger = CoffeePOS.core.closestAction(event, root);
            if (!trigger || trigger.disabled) {
                return;
            }
            const action = trigger.getAttribute('data-action') || '';
            const itemId = trigger.getAttribute('data-cart-item-key') || '';
            const item = itemId ? findItem(itemId) : null;
            const cart = store.getState().cart;

            if (action === 'retry-catalog') {
                loadCatalog();
            } else if (action === 'retry-cart') {
                createCart();
            } else if (action === 'clear-cart' && cart && cart.items.length > 0) {
                confirmDialog.open({ title: __('Clear cart?', 'coffeepos'), message: __('Remove all items from the current cart?', 'coffeepos'), action: 'clear-cart' });
            } else if (action === 'increase-quantity' && item) {
                updateQuantity(item, Number(item.quantity) + 1).catch(function () {});
            } else if (action === 'decrease-quantity' && item && Number(item.quantity) > 1) {
                updateQuantity(item, Number(item.quantity) - 1).catch(function () {});
            } else if (action === 'edit-cart-item' && item) {
                productModal.openEdit(item);
            } else if (action === 'remove-cart-item' && item && cart) {
                mutate(function () { return api.removeCartItem(item.item_id, cartPayload(cart)); }).catch(function () {});
            } else if (action === 'open-customer') {
                contextController.openCustomer();
            } else if (action === 'open-table') {
                contextController.openTables();
            } else if (action === 'remove-customer' && cart) {
                mutate(function () { return api.removeCustomer(cartPayload(cart)); }).catch(function () {});
            } else if (action === 'open-coupon') {
                couponSelector.open();
            } else if (action === 'remove-coupon' && cart) {
                mutate(function () { return api.removeCoupon(cartPayload(cart)); }).catch(function () {});
            } else if (action === 'open-order-note') {
                cartPanel.openOrderNote();
            } else if (action === 'open-held-carts') {
                heldCarts.open();
            } else if (action === 'close-order-note') {
                cartPanel.closeOrderNote();
            } else if (action === 'save-order-note' && cart) {
                const input = root.querySelector('[data-component="order-note-input"]');
                cartPanel.setOrderNoteStatus(__('Saving…', 'coffeepos'));
                mutate(function () { return api.setOrderNote(Object.assign(cartPayload(cart), { note: input.value })); })
                    .then(function () { cartPanel.setOrderNoteStatus(__('Saved', 'coffeepos')); })
                    .catch(function () { cartPanel.setOrderNoteStatus(__('Could not save', 'coffeepos')); });
            } else if (action === 'clear-order-note' && cart) {
                cartPanel.setOrderNoteStatus(__('Saving…', 'coffeepos'));
                mutate(function () { return api.clearOrderNote(cartPayload(cart)); })
                    .then(function () { cartPanel.setOrderNoteStatus(__('Cleared', 'coffeepos')); })
                    .catch(function () { cartPanel.setOrderNoteStatus(__('Could not clear', 'coffeepos')); });
            } else if (action === 'checkout' && cart) {
                if (requireOpenShift && !activeShift) {
                    toast.show(__('Open a shift before checkout.', 'coffeepos'), 'error');
                    return;
                }
                checkoutController.open();
            }
        }

        function onConfirm(event) {
            const cart = store.getState().cart;
            if (cart && event.detail && event.detail.action === 'clear-cart') {
                mutate(function () { return api.clearCart(cartPayload(cart)); }).catch(function () {});
            }
        }

        function init() {
            categoryNav.init();
            search.init();
            orderType.init();
            heldCarts.init();
            root.addEventListener('click', onClick);
            root.addEventListener('coffeepos:confirm', onConfirm);
            loadCatalog();
            createCart();
            loadShift();
        }

        return {
            init: init,
            getState: store.getState,
            getRenderer: function () { return renderer; },
            refreshCatalog: loadCatalog
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
