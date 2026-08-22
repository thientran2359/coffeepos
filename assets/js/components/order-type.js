(function (window) {
    'use strict';

    var CoffeePOS = window.CoffeePOS || {};
    var setState = (CoffeePOS.core && CoffeePOS.core.setState) || function () {};

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.setOrderType = function (state, elements, orderType) {
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
    };

    window.CoffeePOS = CoffeePOS;
}(window));
