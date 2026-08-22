(function () {
    'use strict';

    function getConfig() {
        if (typeof window.CoffeePOSConfig !== 'object' || window.CoffeePOSConfig === null) {
            return {
                screen: '',
                restBase: '',
                restNonce: ''
            };
        }

        return window.CoffeePOSConfig;
    }

    function setState(element, state) {
        if (!element) {
            return;
        }

        element.setAttribute('data-state', state);
    }

    function createToastController(root) {
        var region = root.querySelector('[data-component="toast-region"]');

        function show(message, type) {
            if (!region || !message) {
                return;
            }

            var toast = document.createElement('div');
            toast.className = 'coffeepos-toast is-' + (type || 'info');
            toast.textContent = message;
            region.appendChild(toast);

            window.setTimeout(function () {
                if (toast.parentNode === region) {
                    region.removeChild(toast);
                }
            }, 2600);
        }

        return {
            show: show
        };
    }

    function createCashierController(root) {
        var state = {
            activeCategory: 'all',
            searchTerm: '',
            isSearching: false,
            productGridState: 'normal',
            cartPanelState: 'empty',
            orderTypeDisplayState: 'takeaway',
            customerDisplayState: 'empty',
            modalState: 'closed',
            toastState: ''
        };

        var elements = {
            search: root.querySelector('[data-component="product-search"]'),
            searchWrap: root.querySelector('[data-component="search"]'),
            categoryNav: root.querySelector('[data-component="category-nav"]'),
            categoryButtons: root.querySelectorAll('[data-action="select-category"]'),
            productGrid: root.querySelector('[data-component="product-grid"]'),
            productCards: root.querySelectorAll('[data-component="product-card"]'),
            cartPanel: root.querySelector('[data-component="cart-panel"]'),
            cartCount: root.querySelector('[data-component="cart-count"]'),
            cartItems: root.querySelector('[data-component="cart-items"]'),
            cartItem: root.querySelector('[data-component="cart-item"]'),
            cartEmpty: root.querySelector('[data-component="cart-empty"]'),
            orderTypeButtons: root.querySelectorAll('[data-action="select-order-type"]'),
            tablePlaceholder: root.querySelector('[data-component="table-placeholder"]'),
            modal: root.querySelector('[data-component="modal"]'),
            modalTitle: root.querySelector('[data-component="modal-title"]'),
            modalMessage: root.querySelector('[data-component="modal-message"]')
        };

        var toast = createToastController(root);
        var pendingAction = '';
        var featureMessages = {
            'select-product': 'Product selection business flow will be implemented in Phase 03.',
            'open-customer': 'Customer search flow will be implemented in a later phase.',
            'open-coupon': 'Coupon validation flow will be implemented in a later phase.',
            'increase-quantity': 'Cart quantity mutation is not enabled in Phase 02.',
            'decrease-quantity': 'Cart quantity mutation is not enabled in Phase 02.',
            'edit-cart-item': 'Cart item editing is not enabled in Phase 02.',
            'remove-cart-item': 'Cart item removal is not enabled in Phase 02.',
            'checkout': 'Checkout flow is not enabled in Phase 02.',
            'open-held-carts': 'Held carts flow will be implemented in a later phase.',
            'open-customer-display': 'Customer display control will be implemented in a later phase.',
            'open-shift': 'Shift workflow will be implemented in a later phase.',
            'open-settings': 'Settings panel will be implemented in a later phase.'
        };

        function syncCategoryButtons() {
            Array.prototype.forEach.call(elements.categoryButtons, function (button) {
                var categoryId = button.getAttribute('data-category-id') || '';
                var isActive = categoryId === state.activeCategory;

                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                button.classList.toggle('is-active', isActive);
            });
        }

        function setActiveCategory(categoryId) {
            state.activeCategory = categoryId || 'all';
            syncCategoryButtons();
        }

        function setSearchState(nextState) {
            setState(elements.searchWrap, nextState);
            state.productGridState = nextState === 'loading' ? 'loading' : (state.searchTerm ? 'normal' : 'normal');
            setState(elements.productGrid, state.productGridState);
        }

        function setSearchTerm(value) {
            state.searchTerm = (value || '').trim();

            if (elements.search && elements.search.value !== state.searchTerm) {
                elements.search.value = state.searchTerm;
            }
        }

        function setOrderType(orderType) {
            var nextOrderType = orderType === 'dine_in' ? 'dine_in' : 'takeaway';
            state.orderTypeDisplayState = nextOrderType;

            Array.prototype.forEach.call(elements.orderTypeButtons, function (button) {
                var buttonType = button.getAttribute('data-order-type') || '';
                var isActive = buttonType === nextOrderType;

                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                button.classList.toggle('is-active', isActive);
            });

            if (elements.tablePlaceholder) {
                var showTable = nextOrderType === 'dine_in';
                elements.tablePlaceholder.hidden = !showTable;
                setState(elements.tablePlaceholder, showTable ? 'visible' : 'hidden');
            }
        }

        function setCartState(nextState) {
            state.cartPanelState = nextState;
            setState(elements.cartPanel, nextState);
            setState(elements.cartItems, nextState);

            if (nextState === 'empty') {
                if (elements.cartItem) {
                    elements.cartItem.hidden = true;
                }

                if (elements.cartEmpty) {
                    elements.cartEmpty.hidden = false;
                }

                if (elements.cartCount) {
                    elements.cartCount.textContent = '0';
                }

                return;
            }

            if (elements.cartItem) {
                elements.cartItem.hidden = false;
            }

            if (elements.cartEmpty) {
                elements.cartEmpty.hidden = true;
            }

            if (elements.cartCount) {
                elements.cartCount.textContent = '1';
            }
        }

        function openModal(title, message, action) {
            if (!elements.modal) {
                return;
            }

            pendingAction = action || '';
            elements.modal.hidden = false;
            setState(elements.modal, 'open');
            state.modalState = 'open';

            if (elements.modalTitle) {
                elements.modalTitle.textContent = title;
            }

            if (elements.modalMessage) {
                elements.modalMessage.textContent = message;
            }
        }

        function closeModal() {
            pendingAction = '';

            if (!elements.modal) {
                return;
            }

            elements.modal.hidden = true;
            setState(elements.modal, 'closed');
            state.modalState = 'closed';
        }

        function notifyPending(action) {
            var message = featureMessages[action] || ('Action "' + action + '" is not available in Phase 02.');
            toast.show(message, 'info');
            state.toastState = 'info';
        }

        function onAction(action, trigger) {
            switch (action) {
                case 'search-products':
                    if (elements.search) {
                        elements.search.focus();
                        setSearchState('loading');

                        window.setTimeout(function () {
                            setSearchState('normal');
                        }, 150);
                    }
                    return;

                case 'clear-search':
                    setSearchTerm('');
                    setSearchState('empty');
                    return;

                case 'select-category':
                    setActiveCategory((trigger && trigger.getAttribute('data-category-id')) || 'all');
                    return;

                case 'select-order-type':
                    setOrderType((trigger && trigger.getAttribute('data-order-type')) || 'takeaway');
                    return;

                case 'clear-cart':
                    openModal('Clear cart', 'This is a Phase 02 confirmation preview only.', 'clear-cart');
                    return;

                case 'confirm-modal':
                    if (pendingAction === 'clear-cart') {
                        setCartState('empty');
                        toast.show('Cart clear preview confirmed. Domain mutation will arrive later.', 'success');
                        state.toastState = 'success';
                    }

                    closeModal();
                    return;

                case 'cancel-modal':
                    closeModal();
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
            setSearchTerm(event.target.value || '');

            if (!state.searchTerm) {
                setSearchState('empty');
                return;
            }

            setSearchState('normal');
        }

        function init() {
            root.addEventListener('click', onClick);

            if (elements.search) {
                elements.search.addEventListener('input', onSearchInput);
            }

            setActiveCategory('all');
            setOrderType('takeaway');
            setCartState('empty');
            setSearchState('empty');
        }

        return {
            init: init,
            getState: function () {
                return state;
            }
        };
    }

    function bootstrap() {
        var root = document.getElementById('coffeepos-app');

        if (!root) {
            return;
        }

        var config = getConfig();

        if (config.screen) {
            root.setAttribute('data-coffeepos-screen', config.screen);
        }

        if (!root.getAttribute('data-screen') && config.screen) {
            root.setAttribute('data-screen', config.screen);
        }

        if (config.restBase) {
            root.setAttribute('data-coffeepos-rest-base', config.restBase);
        }

        var screen = root.getAttribute('data-screen') || root.getAttribute('data-coffeepos-screen') || config.screen || '';
        var screenController = null;

        if (screen === 'cashier') {
            screenController = createCashierController(root);
            screenController.init();
        }

        window.dispatchEvent(new CustomEvent('coffeepos:ready', {
            detail: {
                screen: screen,
                uiState: screenController ? screenController.getState() : null
            }
        }));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrap);
        return;
    }

    bootstrap();
}());
