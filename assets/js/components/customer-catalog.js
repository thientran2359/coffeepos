(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.components = CoffeePOS.components || {};
    CoffeePOS.components.createCustomerCatalog = function (root, renderer) {
        const menu = root.querySelector('[data-component="customer-menu"]');
        const nav = root.querySelector('[data-component="customer-category-nav"]');
        const sections = root.querySelector('[data-component="customer-catalog-sections"]');
        const status = root.querySelector('[data-component="customer-catalog-status"]');
        const retry = root.querySelector('[data-action="retry-customer-catalog"]');
        function setStatus(next, message) {
            menu.setAttribute('data-state', next); status.textContent = message || '';
            status.hidden = next === 'normal'; retry.hidden = next !== 'error';
        }
        function render(catalog) {
            const categories = (catalog && Array.isArray(catalog.categories) ? catalog.categories : []).map(function (category) {
                const products = (category.products || []).filter(function (product) { return product.is_in_stock === true && product.is_purchasable === true; });
                return Object.assign({}, category, { products: products });
            }).filter(function (category) { return category.products.length > 0; });
            renderer.renderList('coffeepos-customer-category-button-template', categories, nav);
            renderer.renderList('coffeepos-customer-category-template', categories, sections);
            categories.forEach(function (category) {
                const target = sections.querySelector('[data-category-products="' + String(category.id).replace(/"/g, '') + '"]');
                renderer.renderList('coffeepos-customer-product-row-template', category.products, target);
            });
            setStatus(categories.length ? 'normal' : 'empty', categories.length ? '' : 'The menu is currently empty.');
        }
        function scroll(categoryId) {
            const section = sections.querySelector('[data-category-id="' + String(categoryId).replace(/"/g, '') + '"]');
            if (section) { section.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
        }
        return { render: render, setStatus: setStatus, scroll: scroll };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
