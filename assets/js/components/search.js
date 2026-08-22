(function (window) {
    'use strict';

    var CoffeePOS = window.CoffeePOS || {};
    var setState = (CoffeePOS.core && CoffeePOS.core.setState) || function () {};

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.setSearchState = function (state, elements, nextState) {
        setState(elements.searchWrap, nextState);
        state.productGridState = nextState === 'loading' ? 'loading' : (state.searchTerm ? 'normal' : 'normal');
        setState(elements.productGrid, state.productGridState);
    };

    CoffeePOS.components.setSearchTerm = function (state, elements, value) {
        state.searchTerm = (value || '').trim();

        if (elements.search && elements.search.value !== state.searchTerm) {
            elements.search.value = state.searchTerm;
        }
    };

    window.CoffeePOS = CoffeePOS;
}(window));
