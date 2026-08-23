(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const setState = CoffeePOS.core.setState;
    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createCartContextController = function (root, renderer, api, onAttach, onService) {
        const customerElement = root.querySelector('[data-component="customer-lookup"]');
        const customerForm = customerElement.querySelector('[data-component="customer-lookup-form"]');
        const phoneInput = customerElement.querySelector('[data-component="customer-phone-input"]');
        const customerStatus = customerElement.querySelector('[data-component="customer-lookup-status"]');
        const customerResult = customerElement.querySelector('[data-component="customer-lookup-result"]');
        const tableElement = root.querySelector('[data-component="table-selector"]');
        const tableStatus = tableElement.querySelector('[data-component="table-selector-status"]');
        const tableList = tableElement.querySelector('[data-component="table-list"]');
        let lookupRequest = null;
        let lookupSequence = 0;
        let tableRequest = null;
        let pending = false;

        const customerDialog = CoffeePOS.ui.createDialogLifecycle(customerElement, null, function () {
            if (lookupRequest) {
                lookupRequest.abort();
            }
            lookupSequence += 1;
            setState(customerElement, 'closed');
        });
        const tableDialog = CoffeePOS.ui.createDialogLifecycle(tableElement, null, function () {
            if (tableRequest) {
                tableRequest.abort();
            }
            setState(tableElement, 'closed');
        });

        function setCustomerStatus(status, message) {
            setState(customerElement, status);
            customerStatus.textContent = message || '';
        }

        async function lookup() {
            if (lookupRequest) {
                lookupRequest.abort();
            }
            lookupRequest = new window.AbortController();
            const sequence = ++lookupSequence;
            customerResult.replaceChildren();
            setCustomerStatus('searching', 'Searching...');
            try {
                const data = await api.lookupCustomer(phoneInput.value, lookupRequest.signal);
                if (sequence !== lookupSequence || customerElement.hidden) {
                    return;
                }
                const customer = Object.assign({ membership_label: '' }, data.customer || {});
                if (customer.membership) {
                    customer.membership_label = customer.membership.tier_label
                        || customer.membership.status_label
                        || customer.membership.points_display
                        || '';
                }
                renderer.renderList('coffeepos-customer-result-template', [customer], customerResult);
                setCustomerStatus('found', 'Customer found.');
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                if (sequence !== lookupSequence) {
                    return;
                }
                setCustomerStatus(error.code === 'customer_not_found' ? 'not_found' : 'error', error.message);
            }
        }

        async function selectCustomer(customerId) {
            if (pending) {
                return;
            }
            pending = true;
            setCustomerStatus('attaching', 'Adding customer...');
            try {
                await onAttach(Number(customerId));
                customerDialog.close();
            } catch (error) {
                setCustomerStatus('found', error.message);
            } finally {
                pending = false;
            }
        }

        async function openTables() {
            tableDialog.open('');
            setState(tableElement, 'loading');
            tableStatus.textContent = 'Loading tables...';
            tableList.replaceChildren();
            if (tableRequest) {
                tableRequest.abort();
            }
            tableRequest = new window.AbortController();
            try {
                const data = await api.loadTables(tableRequest.signal);
                if (tableElement.hidden) {
                    return;
                }
                const items = Array.isArray(data.items) ? data.items : [];
                renderer.renderList('coffeepos-table-option-template', items, tableList);
                setState(tableElement, items.length ? 'normal' : 'empty');
                tableStatus.textContent = items.length ? '' : 'No tables are available.';
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                setState(tableElement, 'error');
                tableStatus.textContent = error.message || 'Tables could not be loaded.';
            }
        }

        async function selectTable(tableId) {
            if (pending) {
                return;
            }
            pending = true;
            setState(tableElement, 'updating');
            tableStatus.textContent = 'Updating service...';
            try {
                await onService('dine_in', Number(tableId));
                tableDialog.close();
            } catch (error) {
                setState(tableElement, 'error');
                tableStatus.textContent = error.message;
            } finally {
                pending = false;
            }
        }

        customerForm.addEventListener('submit', function (event) {
            event.preventDefault();
            lookup();
        });
        customerElement.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-action]');
            if (!trigger) {
                return;
            }
            if (trigger.getAttribute('data-action') === 'close-customer-lookup') {
                customerDialog.close();
            } else if (trigger.getAttribute('data-action') === 'select-customer') {
                selectCustomer(trigger.getAttribute('data-customer-id'));
            }
        });
        tableElement.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-action]');
            if (!trigger) {
                return;
            }
            if (trigger.getAttribute('data-action') === 'close-table-selector') {
                tableDialog.close();
            } else if (trigger.getAttribute('data-action') === 'select-table') {
                selectTable(trigger.getAttribute('data-table-id'));
            }
        });

        return {
            openCustomer: function () {
                customerResult.replaceChildren();
                customerStatus.textContent = '';
                customerDialog.open('');
                setState(customerElement, 'idle');
                phoneInput.focus();
            },
            openTables: openTables,
            closeTables: tableDialog.close
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
