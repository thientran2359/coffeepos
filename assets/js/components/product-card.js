(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createProductCardController = function (root, onSelect) {
        let selectedCard = null;

        function restore(card) {
            if (card && card.getAttribute('data-state') !== 'out_of_stock') {
                card.setAttribute('data-state', card === selectedCard ? 'selected' : 'normal');
            }
        }

        root.addEventListener('pointerdown', function (event) {
            const trigger = event.target.closest('[data-action="select-product"]');
            const card = trigger && trigger.closest('[data-component="product-card"]');

            if (card && !trigger.disabled) {
                card.setAttribute('data-state', 'pressed');
            }
        });

        root.addEventListener('pointerup', function (event) {
            restore(event.target.closest('[data-component="product-card"]'));
        });

        root.addEventListener('pointercancel', function (event) {
            restore(event.target.closest('[data-component="product-card"]'));
        });

        root.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-action="select-product"]');
            const card = trigger && trigger.closest('[data-component="product-card"]');

            if (!card || trigger.disabled) {
                return;
            }

            if (selectedCard && selectedCard !== card) {
                restore(selectedCard);
                selectedCard.setAttribute('data-state', 'normal');
            }

            selectedCard = card;
            selectedCard.setAttribute('data-state', 'selected');

            if (typeof onSelect === 'function') {
                onSelect({
                    productId: card.getAttribute('data-product-id') || '',
                    occurrenceKey: card.getAttribute('data-occurrence-key') || '',
                    name: (card.querySelector('.coffeepos-product-name') || {}).textContent || ''
                });
            }
        });

        return {
            getSelectedProductId: function () {
                return selectedCard ? selectedCard.getAttribute('data-product-id') || '' : '';
            }
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
