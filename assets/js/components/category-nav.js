(function (window) {
    'use strict';

    var CoffeePOS = window.CoffeePOS || {};

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.syncCategoryButtons = function (buttons, activeCategory) {
        Array.prototype.forEach.call(buttons, function (button) {
            var categoryId = button.getAttribute('data-category-id') || '';
            var isActive = categoryId === activeCategory;

            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            button.classList.toggle('is-active', isActive);
        });
    };

    window.CoffeePOS = CoffeePOS;
}(window));
