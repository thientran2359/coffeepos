(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const setState = CoffeePOS.core.setState;

    function normalize(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/đ/g, 'd')
            .replace(/Đ/g, 'D')
            .toLowerCase()
            .trim();
    }

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createProductSearchController = function (root, renderer, navigator, onChange) {
        const wrapper = root.querySelector('[data-component="search"]');
        const input = root.querySelector('[data-component="product-search"]');
        const clearButton = root.querySelector('[data-action="clear-search"]');
        const panel = root.querySelector('[data-component="product-search-panel"]');
        const resultTarget = root.querySelector('[data-component="product-search-results"]');
        const loading = root.querySelector('[data-component="search-results-loading"]');
        const empty = root.querySelector('[data-component="search-results-empty"]');
        let searchIndex = [];
        let results = [];
        let searchTerm = '';
        let timer = 0;
        let activeIndex = -1;

        function notify(state) {
            if (typeof onChange === 'function') {
                onChange(searchTerm, state);
            }
        }

        function setPanelState(state) {
            setState(wrapper, searchTerm === '' ? 'idle' : state);
            setState(panel, state);

            if (panel) {
                panel.hidden = state === 'closed';
            }

            if (input) {
                input.setAttribute('aria-expanded', state === 'closed' ? 'false' : 'true');
            }

            if (loading) {
                loading.hidden = state !== 'loading';
            }

            if (empty) {
                empty.hidden = state !== 'empty';
            }

            if (resultTarget) {
                resultTarget.hidden = state !== 'results';
            }

            notify(state);
        }

        function syncActiveResult() {
            if (!resultTarget) {
                return;
            }

            resultTarget.querySelectorAll('[data-action="locate-product"]').forEach(function (button, index) {
                const selected = index === activeIndex;
                button.classList.toggle('is-active', selected);
                button.setAttribute('aria-selected', selected ? 'true' : 'false');
            });
        }

        function renderResults(items) {
            results = items;
            activeIndex = items.length > 0 ? 0 : -1;

            if (items.length === 0) {
                if (resultTarget) {
                    resultTarget.replaceChildren();
                }
                setPanelState('empty');
                return;
            }

            renderer.renderList('coffeepos-product-search-result-template', items, resultTarget);
            setPanelState('results');
            syncActiveResult();
        }

        function search() {
            const query = normalize(searchTerm);

            if (query === '') {
                close();
                return;
            }

            renderResults(searchIndex.filter(function (item) {
                return item.searchName.indexOf(query) !== -1;
            }).slice(0, 12).map(function (item) {
                return item.view;
            }));
        }

        function update(value) {
            window.clearTimeout(timer);
            searchTerm = String(value || '').trim();

            if (clearButton) {
                clearButton.hidden = searchTerm === '';
            }

            if (searchTerm === '') {
                close();
                return;
            }

            setPanelState('loading');
            timer = window.setTimeout(search, 300);
        }

        function close() {
            window.clearTimeout(timer);
            results = [];
            activeIndex = -1;

            if (resultTarget) {
                resultTarget.replaceChildren();
            }

            setPanelState('closed');
        }

        function clear() {
            if (input) {
                input.value = '';
                input.focus();
            }

            searchTerm = '';

            if (clearButton) {
                clearButton.hidden = true;
            }

            close();
        }

        function locate(result) {
            if (!result) {
                return;
            }

            navigator.locateOccurrence(result);
            close();
        }

        function setCatalog(catalog) {
            searchIndex = [];

            (Array.isArray(catalog && catalog.categories) ? catalog.categories : []).forEach(function (category) {
                (Array.isArray(category.products) ? category.products : []).forEach(function (product) {
                    searchIndex.push({
                        searchName: normalize(product.name),
                        view: {
                            occurrence_key: product.occurrence_key,
                            category_id: category.id,
                            category_name: category.name,
                            product_id: product.id,
                            name: product.name
                        }
                    });
                });
            });

            if (input) {
                input.disabled = searchIndex.length === 0;
            }
        }

        if (input) {
            input.addEventListener('input', function (event) { update(event.target.value); });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    close();
                    return;
                }

                if (results.length === 0) {
                    return;
                }

                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    activeIndex = (activeIndex + 1) % results.length;
                    syncActiveResult();
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    activeIndex = (activeIndex - 1 + results.length) % results.length;
                    syncActiveResult();
                } else if (event.key === 'Enter') {
                    event.preventDefault();
                    locate(results[Math.max(0, activeIndex)]);
                }
            });
        }

        if (clearButton) {
            clearButton.addEventListener('click', clear);
        }

        if (panel) {
            panel.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-action="locate-product"]');

                if (!trigger) {
                    return;
                }

                locate(results.find(function (result) {
                    return String(result.occurrence_key) === trigger.getAttribute('data-occurrence-key');
                }));
            });
        }

        return {
            init: function () { clear(); },
            setCatalog: setCatalog,
            clear: clear,
            close: close,
            getTerm: function () { return searchTerm; }
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
