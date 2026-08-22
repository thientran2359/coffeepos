(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};

    CoffeePOS.state = CoffeePOS.state || {};

    CoffeePOS.state.createCashierStore = function () {
        const listeners = [];
        const state = {
            catalog: null,
            catalogStatus: 'loading',
            cart: null,
            cartStatus: 'loading',
            activeCategory: 'all',
            searchTerm: '',
            productModal: {
                status: 'closed',
                mode: 'add',
                productId: 0,
                itemId: ''
            }
        };

        function emit(change) {
            listeners.slice().forEach(function (listener) {
                listener(change, state);
            });
        }

        return {
            getState: function () { return state; },
            subscribe: function (listener) {
                if (typeof listener === 'function') {
                    listeners.push(listener);
                }

                return function () {
                    const index = listeners.indexOf(listener);
                    if (index !== -1) {
                        listeners.splice(index, 1);
                    }
                };
            },
            setCatalogStatus: function (status) {
                state.catalogStatus = status;
                emit('catalog-status');
            },
            setCatalog: function (catalog) {
                state.catalog = catalog;
                state.catalogStatus = 'normal';
                emit('catalog');
            },
            setCartStatus: function (status) {
                state.cartStatus = status;
                emit('cart-status');
            },
            setCart: function (cart) {
                state.cart = cart;
                state.cartStatus = Array.isArray(cart && cart.items) && cart.items.length > 0 ? 'normal' : 'empty';
                emit('cart');
            },
            setActiveCategory: function (categoryId) {
                state.activeCategory = categoryId || 'all';
                emit('active-category');
            },
            setSearchTerm: function (term) {
                state.searchTerm = String(term || '');
                emit('search');
            },
            setProductModal: function (modalState) {
                state.productModal = Object.assign({}, state.productModal, modalState);
                emit('product-modal');
            }
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
