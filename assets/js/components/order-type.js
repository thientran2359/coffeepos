(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const setState = CoffeePOS.core.setState;

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createOrderTypeController = function (root, onChange) {
        const component = root.querySelector('[data-component="order-type"]');
        const tablePlaceholder = root.querySelector('[data-component="table-placeholder"]');
        let selected = 'takeaway';

        function setSelected(orderType, notify) {
            selected = orderType === 'dine_in' ? 'dine_in' : 'takeaway';

            if (component) {
                component.querySelectorAll('[data-action="select-order-type"]').forEach(function (button) {
                    const isActive = button.getAttribute('data-order-type') === selected;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                });
            }

            if (tablePlaceholder) {
                const visible = selected === 'dine_in';
                tablePlaceholder.hidden = !visible;
                setState(tablePlaceholder, visible ? 'visible' : 'hidden');
            }

            if (notify !== false && typeof onChange === 'function') {
                onChange(selected);
            }
        }

        if (component) {
            component.addEventListener('click', function (event) {
                const trigger = event.target.closest('[data-action="select-order-type"]');

                if (trigger) {
                    const requested = trigger.getAttribute('data-order-type') === 'dine_in' ? 'dine_in' : 'takeaway';

                    if (typeof onChange === 'function') {
                        onChange(requested);
                    }
                }
            });
        }

        return {
            init: function () { setSelected('takeaway', false); },
            setSelected: setSelected,
            setPending: function (pending) {
                if (component) {
                    component.querySelectorAll('[data-action="select-order-type"]').forEach(function (button) {
                        button.disabled = pending === true;
                    });
                    setState(component, pending === true ? 'updating' : 'normal');
                }
            },
            getSelected: function () { return selected; }
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
