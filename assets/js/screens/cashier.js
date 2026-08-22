(function (window, document) {
    'use strict';

    var CoffeePOS = window.CoffeePOS || {};

    CoffeePOS.screens = CoffeePOS.screens || {};

    CoffeePOS.screens.createCashierController = function (root) {
        var syncCategoryButtons = (CoffeePOS.components && CoffeePOS.components.syncCategoryButtons) || function () {};
        var setSearchState = (CoffeePOS.components && CoffeePOS.components.setSearchState) || function () {};
        var setSearchTerm = (CoffeePOS.components && CoffeePOS.components.setSearchTerm) || function () {};
        var setOrderType = (CoffeePOS.components && CoffeePOS.components.setOrderType) || function () {};
        var setCartState = (CoffeePOS.components && CoffeePOS.components.setCartState) || function () {};
        var createToastController = (CoffeePOS.ui && CoffeePOS.ui.createToastController) || function () {
            return {
                show: function () {}
            };
        };
        var createModalController = (CoffeePOS.ui && CoffeePOS.ui.createModalController) || function () {
            return {
                open: function () {},
                close: function () {},
                getPendingAction: function () {
                    return '';
                }
            };
        };
        var request = (CoffeePOS.core && CoffeePOS.core.request) || function () {
            return Promise.reject(new Error('HTTP client is unavailable.'));
        };
        var getConfig = (CoffeePOS.core && CoffeePOS.core.getConfig) || function () {
            return {
                restBase: ''
            };
        };
        var setState = (CoffeePOS.core && CoffeePOS.core.setState) || function () {};

        var state = {
            activeCategory: 'all',
            searchTerm: '',
            searchDebounceHandle: 0,
            productGridState: 'loading',
            cartPanelState: 'empty',
            orderTypeDisplayState: 'takeaway',
            customerDisplayState: 'empty',
            modalState: 'closed',
            toastState: '',
            selectedProductId: 0,
            pendingModal: null,
            products: [],
            productById: {},
            cart: createEmptyCart('VND')
        };

        var elements = {
            search: root.querySelector('[data-component="product-search"]'),
            searchWrap: root.querySelector('[data-component="search"]'),
            categoryNav: root.querySelector('[data-component="category-nav"]'),
            categoryButtons: root.querySelectorAll('[data-action="select-category"]'),
            productGrid: root.querySelector('[data-component="product-grid"]'),
            productGridEmpty: root.querySelector('[data-component="product-grid-empty"]'),
            productGridLoading: root.querySelector('[data-component="product-grid-loading"]'),
            cartPanel: root.querySelector('[data-component="cart-panel"]'),
            cartCount: root.querySelector('[data-component="cart-count"]'),
            cartItems: root.querySelector('[data-component="cart-items"]'),
            cartItem: root.querySelector('[data-component="cart-item"]'),
            cartEmpty: root.querySelector('[data-component="cart-empty"]'),
            orderTypeButtons: root.querySelectorAll('[data-action="select-order-type"]'),
            tablePlaceholder: root.querySelector('[data-component="table-placeholder"]'),
            modal: root.querySelector('[data-component="modal"]'),
            modalTitle: root.querySelector('[data-component="modal-title"]'),
            modalMessage: root.querySelector('[data-component="modal-message"]'),
            summary: root.querySelector('[data-component="cart-summary"]')
        };

        var toast = createToastController(root);
        var modal = createModalController(elements, state);
        var featureMessages = {
            'open-customer': 'Customer search flow will be implemented in a later phase.',
            'open-coupon': 'Coupon validation flow will be implemented in a later phase.',
            'checkout': 'Checkout flow is not enabled in Phase 03.',
            'open-held-carts': 'Held carts flow will be implemented in a later phase.',
            'open-customer-display': 'Customer display control will be implemented in a later phase.',
            'open-shift': 'Shift workflow will be implemented in a later phase.',
            'open-settings': 'Settings panel will be implemented in a later phase.'
        };

        function createEmptyCart(currency) {
            return {
                order_type: 'takeaway',
                table: {
                    table_id: null,
                    table_label: ''
                },
                customer: {
                    is_guest: true,
                    customer_id: null,
                    phone: '',
                    display_name: ''
                },
                payment: {
                    coupon_code: null
                },
                items: [],
                total_quantity: 0,
                subtotal_minor: 0,
                currency: currency || 'VND'
            };
        }

        function endpoint(path, queryParams) {
            var config = getConfig();
            var base = (config.restBase || '/wp-json/coffeepos/v1/').replace(/\/+$/, '') + '/';
            var normalizedPath = String(path || '').replace(/^\/+/, '');
            var url = base + normalizedPath;

            if (!queryParams) {
                return url;
            }

            var searchParams = new window.URLSearchParams();

            Object.keys(queryParams).forEach(function (key) {
                var value = queryParams[key];

                if (value === null || value === undefined || value === '') {
                    return;
                }

                searchParams.append(key, String(value));
            });

            var query = searchParams.toString();

            return query === '' ? url : (url + '?' + query);
        }

        function formatMoney(amountMinor, currency) {
            var amount = Number(amountMinor || 0);
            var normalizedCurrency = String(currency || state.cart.currency || 'VND').toUpperCase();

            if (window.Intl && window.Intl.NumberFormat) {
                try {
                    var divisor = normalizedCurrency === 'VND' ? 1 : 100;
                    return new window.Intl.NumberFormat('vi-VN', {
                        style: 'currency',
                        currency: normalizedCurrency,
                        maximumFractionDigits: normalizedCurrency === 'VND' ? 0 : 2
                    }).format(amount / divisor);
                } catch (error) {
                    return amount.toLocaleString('vi-VN') + ' ' + normalizedCurrency;
                }
            }

            return amount + ' ' + normalizedCurrency;
        }

        function openModal(title, message, pendingModal) {
            state.pendingModal = pendingModal || null;
            modal.open(title, message, 'phase03-modal');
        }

        function closeModal() {
            state.pendingModal = null;
            modal.close();
        }

        function notifyPending(action) {
            var message = featureMessages[action] || ('Action "' + action + '" is not available in this phase.');
            toast.show(message, 'info');
            state.toastState = 'info';
        }

        function setActiveCategory(categoryId) {
            state.activeCategory = categoryId || 'all';
            syncCategoryButtons(elements.categoryButtons, state.activeCategory);
        }

        function setCart(cart) {
            state.cart = normalizeCart(cart);
            renderCart();
        }

        function normalizeCart(cart) {
            var payload = cart && typeof cart === 'object' ? cart : {};
            var currency = String(payload.currency || state.cart.currency || 'VND').toUpperCase();

            return {
                order_type: payload.order_type || 'takeaway',
                table: payload.table || {
                    table_id: null,
                    table_label: ''
                },
                customer: payload.customer || {
                    is_guest: true,
                    customer_id: null,
                    phone: '',
                    display_name: ''
                },
                payment: payload.payment || {
                    coupon_code: null
                },
                items: Array.isArray(payload.items) ? payload.items : [],
                total_quantity: Number(payload.total_quantity || 0),
                subtotal_minor: Number(payload.subtotal_minor || 0),
                currency: currency
            };
        }

        function renderCategoryButtons(categories) {
            if (!elements.categoryNav) {
                return;
            }

            var nextCategories = Array.isArray(categories) ? categories : [];
            elements.categoryNav.innerHTML = '';
            elements.categoryNav.appendChild(buildCategoryButton('all', 'All'));

            nextCategories.forEach(function (category) {
                if (!category || typeof category !== 'object') {
                    return;
                }

                var categoryId = String(category.id || '');
                var categoryName = String(category.name || '').trim();

                if (categoryId === '' || categoryName === '') {
                    return;
                }

                elements.categoryNav.appendChild(buildCategoryButton(categoryId, categoryName));
            });

            elements.categoryButtons = root.querySelectorAll('[data-action="select-category"]');
            setActiveCategory(state.activeCategory);
        }

        function buildCategoryButton(categoryId, title) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'coffeepos-chip';
            button.setAttribute('data-action', 'select-category');
            button.setAttribute('data-category-id', categoryId);
            button.setAttribute('aria-pressed', 'false');
            button.textContent = title;

            return button;
        }

        function clearProductCards() {
            if (!elements.productGrid) {
                return;
            }

            var cards = elements.productGrid.querySelectorAll('[data-component="product-card"]');
            Array.prototype.forEach.call(cards, function (card) {
                if (card.parentNode === elements.productGrid) {
                    elements.productGrid.removeChild(card);
                }
            });
        }

        function renderProducts(products) {
            var items = Array.isArray(products) ? products : [];
            state.products = items;
            state.productById = {};

            items.forEach(function (item) {
                state.productById[String(item.id)] = item;
            });

            clearProductCards();

            if (!elements.productGrid) {
                return;
            }

            var referenceNode = elements.productGridEmpty || elements.productGridLoading || null;

            items.forEach(function (product) {
                var article = document.createElement('article');
                var stateName = product.is_in_stock ? (Number(product.id) === Number(state.selectedProductId) ? 'selected' : 'normal') : 'out_of_stock';

                article.className = 'coffeepos-product-card';
                article.setAttribute('data-component', 'product-card');
                article.setAttribute('data-product-id', String(product.id));
                article.setAttribute('data-state', stateName);

                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'coffeepos-product-card-button';
                button.setAttribute('data-action', 'select-product');

                if (!product.is_in_stock) {
                    button.disabled = true;
                }

                var name = document.createElement('span');
                name.className = 'coffeepos-product-name';
                name.textContent = String(product.name || 'Unnamed product');

                var price = document.createElement('span');
                price.className = 'coffeepos-product-price';
                price.textContent = formatMoney(product.price_minor, product.currency);

                var meta = document.createElement('span');
                meta.className = 'coffeepos-product-meta';

                if (!product.is_in_stock) {
                    meta.textContent = 'Out of stock';
                } else if (product.is_variable) {
                    meta.textContent = 'Variation available';
                } else {
                    meta.textContent = 'In stock';
                }

                button.appendChild(name);
                button.appendChild(price);
                button.appendChild(meta);
                article.appendChild(button);

                if (referenceNode && referenceNode.parentNode === elements.productGrid) {
                    elements.productGrid.insertBefore(article, referenceNode);
                } else {
                    elements.productGrid.appendChild(article);
                }
            });

            if (elements.productGridEmpty) {
                elements.productGridEmpty.hidden = items.length !== 0;
            }

            setState(elements.productGrid, items.length === 0 ? 'empty' : 'normal');
            state.productGridState = items.length === 0 ? 'empty' : 'normal';
        }

        function setProductLoading(isLoading) {
            if (!elements.productGridLoading) {
                return;
            }

            elements.productGridLoading.hidden = !isLoading;

            if (isLoading) {
                setState(elements.productGrid, 'loading');
                state.productGridState = 'loading';
            }
        }

        function removeGeneratedCartRows() {
            if (!elements.cartItems) {
                return;
            }

            var rows = elements.cartItems.querySelectorAll('[data-component="cart-item"][data-generated="true"]');

            Array.prototype.forEach.call(rows, function (row) {
                row.parentNode.removeChild(row);
            });
        }

        function summarizeModifiers(modifiers) {
            if (!modifiers || typeof modifiers !== 'object') {
                return '';
            }

            var parts = [];

            Object.keys(modifiers).forEach(function (groupName) {
                var values = modifiers[groupName];

                if (!Array.isArray(values) || values.length === 0) {
                    return;
                }

                parts.push(groupName + ': ' + values.join(', '));
            });

            return parts.join(' · ');
        }

        function fillCartRow(row, item) {
            row.hidden = false;
            row.setAttribute('data-item-id', String(item.item_id || ''));

            var product = state.productById[String(item.product_id)] || null;
            var productName = product ? String(product.name || '') : ('Product #' + String(item.product_id || '0'));

            var title = row.querySelector('.coffeepos-cart-item-main strong');
            if (title) {
                title.textContent = productName;
            }

            var descriptionLines = row.querySelectorAll('.coffeepos-cart-item-main p');
            var firstLineParts = [];

            if (Number(item.variation_id || 0) > 0) {
                firstLineParts.push('Variation #' + String(item.variation_id));
            }

            var modifierSummary = summarizeModifiers(item.modifiers || {});

            if (modifierSummary !== '') {
                firstLineParts.push(modifierSummary);
            }

            if (Array.isArray(item.quick_notes) && item.quick_notes.length > 0) {
                firstLineParts.push(item.quick_notes.join(', '));
            }

            if (descriptionLines[0]) {
                descriptionLines[0].textContent = firstLineParts.length > 0 ? firstLineParts.join(' · ') : 'Default configuration';
            }

            if (descriptionLines[1]) {
                var note = String(item.custom_note || '').trim();
                descriptionLines[1].textContent = note === '' ? 'Note: -' : ('Note: ' + note);
            }

            var quantity = row.querySelector('[data-component="cart-item-quantity"]');
            if (quantity) {
                quantity.textContent = String(item.quantity || 0);
            }

            var price = row.querySelector('.coffeepos-cart-item-price span');
            if (price) {
                price.textContent = formatMoney(item.line_total_minor, item.currency || state.cart.currency);
            }

            var actionButtons = row.querySelectorAll('[data-action]');
            Array.prototype.forEach.call(actionButtons, function (button) {
                button.setAttribute('data-item-id', String(item.item_id || ''));
            });
        }

        function renderCart() {
            var items = Array.isArray(state.cart.items) ? state.cart.items : [];

            removeGeneratedCartRows();

            if (elements.cartCount) {
                elements.cartCount.textContent = String(state.cart.total_quantity || 0);
            }

            if (!elements.cartItem || !elements.cartItems) {
                return;
            }

            if (items.length === 0) {
                setCartState(state, elements, 'empty');
                elements.cartItem.hidden = true;

                if (elements.cartEmpty) {
                    elements.cartEmpty.hidden = false;
                }

                renderSummary();
                return;
            }

            setCartState(state, elements, 'normal');
            if (elements.cartEmpty) {
                elements.cartEmpty.hidden = true;
            }

            fillCartRow(elements.cartItem, items[0]);
            elements.cartItem.removeAttribute('data-generated');

            for (var index = 1; index < items.length; index += 1) {
                var row = elements.cartItem.cloneNode(true);
                row.setAttribute('data-generated', 'true');
                fillCartRow(row, items[index]);
                elements.cartItems.appendChild(row);
            }

            renderSummary();
        }

        function renderSummary() {
            if (!elements.summary) {
                return;
            }

            var values = elements.summary.querySelectorAll('strong');
            var subtotal = Number(state.cart.subtotal_minor || 0);

            if (values[0]) {
                values[0].textContent = formatMoney(subtotal, state.cart.currency);
            }

            if (values[1]) {
                values[1].textContent = formatMoney(0, state.cart.currency);
            }

            if (values[2]) {
                values[2].textContent = formatMoney(subtotal, state.cart.currency);
            }
        }

        function postJson(url, payload, method) {
            return request(url, {
                method: method || 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload || {})
            });
        }

        function loadCategories() {
            return request(endpoint('categories', {
                page: 1,
                per_page: 50
            })).then(function (response) {
                var items = response && response.data && Array.isArray(response.data.items)
                    ? response.data.items
                    : [];

                renderCategoryButtons(items);
            }).catch(function () {
                setActiveCategory('all');
            });
        }

        function loadProducts() {
            setProductLoading(true);
            setSearchState(state, elements, 'loading');

            var query = {
                page: 1,
                per_page: 30,
                search: state.searchTerm
            };

            if (state.activeCategory !== 'all') {
                query.category = state.activeCategory;
            }

            return request(endpoint('products', query)).then(function (response) {
                var items = response && response.data && Array.isArray(response.data.items)
                    ? response.data.items
                    : [];

                renderProducts(items);
                setSearchState(state, elements, state.searchTerm === '' ? 'empty' : 'normal');
            }).catch(function (error) {
                renderProducts([]);
                setState(elements.productGrid, 'error');
                state.productGridState = 'error';
                toast.show(error && error.message ? error.message : 'Unable to load products.', 'error');
                state.toastState = 'error';
            }).finally(function () {
                setProductLoading(false);
            });
        }

        function validateCart() {
            return postJson(endpoint('cart/validate'), {
                cart: state.cart
            }).then(function (response) {
                setCart(response && response.data ? response.data.cart : createEmptyCart(state.cart.currency));
            }).catch(function (error) {
                toast.show(error && error.message ? error.message : 'Unable to validate cart.', 'error');
                state.toastState = 'error';
            });
        }

        function addItem(itemPayload) {
            return postJson(endpoint('cart/items'), {
                cart: state.cart,
                item: itemPayload
            }).then(function (response) {
                setCart(response && response.data ? response.data.cart : state.cart);
                toast.show('Item added to cart.', 'success');
                state.toastState = 'success';
            }).catch(function (error) {
                toast.show(error && error.message ? error.message : 'Unable to add item.', 'error');
                state.toastState = 'error';
            });
        }

        function updateItemQuantity(itemId, quantity) {
            return postJson(endpoint('cart/items/' + itemId), {
                cart: state.cart,
                quantity: quantity
            }, 'POST').then(function (response) {
                setCart(response && response.data ? response.data.cart : state.cart);
            }).catch(function (error) {
                toast.show(error && error.message ? error.message : 'Unable to update quantity.', 'error');
                state.toastState = 'error';
            });
        }

        function replaceItem(itemId, itemPayload) {
            return postJson(endpoint('cart/items/' + itemId), {
                cart: state.cart,
                item: itemPayload
            }, 'POST').then(function (response) {
                setCart(response && response.data ? response.data.cart : state.cart);
                toast.show('Cart item updated.', 'success');
                state.toastState = 'success';
            }).catch(function (error) {
                toast.show(error && error.message ? error.message : 'Unable to update item.', 'error');
                state.toastState = 'error';
            });
        }

        function removeItem(itemId) {
            return postJson(endpoint('cart/items/' + itemId), {
                cart: state.cart
            }, 'DELETE').then(function (response) {
                setCart(response && response.data ? response.data.cart : state.cart);
            }).catch(function (error) {
                toast.show(error && error.message ? error.message : 'Unable to remove item.', 'error');
                state.toastState = 'error';
            });
        }

        function clearCart() {
            return postJson(endpoint('cart'), {
                cart: state.cart
            }, 'DELETE').then(function (response) {
                setCart(response && response.data ? response.data.cart : createEmptyCart(state.cart.currency));
                toast.show('Cart cleared.', 'success');
                state.toastState = 'success';
            }).catch(function (error) {
                toast.show(error && error.message ? error.message : 'Unable to clear cart.', 'error');
                state.toastState = 'error';
            });
        }

        function selectProduct(productId) {
            var product = state.productById[String(productId)];

            if (!product) {
                return;
            }

            if (!product.is_in_stock) {
                toast.show('Selected product is out of stock.', 'error');
                state.toastState = 'error';
                return;
            }

            state.selectedProductId = Number(product.id || 0);
            renderProducts(state.products);

            request(endpoint('products/' + String(product.id))).then(function (response) {
                var detail = response && response.data ? response.data : {};
                var variations = Array.isArray(detail.variations) ? detail.variations : [];

                if (product.is_variable) {
                    if (variations.length === 0) {
                        toast.show('No available variation found for this product.', 'error');
                        state.toastState = 'error';
                        return;
                    }

                    var chosenVariation = variations[0];
                    var message = 'Variation selection is loaded. Confirm to add the default available variation now.';

                    openModal('Configure product', message, {
                        type: 'add-product',
                        item: {
                            product_id: product.id,
                            variation_id: chosenVariation.id,
                            selected_attributes: chosenVariation.attributes || {},
                            quantity: 1,
                            modifiers: {},
                            quick_notes: [],
                            custom_note: ''
                        }
                    });

                    return;
                }

                addItem({
                    product_id: product.id,
                    quantity: 1,
                    modifiers: {},
                    quick_notes: [],
                    custom_note: ''
                });
            }).catch(function (error) {
                toast.show(error && error.message ? error.message : 'Unable to load product detail.', 'error');
                state.toastState = 'error';
            });
        }

        function handleOrderTypeSelection(trigger) {
            var nextOrderType = (trigger && trigger.getAttribute('data-order-type')) || 'takeaway';
            var previousOrderType = state.cart.order_type;

            setOrderType(state, elements, nextOrderType);

            state.cart.order_type = nextOrderType === 'dine_in' ? 'dine_in' : 'takeaway';

            if (state.cart.order_type === 'dine_in') {
                state.cart.table = {
                    table_id: 1,
                    table_label: 'Table 1'
                };
            } else {
                state.cart.table = {
                    table_id: null,
                    table_label: ''
                };
            }

            validateCart().catch(function () {
                state.cart.order_type = previousOrderType;
                setOrderType(state, elements, previousOrderType);
            });
        }

        function getCartItemById(itemId) {
            var items = Array.isArray(state.cart.items) ? state.cart.items : [];

            for (var index = 0; index < items.length; index += 1) {
                if (String(items[index].item_id) === String(itemId)) {
                    return items[index];
                }
            }

            return null;
        }

        function onAction(action, trigger) {
            var itemId;
            var item;

            switch (action) {
                case 'search-products':
                    if (elements.search) {
                        elements.search.focus();
                    }
                    return;

                case 'clear-search':
                    setSearchTerm(state, elements, '');
                    setSearchState(state, elements, 'empty');
                    loadProducts();
                    return;

                case 'select-category':
                    setActiveCategory((trigger && trigger.getAttribute('data-category-id')) || 'all');
                    loadProducts();
                    return;

                case 'select-order-type':
                    handleOrderTypeSelection(trigger);
                    return;

                case 'select-product':
                    selectProduct((trigger && trigger.closest('[data-product-id]') && trigger.closest('[data-product-id]').getAttribute('data-product-id')) || 0);
                    return;

                case 'clear-cart':
                    openModal('Clear cart', 'Do you want to clear all cart items?', {
                        type: 'clear-cart'
                    });
                    return;

                case 'confirm-modal':
                    if (state.pendingModal && state.pendingModal.type === 'clear-cart') {
                        clearCart();
                    }

                    if (state.pendingModal && state.pendingModal.type === 'add-product') {
                        addItem(state.pendingModal.item || {});
                    }

                    if (state.pendingModal && state.pendingModal.type === 'edit-item') {
                        replaceItem(state.pendingModal.itemId, state.pendingModal.item || {});
                    }

                    closeModal();
                    return;

                case 'cancel-modal':
                    closeModal();
                    return;

                case 'increase-quantity':
                    itemId = trigger ? trigger.getAttribute('data-item-id') : '';
                    item = getCartItemById(itemId);

                    if (item) {
                        updateItemQuantity(itemId, Number(item.quantity || 0) + 1);
                    }
                    return;

                case 'decrease-quantity':
                    itemId = trigger ? trigger.getAttribute('data-item-id') : '';
                    item = getCartItemById(itemId);

                    if (!item) {
                        return;
                    }

                    if (Number(item.quantity || 0) <= 1) {
                        removeItem(itemId);
                        return;
                    }

                    updateItemQuantity(itemId, Number(item.quantity || 0) - 1);
                    return;

                case 'remove-cart-item':
                    itemId = trigger ? trigger.getAttribute('data-item-id') : '';
                    removeItem(itemId);
                    return;

                case 'edit-cart-item':
                    itemId = trigger ? trigger.getAttribute('data-item-id') : '';
                    item = getCartItemById(itemId);

                    if (!item) {
                        return;
                    }

                    openModal('Edit cart item', 'Confirm to increase item quantity by 1 for edit preview.', {
                        type: 'edit-item',
                        itemId: itemId,
                        item: {
                            product_id: item.product_id,
                            variation_id: item.variation_id,
                            quantity: Number(item.quantity || 0) + 1,
                            modifiers: item.modifiers || {},
                            quick_notes: item.quick_notes || [],
                            custom_note: item.custom_note || ''
                        }
                    });
                    return;

                default:
                    notifyPending(action);
            }
        }

        function onClick(event) {
            var actionElement = event.target.closest('[data-action]');

            if (!actionElement || !root.contains(actionElement)) {
                return;
            }

            var action = actionElement.getAttribute('data-action') || '';

            if (!action) {
                return;
            }

            onAction(action, actionElement);
        }

        function onSearchInput(event) {
            setSearchTerm(state, elements, event.target.value || '');

            if (state.searchDebounceHandle) {
                window.clearTimeout(state.searchDebounceHandle);
            }

            state.searchDebounceHandle = window.setTimeout(function () {
                loadProducts();
            }, 300);
        }

        function init() {
            root.addEventListener('click', onClick);

            if (elements.search) {
                elements.search.addEventListener('input', onSearchInput);
            }

            setActiveCategory('all');
            setOrderType(state, elements, 'takeaway');
            setSearchState(state, elements, 'empty');
            setCart(createEmptyCart('VND'));

            loadCategories();
            loadProducts();
            validateCart();
        }

        return {
            init: init,
            getState: function () {
                return {
                    activeCategory: state.activeCategory,
                    searchTerm: state.searchTerm,
                    productGridState: state.productGridState,
                    cartPanelState: state.cartPanelState,
                    orderTypeDisplayState: state.orderTypeDisplayState,
                    modalState: state.modalState,
                    toastState: state.toastState
                };
            }
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window, document));
