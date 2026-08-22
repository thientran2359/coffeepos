(function (window) {
    'use strict';

    var CoffeePOS = window.CoffeePOS || {};
    var setState = (CoffeePOS.core && CoffeePOS.core.setState) || function () {};

    CoffeePOS.ui = CoffeePOS.ui || {};

    CoffeePOS.ui.createModalController = function (elements, state) {
        var pendingAction = '';

        function open(title, message, action) {
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

        function close() {
            pendingAction = '';

            if (!elements.modal) {
                return;
            }

            elements.modal.hidden = true;
            setState(elements.modal, 'closed');
            state.modalState = 'closed';
        }

        function getPendingAction() {
            return pendingAction;
        }

        return {
            open: open,
            close: close,
            getPendingAction: getPendingAction
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
