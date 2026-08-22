(function (window) {
    'use strict';

    var CoffeePOS = window.CoffeePOS || {};
    var setState = (CoffeePOS.core && CoffeePOS.core.setState) || function () {};

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.setCartState = function (state, elements, nextState) {
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
    };

    window.CoffeePOS = CoffeePOS;
}(window));
