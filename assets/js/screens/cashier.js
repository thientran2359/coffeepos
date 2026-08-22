(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};

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
        const toast = CoffeePOS.ui.createToastController(root, renderer);
        const modal = CoffeePOS.ui.createModalController(root);
        const confirmDialog = CoffeePOS.ui.createConfirmDialogController(root);
        const state = {
            activeCategory: 'all',
            searchTerm: '',
            isSearching: false,
            catalogState: 'normal',
            searchResultsState: 'idle',
            cartPanelState: 'empty',
            orderTypeDisplayState: 'takeaway',
            customerDisplayState: 'empty',
            modalState: 'closed',
            toastState: '',
            selectedProductId: ''
        };

        const categoryNav = CoffeePOS.components.createCategoryNavController(root, function (categoryId) {
            state.activeCategory = categoryId;
        });
        const search = CoffeePOS.components.createProductSearchController(root, function (term, resultsState) {
            state.searchTerm = term;
            state.isSearching = resultsState === 'loading';
            state.searchResultsState = resultsState;
        });
        const productCards = CoffeePOS.components.createProductCardController(root, function (product) {
            state.selectedProductId = product.productId;
            toast.show('Product selected.', 'info');
            state.toastState = 'info';
        });
        const orderType = CoffeePOS.components.createOrderTypeController(root, function (value) {
            state.orderTypeDisplayState = value;
        });

        function openFoundationModal(title, message, action) {
            modal.open({ title: title, message: message, action: action, state: 'idle' });
            state.modalState = 'open';
        }

        function notifyUnavailable() {
            toast.show('This action is not available yet.', 'info');
            state.toastState = 'info';
        }

        function onClick(event) {
            const trigger = CoffeePOS.core.closestAction(event, root);

            if (!trigger || trigger.disabled) {
                return;
            }

            const action = trigger.getAttribute('data-action') || '';

            switch (action) {
                case 'clear-cart':
                    confirmDialog.open({
                        title: 'Clear cart?',
                        message: 'Remove all items from the current cart?',
                        action: 'clear-cart'
                    });
                    return;

                case 'open-customer':
                    openFoundationModal('Find customer', 'No customer selected.', 'open-customer');
                    return;

                case 'open-coupon':
                    openFoundationModal('Add coupon', 'No coupon selected.', 'open-coupon');
                    return;

                case 'open-table':
                    openFoundationModal('Select table', 'No table selected.', 'open-table');
                    return;

                case 'retry-catalog':
                case 'retry-cart':
                case 'remove-coupon':
                case 'checkout':
                case 'increase-quantity':
                case 'decrease-quantity':
                case 'edit-cart-item':
                case 'remove-cart-item':
                case 'locate-product':
                    notifyUnavailable();
                    return;

                default:
                    return;
            }
        }

        function onConfirm(event) {
            if (event.detail && event.detail.action === 'clear-cart') {
                toast.show('Cart is already empty.', 'success');
                state.cartPanelState = 'empty';
                state.toastState = 'success';
            }
        }

        function onModalConfirm() {
            modal.close();
            notifyUnavailable();
        }

        function onModalClose() {
            state.modalState = 'closed';
        }

        function init() {
            categoryNav.init();
            search.init();
            orderType.init();
            root.addEventListener('click', onClick);
            root.addEventListener('coffeepos:confirm', onConfirm);
            root.addEventListener('coffeepos:modal-confirm', onModalConfirm);
            root.addEventListener('coffeepos:modal-close', onModalClose);
        }

        return {
            init: init,
            getState: function () { return Object.assign({}, state); },
            getRenderer: function () { return renderer; },
            getSelectedProductId: productCards.getSelectedProductId,
            setActiveCategory: categoryNav.setActive,
            setOrderType: orderType.setSelected
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
