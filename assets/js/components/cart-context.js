(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    const setState = CoffeePOS.core.setState;
    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createCartContextController = function (root, renderer, api, onAttach, onService) {
        const customerElement = root.querySelector('[data-component="customer-lookup"]');
        const customerForm = customerElement.querySelector('[data-component="customer-lookup-form"]');
        const phoneInput = customerElement.querySelector('[data-component="customer-phone-input"]');
        const customerStatus = customerElement.querySelector('[data-component="customer-lookup-status"]');
        const customerResult = customerElement.querySelector('[data-component="customer-lookup-result"]');
        const createPanel = customerElement.querySelector('[data-component="customer-create"]');
        const createForm = customerElement.querySelector('[data-component="customer-create-form"]');
        const createPhone = customerElement.querySelector('[data-component="customer-create-phone"]');
        const createName = customerElement.querySelector('[data-component="customer-create-name"]');
        const createEmail = customerElement.querySelector('[data-component="customer-create-email"]');
        const memberCreateEnabled = customerElement.getAttribute('data-member-create-enabled') === 'true';
        const tableElement = root.querySelector('[data-component="table-selector"]');
        const tableStatus = tableElement.querySelector('[data-component="table-selector-status"]');
        const tableList = tableElement.querySelector('[data-component="table-list"]');
        let lookupRequest = null;
        let lookupSequence = 0;
        let lookupTimer = 0;
        let tableRequest = null;
        let attaching = false;
        let creating = false;
        let tablePending = false;
        let creationOperationId = '';

        const customerDialog = CoffeePOS.ui.createDialogLifecycle(customerElement, null, function () {
            if (lookupRequest) {
                lookupRequest.abort();
            }
            window.clearTimeout(lookupTimer);
            lookupSequence += 1;
            setState(customerElement, 'closed');
        }, function () {
            return !creating && !attaching;
        });
        const tableDialog = CoffeePOS.ui.createDialogLifecycle(tableElement, null, function () {
            if (tableRequest) {
                tableRequest.abort();
            }
            setState(tableElement, 'closed');
        });

        function operationId() {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return 'member-' + window.crypto.randomUUID();
            }
            return 'member-' + Date.now() + '-' + Math.random().toString(16).slice(2);
        }

        function normalizedDigits(value) {
            let digits = String(value || '').replace(/\D+/g, '');
            if (digits.length === 11 && digits.indexOf('84') === 0) {
                digits = '0' + digits.slice(2);
            }
            return digits;
        }

        function canLookup(value) {
            const digits = normalizedDigits(value);
            return digits.length >= 9 && digits.length <= 15;
        }

        function setCustomerStatus(status, message) {
            setState(customerElement, status);
            customerStatus.textContent = message || '';
        }

        function membershipLabel(customer) {
            const membership = customer && customer.membership;
            return membership
                ? membership.tier_label || membership.status_label || membership.points_display || ''
                : '';
        }

        function renderCandidate(value) {
            const customer = Object.assign({ membership_label: '', email: '' }, value || {});
            customer.membership_label = membershipLabel(customer);
            renderer.renderList('coffeepos-customer-result-template', [customer], customerResult);
            createPanel.hidden = true;
            setCustomerStatus('found', __('Member found. Confirm to use this member.', 'coffeepos'));
        }

        function showCreate(phone) {
            customerResult.replaceChildren();
            if (!memberCreateEnabled) {
                createPanel.hidden = true;
                setCustomerStatus('not_found', __('No member was found. Member creation is disabled.', 'coffeepos'));
                return;
            }
            createPhone.value = String(phone || '');
            createPanel.hidden = false;
            setCustomerStatus('not_found', __('No member was found. You can create one below.', 'coffeepos'));
        }

        function resetCandidate() {
            customerResult.replaceChildren();
            createPanel.hidden = true;
            creationOperationId = '';
        }

        async function lookup() {
            window.clearTimeout(lookupTimer);
            const value = phoneInput.value;
            if (!canLookup(value)) {
                if (lookupRequest) {
                    lookupRequest.abort();
                }
                lookupSequence += 1;
                resetCandidate();
                setCustomerStatus(value.trim() === '' ? 'idle' : 'typing', value.trim() === '' ? '' : __('Enter a complete phone number.', 'coffeepos'));
                return;
            }
            if (lookupRequest) {
                lookupRequest.abort();
            }
            lookupRequest = new window.AbortController();
            const sequence = ++lookupSequence;
            customerResult.replaceChildren();
            createPanel.hidden = true;
            setCustomerStatus('searching', __('Searching for member…', 'coffeepos'));
            try {
                const data = await api.lookupCustomer(value, lookupRequest.signal);
                if (sequence !== lookupSequence || customerElement.hidden) {
                    return;
                }
                renderCandidate(data.customer || {});
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                if (sequence !== lookupSequence) {
                    return;
                }
                if (error && error.code === 'customer_not_found') {
                    showCreate(value);
                    return;
                }
                resetCandidate();
                setCustomerStatus('error', error && error.message ? error.message : __('Member lookup failed.', 'coffeepos'));
            }
        }

        function scheduleLookup() {
            window.clearTimeout(lookupTimer);
            resetCandidate();
            if (!canLookup(phoneInput.value)) {
                setCustomerStatus(phoneInput.value.trim() === '' ? 'idle' : 'typing', phoneInput.value.trim() === '' ? '' : __('Enter a complete phone number.', 'coffeepos'));
                return;
            }
            setCustomerStatus('typing', __('Waiting to search…', 'coffeepos'));
            lookupTimer = window.setTimeout(lookup, 400);
        }

        async function selectCustomer(customerId) {
            if (attaching || creating) {
                return;
            }
            attaching = true;
            setCustomerStatus('attaching', __('Adding member to cart…', 'coffeepos'));
            try {
                await onAttach(Number(customerId));
                attaching = false;
                customerDialog.close();
            } catch (error) {
                setCustomerStatus('conflict', error && error.message ? error.message : __('Member could not be attached.', 'coffeepos'));
            } finally {
                attaching = false;
            }
        }

        async function createCustomer() {
            if (creating || attaching) {
                return;
            }
            creating = true;
            createForm.querySelectorAll('input, button').forEach(function (element) { element.disabled = true; });
            if (!creationOperationId) {
                creationOperationId = operationId();
            }
            setCustomerStatus('creating', __('Creating member…', 'coffeepos'));
            try {
                const data = await api.createCustomer({
                    display_name: createName.value,
                    phone: createPhone.value,
                    email: createEmail.value,
                    client_operation_id: creationOperationId
                });
                const customer = data.customer || {};
                renderCandidate(customer);
                setCustomerStatus('created_pending_attach', __('Member created. Adding member to cart…', 'coffeepos'));
                creating = false;
                await selectCustomer(customer.customer_id);
                return;
            } catch (error) {
                if (error && error.code === 'customer_phone_exists' && error.details && error.details.customer) {
                    renderCandidate(error.details.customer);
                    setCustomerStatus('found', __('This phone already belongs to a member. Confirm to use it.', 'coffeepos'));
                } else {
                    setCustomerStatus('error', error && error.message ? error.message : __('Member could not be created.', 'coffeepos'));
                }
            } finally {
                creating = false;
                createForm.querySelectorAll('input, button').forEach(function (element) { element.disabled = false; });
            }
        }

        async function openTables() {
            tableDialog.open('');
            setState(tableElement, 'loading');
            tableStatus.textContent = __('Loading tables...', 'coffeepos');
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
                tableStatus.textContent = items.length ? '' : __('No tables are available.', 'coffeepos');
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }
                setState(tableElement, 'error');
                tableStatus.textContent = error.message || __('Tables could not be loaded.', 'coffeepos');
            }
        }

        async function selectTable(tableId) {
            if (tablePending) {
                return;
            }
            tablePending = true;
            setState(tableElement, 'updating');
            tableStatus.textContent = __('Updating service...', 'coffeepos');
            try {
                await onService('dine_in', Number(tableId));
                tableDialog.close();
            } catch (error) {
                setState(tableElement, 'error');
                tableStatus.textContent = error.message;
            } finally {
                tablePending = false;
            }
        }

        customerForm.addEventListener('submit', function (event) {
            event.preventDefault();
            lookup();
        });
        phoneInput.addEventListener('input', scheduleLookup);
        createForm.addEventListener('submit', function (event) {
            event.preventDefault();
            createCustomer();
        });
        createForm.addEventListener('input', function () {
            creationOperationId = '';
        });
        createPhone.addEventListener('input', function () {
            phoneInput.value = createPhone.value;
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
                resetCandidate();
                customerStatus.textContent = '';
                phoneInput.value = '';
                createPhone.value = '';
                createName.value = '';
                createEmail.value = '';
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
