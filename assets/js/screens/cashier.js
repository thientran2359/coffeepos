(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const setState = CoffeePOS.core.setState;
    const POS_SESSION_STORAGE_KEY = 'coffeepos.pos_session_id';

    CoffeePOS.screens = CoffeePOS.screens || {};

    CoffeePOS.screens.createCashierController = function (root) {
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
        const orderType = CoffeePOS.components.createOrderTypeController(root, function (requested) {
            if (mutationPending) {
                return;
            }
            if (requested === 'dine_in') {
                contextController.openTables();
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
            try {
                const data = await api.getCurrentShift();
                activeShift = data.shift || null;
                label.textContent = activeShift ? 'Shift #' + activeShift.id + ' open' : 'No shift open';
                setState(header, activeShift ? 'open' : 'closed');
            } catch (error) {
                label.textContent = 'Shift unavailable';
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
                toast.show(error.message || 'The catalog could not be loaded.', 'error');
            }
        }

        function reconcileConflict(error) {
            const latest = error && error.code === 'cart_revision_conflict'
                && error.details && error.details.cart;
            if (!latest) {
                return false;
            }
            applyCart(latest);
            toast.show(error.message || 'The cart changed and was refreshed.', 'warning');
            return true;
        }

        async function mutate(operation) {
            if (mutationPending) {
                throw new Error('A cart update is already in progress.');
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
                    toast.show(error.message || 'The cart could not be updated.', 'error');
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
            } catch (error) {
                if (existingSessionId && error && error.code === 'cart_session_not_found') {
                    rememberPosSessionId('');
                    try {
                        const replacement = await api.createCartSession();
                        applyCart(replacement.cart);
                        return;
                    } catch (replacementError) {
                        error = replacementError;
                    }
                }
                store.setCartStatus('error');
                cartPanel.setError();
                toast.show(error.message || 'The cart could not be loaded.', 'error');
            }
        }

        async function submitProduct(payload, mode, itemId) {
            const cart = store.getState().cart;
            if (!cart) {
                throw new Error('The cart is not ready.');
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

        CoffeePOS.components.createProductCardController(root, function (product) {
            productModal.openAdd(Number(product.productId));
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
            const removeCustomer = root.querySelector('[data-action="remove-customer"]');
            const tableLabel = root.querySelector('[data-component="selected-table-label"]');
            const membershipData = customer.membership;

            setState(customerSummary, isGuest ? 'empty' : 'selected');
            customerName.textContent = isGuest ? 'Guest customer' : String(customer.display_name || customer.name || 'Customer');
            customerPhone.textContent = String(customer.phone || '');
            customerPhone.hidden = isGuest || customerPhone.textContent === '';
            membership.textContent = membershipData && String(membershipData.status_label || membershipData.tier_label || '');
            membership.hidden = membership.textContent === '';
            removeCustomer.hidden = isGuest;
            orderType.setSelected(cart && cart.order_type || 'takeaway', false);
            tableLabel.textContent = cart && cart.table && cart.table.table_label
                ? String(cart.table.table_label)
                : 'Select table';
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
            applyCart,
            toast,
            function (type, payload) {
                if (type === 'checkout.closed') { syncBridge.returnToCart(); return; }
                syncBridge.publishWorkflow(type, payload);
            }
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
                confirmDialog.open({ title: 'Clear cart?', message: 'Remove all items from the current cart?', action: 'clear-cart' });
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
            } else if (action === 'close-order-note') {
                cartPanel.closeOrderNote();
            } else if (action === 'save-order-note' && cart) {
                const input = root.querySelector('[data-component="order-note-input"]');
                cartPanel.setOrderNoteStatus('Saving…');
                mutate(function () { return api.setOrderNote(Object.assign(cartPayload(cart), { note: input.value })); })
                    .then(function () { cartPanel.setOrderNoteStatus('Saved'); })
                    .catch(function () { cartPanel.setOrderNoteStatus('Could not save'); });
            } else if (action === 'clear-order-note' && cart) {
                cartPanel.setOrderNoteStatus('Saving…');
                mutate(function () { return api.clearOrderNote(cartPayload(cart)); })
                    .then(function () { cartPanel.setOrderNoteStatus('Cleared'); })
                    .catch(function () { cartPanel.setOrderNoteStatus('Could not clear'); });
            } else if (action === 'checkout' && cart) {
                if (!activeShift) {
                    toast.show('Open a shift before checkout.', 'error');
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
