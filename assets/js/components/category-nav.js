(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createCategoryNavController = function (root, onChange) {
        const nav = root.querySelector('[data-component="category-nav"]');
        const catalogScroll = root.querySelector('[data-component="catalog-scroll"]');
        let activeCategory = 'all';
        let frame = 0;
        let suppressSpyUntil = 0;

        function buttons() {
            return nav ? nav.querySelectorAll('[data-action="scroll-category"]') : [];
        }

        function sections() {
            return catalogScroll ? catalogScroll.querySelectorAll('[data-component="catalog-category-section"]') : [];
        }

        function render() {
            buttons().forEach(function (button) {
                const isActive = button.getAttribute('data-category-id') === activeCategory;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        function setActive(categoryId, notify) {
            activeCategory = categoryId || 'all';
            render();

            if (notify !== false && typeof onChange === 'function') {
                onChange(activeCategory);
            }
        }

        function scrollToCategory(categoryId) {
            if (!catalogScroll) {
                return;
            }

            let top = 0;

            if (categoryId !== 'all') {
                const section = catalogScroll.querySelector(
                    '[data-component="catalog-category-section"][data-category-id="' + window.CSS.escape(String(categoryId)) + '"]'
                );

                if (!section) {
                    return;
                }

                const scrollRect = catalogScroll.getBoundingClientRect();
                const sectionRect = section.getBoundingClientRect();
                top = catalogScroll.scrollTop + sectionRect.top - scrollRect.top - 8;
            }

            suppressSpyUntil = Date.now() + 500;
            setActive(categoryId, true);
            catalogScroll.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        }

        function locateOccurrence(result) {
            if (!catalogScroll || !result) {
                return;
            }

            const occurrenceKey = String(result.occurrence_key || '');
            const card = catalogScroll.querySelector(
                '[data-component="product-card"][data-occurrence-key="' + window.CSS.escape(occurrenceKey) + '"]'
            );

            if (!card) {
                return;
            }

            const scrollRect = catalogScroll.getBoundingClientRect();
            const cardRect = card.getBoundingClientRect();
            const top = catalogScroll.scrollTop + cardRect.top - scrollRect.top - 12;
            suppressSpyUntil = Date.now() + 600;
            setActive(String(result.category_id || 'all'), true);
            catalogScroll.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
            card.setAttribute('data-highlighted', 'true');

            window.setTimeout(function () {
                const button = card.querySelector('[data-action="select-product"]');

                if (button) {
                    button.focus({ preventScroll: true });
                }

                window.setTimeout(function () {
                    card.removeAttribute('data-highlighted');
                }, 1400);
            }, 350);
        }

        function updateFromScroll() {
            frame = 0;

            if (!catalogScroll || Date.now() < suppressSpyUntil) {
                return;
            }

            if (catalogScroll.scrollTop <= 8) {
                setActive('all', true);
                return;
            }

            const scrollTop = catalogScroll.getBoundingClientRect().top + 18;
            let nextCategory = 'all';

            sections().forEach(function (section) {
                if (section.getBoundingClientRect().top <= scrollTop) {
                    nextCategory = section.getAttribute('data-category-id') || nextCategory;
                }
            });

            setActive(nextCategory, true);
        }

        function onScroll() {
            if (frame) {
                return;
            }

            frame = window.requestAnimationFrame(updateFromScroll);
        }

        if (nav) {
            nav.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-action="scroll-category"]');

                if (!trigger || !nav.contains(trigger) || trigger.disabled) {
                    return;
                }

                scrollToCategory(trigger.getAttribute('data-category-id') || 'all');
            });
        }

        if (catalogScroll) {
            catalogScroll.addEventListener('scroll', onScroll, { passive: true });
        }

        return {
            init: function () { setActive('all', false); },
            refresh: function () {
                setActive(activeCategory, false);
                updateFromScroll();
            },
            setActive: setActive,
            scrollToCategory: scrollToCategory,
            locateOccurrence: locateOccurrence,
            getActive: function () { return activeCategory; }
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
