(function (window, document) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createCatalogRenderer = function (root, renderer) {
        const categoryTarget = root.querySelector('[data-component="category-list"]');
        const sectionTarget = root.querySelector('[data-component="catalog-section-list"]');
        const allButton = root.querySelector('[data-action="scroll-category"][data-category-id="all"]');
        const searchInput = root.querySelector('[data-component="product-search"]');
        const labels = (window.CoffeePOSConfig && window.CoffeePOSConfig.i18n) || {};

        function prepareProduct(fragment, product) {
            const card = fragment.querySelector('[data-component="product-card"]');
            const button = fragment.querySelector('[data-action="select-product"]');
            const stock = fragment.querySelector('[data-component="product-stock-label"]');
            const variation = fragment.querySelector('[data-component="product-variation-indicator"]');
            const initial = fragment.querySelector('.coffeepos-product-initial');
            const image = fragment.querySelector('[data-component="product-image"]');
            const unavailable = product.is_in_stock !== true || product.is_purchasable !== true;

            if (card) {
                card.setAttribute('data-state', unavailable ? 'out_of_stock' : 'normal');
            }

            if (button) {
                button.disabled = unavailable;
                button.setAttribute('aria-disabled', unavailable ? 'true' : 'false');
            }

            if (stock) {
                stock.textContent = unavailable ? (labels.outOfStock || __('Out of stock', 'coffeepos')) : (labels.inStock || __('In stock', 'coffeepos'));
            }

            if (variation) {
                variation.textContent = product.is_variable === true ? (labels.optionsAvailable || __('Options available', 'coffeepos')) : '';
            }

            if (initial) {
                initial.textContent = String(product.name || '?').trim().charAt(0).toUpperCase() || '?';
            }

            if (image) {
                image.addEventListener('error', function () {
                    image.removeAttribute('src');
                }, { once: true });
            }
        }

        function render(catalog) {
            const categories = Array.isArray(catalog && catalog.categories) ? catalog.categories : [];
            renderer.renderList('coffeepos-category-button-template', categories, categoryTarget);
            const output = document.createDocumentFragment();

            categories.forEach(function (category) {
                const sectionFragment = renderer.render('coffeepos-catalog-category-template', category);
                const count = sectionFragment.querySelector('[data-component="catalog-category-count"]');
                const productTarget = sectionFragment.querySelector('[data-component="catalog-category-products"]');
                const products = Array.isArray(category.products) ? category.products : [];

                if (count) {
                    count.textContent = String(products.length);
                }

                products.forEach(function (product) {
                    const productFragment = renderer.render('coffeepos-product-card-template', product);
                    prepareProduct(productFragment, product);
                    productTarget.appendChild(productFragment);
                });

                output.appendChild(sectionFragment);
            });

            sectionTarget.replaceChildren(output);

            if (allButton) {
                allButton.disabled = categories.length === 0;
            }

            if (searchInput) {
                searchInput.disabled = categories.length === 0;
            }
        }

        return { render: render };
    };

    window.CoffeePOS = CoffeePOS;
}(window, document));
