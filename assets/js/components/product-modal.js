(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const setState = CoffeePOS.core.setState;

    CoffeePOS.components = CoffeePOS.components || {};

    CoffeePOS.components.createProductModalController = function (root, renderer, api, onSubmit, onStateChange) {
        const element = root.querySelector('[data-component="product-modal"]');
        const title = element.querySelector('[data-component="product-modal-title"]');
        const modeLabel = element.querySelector('[data-component="product-modal-mode"]');
        const price = element.querySelector('[data-component="product-modal-price"]');
        const loading = element.querySelector('[data-component="product-modal-loading"]');
        const error = element.querySelector('[data-component="product-modal-error"]');
        const errorMessage = element.querySelector('[data-component="product-modal-error-message"]');
        const content = element.querySelector('[data-component="product-modal-content"]');
        const variationSection = element.querySelector('[data-component="variation-section"]');
        const variationTarget = element.querySelector('[data-component="variation-selector"]');
        const variationError = element.querySelector('[data-component="variation-error"]');
        const modifierSection = element.querySelector('[data-component="modifier-section"]');
        const modifierTarget = element.querySelector('[data-component="modifier-selector"]');
        const quickNotesSection = element.querySelector('[data-component="quick-notes-section"]');
        const quickNotesTarget = element.querySelector('[data-component="quick-notes"]');
        const customNote = element.querySelector('[data-component="product-custom-note"]');
        const quantityOutput = element.querySelector('[data-component="configuration-quantity"]');
        const submitButton = element.querySelector('[data-component="submit-product-configuration"]');
        const labels = (window.CoffeePOSConfig && window.CoffeePOSConfig.i18n) || {};
        let requestSequence = 0;
        let productRequest = null;
        let variationRequest = null;
        let state = initialState();

        const lifecycle = CoffeePOS.ui.createDialogLifecycle(element, null, function () {
            reset();
            notifyState('closed');
        });

        function initialState() {
            return {
                status: 'closed',
                mode: 'add',
                itemId: '',
                detail: null,
                selectedAttributes: {},
                selectedModifiers: {},
                selectedQuickNotes: [],
                resolvedVariation: null,
                quantity: 1,
                submitting: false
            };
        }

        function notifyState(status) {
            state.status = status;
            setState(element, status);

            if (typeof onStateChange === 'function') {
                onStateChange({
                    status: status,
                    mode: state.mode,
                    productId: state.detail && state.detail.product ? state.detail.product.id : 0,
                    itemId: state.itemId
                });
            }
        }

        function reset() {
            requestSequence += 1;

            if (productRequest) {
                productRequest.abort();
            }

            if (variationRequest) {
                variationRequest.abort();
            }

            productRequest = null;
            variationRequest = null;
            state = initialState();
            variationTarget.replaceChildren();
            modifierTarget.replaceChildren();
            quickNotesTarget.replaceChildren();
            content.hidden = true;
            loading.hidden = false;
            error.hidden = true;
            variationError.hidden = true;
            customNote.value = '';
            quantityOutput.textContent = '1';
        }

        function showLoading(productName) {
            title.textContent = productName || (labels.product || 'Product');
            modeLabel.textContent = state.mode === 'edit'
                ? (labels.editItem || 'Edit item')
                : (labels.addItem || 'Add item');
            content.hidden = true;
            error.hidden = true;
            loading.hidden = false;
            notifyState('loading');
        }

        function showLoadError(message) {
            loading.hidden = true;
            content.hidden = true;
            error.hidden = false;
            errorMessage.textContent = message || (labels.productLoadError || 'Product could not be loaded.');
            notifyState('error');
        }

        function renderVariationGroups(groups) {
            const output = document.createDocumentFragment();

            groups.forEach(function (group) {
                const groupFragment = renderer.render('coffeepos-variation-group-template', group);
                const optionTarget = groupFragment.querySelector('[data-component="variation-option-list"]');
                const options = (Array.isArray(group.options) ? group.options : []).map(function (option) {
                    return {
                        attribute_name: group.name,
                        value: option.value,
                        label: option.label
                    };
                });
                renderer.renderList('coffeepos-variation-option-template', options, optionTarget);
                output.appendChild(groupFragment);
            });

            variationTarget.replaceChildren(output);
            variationSection.hidden = groups.length === 0;
        }

        function renderModifierGroups(groups) {
            const output = document.createDocumentFragment();

            groups.forEach(function (group) {
                const groupFragment = renderer.render('coffeepos-modifier-group-template', group);
                const optionTarget = groupFragment.querySelector('[data-component="modifier-option-list"]');
                const options = (Array.isArray(group.options) ? group.options : []).map(function (option) {
                    return {
                        group_id: group.id,
                        id: option.id,
                        label: option.label
                    };
                });
                renderer.renderList('coffeepos-modifier-option-template', options, optionTarget);
                output.appendChild(groupFragment);
            });

            modifierTarget.replaceChildren(output);
            modifierSection.hidden = groups.length === 0;
        }

        function renderQuickNotes(notes) {
            renderer.renderList('coffeepos-quick-note-template', notes, quickNotesTarget);
            quickNotesSection.hidden = notes.length === 0;
        }

        function applySelectionState() {
            variationTarget.querySelectorAll('[data-action="select-variation"]').forEach(function (button) {
                const selected = state.selectedAttributes[button.getAttribute('data-attribute-name')]
                    === button.getAttribute('data-attribute-value');
                button.classList.toggle('is-active', selected);
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });

            modifierTarget.querySelectorAll('[data-action="select-modifier"]').forEach(function (button) {
                const groupId = button.getAttribute('data-modifier-group-id');
                const optionId = button.getAttribute('data-modifier-option-id');
                const selected = (state.selectedModifiers[groupId] || []).indexOf(optionId) !== -1;
                button.classList.toggle('is-active', selected);
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });

            quickNotesTarget.querySelectorAll('[data-action="toggle-quick-note"]').forEach(function (button) {
                const selected = state.selectedQuickNotes.indexOf(button.getAttribute('data-quick-note-id')) !== -1;
                button.classList.toggle('is-active', selected);
                button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            });
        }

        function restoreItem(item) {
            state.quantity = Math.max(1, Number(item.quantity || 1));
            state.selectedAttributes = Object.assign({}, item.variation && item.variation.attributes || {});
            state.selectedModifiers = Object.assign({}, item.modifiers || {});
            state.selectedQuickNotes = Array.isArray(item.quick_notes) ? item.quick_notes.slice() : [];
            customNote.value = String(item.custom_note || '');

            if (Number(item.variation_id || 0) > 0) {
                state.resolvedVariation = (state.detail.variations || []).find(function (variation) {
                    return Number(variation.id) === Number(item.variation_id);
                }) || null;
            }
        }

        function renderReady(item) {
            const detail = state.detail;
            const product = detail.product || {};
            const groups = Array.isArray(detail.attributes) ? detail.attributes : [];
            const modifierGroups = Array.isArray(detail.modifier_groups) ? detail.modifier_groups : [];
            const notes = Array.isArray(detail.quick_notes) ? detail.quick_notes : [];

            title.textContent = String(product.name || '');
            renderVariationGroups(groups);
            renderModifierGroups(modifierGroups);
            renderQuickNotes(notes);

            if (item) {
                restoreItem(item);
            }

            applySelectionState();
            quantityOutput.textContent = String(state.quantity);
            loading.hidden = true;
            error.hidden = true;
            content.hidden = false;
            notifyState('ready');
            updatePrice();
            updateSubmitState();
        }

        function updatePrice() {
            const product = state.detail && state.detail.product || {};
            const display = state.resolvedVariation && state.resolvedVariation.price_display
                ? state.resolvedVariation.price_display
                : product.price_display || '';
            price.textContent = String(display);
        }

        function hasCompleteVariation() {
            const groups = state.detail && Array.isArray(state.detail.attributes) ? state.detail.attributes : [];
            return groups.every(function (group) {
                return Boolean(state.selectedAttributes[group.name]);
            });
        }

        function modifiersAreValid() {
            const groups = state.detail && Array.isArray(state.detail.modifier_groups)
                ? state.detail.modifier_groups
                : [];

            return groups.every(function (group) {
                const selected = state.selectedModifiers[group.id] || [];
                const minimum = Math.max(0, Number(group.minimum || (group.required ? 1 : 0)));
                const maximum = Math.max(minimum, Number(group.maximum || (group.selection === 'single' ? 1 : group.options.length)));
                return selected.length >= minimum && selected.length <= maximum;
            });
        }

        function updateSubmitState() {
            const product = state.detail && state.detail.product || {};
            const variationValid = product.is_variable !== true
                || (state.resolvedVariation && state.resolvedVariation.is_available === true);
            submitButton.disabled = state.status !== 'ready'
                || state.submitting
                || product.is_in_stock !== true
                || product.is_purchasable !== true
                || !variationValid
                || !modifiersAreValid();
            submitButton.textContent = state.submitting
                ? (labels.saving || 'Saving...')
                : (state.mode === 'edit' ? (labels.updateItem || 'Update item') : (labels.addToCart || 'Add to cart'));
        }

        async function resolveVariation() {
            const product = state.detail && state.detail.product || {};

            if (product.is_variable !== true || !hasCompleteVariation()) {
                state.resolvedVariation = null;
                variationError.hidden = true;
                updatePrice();
                updateSubmitState();
                return;
            }

            if (variationRequest) {
                variationRequest.abort();
            }

            variationRequest = new window.AbortController();
            const sequence = ++requestSequence;
            variationError.hidden = true;
            submitButton.disabled = true;

            try {
                const data = await api.resolveVariation(product.id, state.selectedAttributes, variationRequest.signal);

                if (sequence !== requestSequence || state.status === 'closed') {
                    return;
                }

                state.resolvedVariation = data.variation;
                updatePrice();
                updateSubmitState();
            } catch (requestError) {
                if (requestError && requestError.name === 'AbortError') {
                    return;
                }

                if (sequence !== requestSequence) {
                    return;
                }

                state.resolvedVariation = null;
                variationError.textContent = requestError.message || (labels.variationUnavailable || 'This variation is unavailable.');
                variationError.hidden = false;
                updatePrice();
                updateSubmitState();
            }
        }

        function selectVariation(button) {
            state.selectedAttributes[button.getAttribute('data-attribute-name')] = button.getAttribute('data-attribute-value');
            state.resolvedVariation = null;
            applySelectionState();
            resolveVariation();
        }

        function selectModifier(button) {
            const groupId = button.getAttribute('data-modifier-group-id');
            const optionId = button.getAttribute('data-modifier-option-id');
            const group = (state.detail.modifier_groups || []).find(function (candidate) { return candidate.id === groupId; });

            if (!group) {
                return;
            }

            const selected = (state.selectedModifiers[groupId] || []).slice();
            const existingIndex = selected.indexOf(optionId);

            if (group.selection === 'single') {
                state.selectedModifiers[groupId] = existingIndex === -1 ? [optionId] : [];
            } else if (existingIndex === -1) {
                const maximum = Math.max(0, Number(group.maximum || group.options.length));

                if (selected.length < maximum) {
                    selected.push(optionId);
                }
                state.selectedModifiers[groupId] = selected;
            } else {
                selected.splice(existingIndex, 1);
                state.selectedModifiers[groupId] = selected;
            }

            applySelectionState();
            updateSubmitState();
        }

        function toggleQuickNote(button) {
            const noteId = button.getAttribute('data-quick-note-id');
            const index = state.selectedQuickNotes.indexOf(noteId);

            if (index === -1) {
                state.selectedQuickNotes.push(noteId);
            } else {
                state.selectedQuickNotes.splice(index, 1);
            }

            applySelectionState();
        }

        function changeQuantity(delta) {
            state.quantity = Math.max(1, state.quantity + delta);
            quantityOutput.textContent = String(state.quantity);
        }

        async function submit() {
            if (submitButton.disabled || state.submitting) {
                return;
            }

            state.submitting = true;
            updateSubmitState();
            variationError.hidden = true;

            const product = state.detail.product;
            const payload = {
                product_id: Number(product.id),
                variation_id: state.resolvedVariation ? Number(state.resolvedVariation.id) : 0,
                quantity: state.quantity,
                modifiers: state.selectedModifiers,
                quick_notes: state.selectedQuickNotes,
                custom_note: customNote.value
            };

            try {
                await onSubmit(payload, state.mode, state.itemId);
                lifecycle.close();
            } catch (submitError) {
                state.submitting = false;
                variationError.textContent = submitError.message || (labels.cartUpdateError || 'The cart could not be updated.');
                variationError.hidden = false;
                updateSubmitState();
            }
        }

        async function open(productId, mode, item) {
            reset();
            state.mode = mode === 'edit' ? 'edit' : 'add';
            state.itemId = item ? String(item.item_id || item.key || '') : '';
            lifecycle.open('');
            showLoading(item ? item.product_name : '');
            productRequest = new window.AbortController();
            const sequence = ++requestSequence;

            try {
                const detail = await api.loadProduct(productId, productRequest.signal);

                if (sequence !== requestSequence || element.hidden) {
                    return;
                }

                state.detail = detail;
                renderReady(item || null);
            } catch (requestError) {
                if (requestError && requestError.name === 'AbortError') {
                    return;
                }

                if (sequence === requestSequence) {
                    showLoadError(requestError.message);
                }
            }
        }

        element.addEventListener('click', function (event) {
            const trigger = event.target.closest('[data-action]');

            if (!trigger || trigger.disabled) {
                return;
            }

            switch (trigger.getAttribute('data-action')) {
                case 'close-product-modal':
                    lifecycle.close();
                    break;
                case 'select-variation':
                    selectVariation(trigger);
                    break;
                case 'select-modifier':
                    selectModifier(trigger);
                    break;
                case 'toggle-quick-note':
                    toggleQuickNote(trigger);
                    break;
                case 'decrease-quantity':
                    changeQuantity(-1);
                    break;
                case 'increase-quantity':
                    changeQuantity(1);
                    break;
                case 'add-to-cart':
                    submit();
                    break;
                default:
                    break;
            }
        });

        return {
            openAdd: function (productId) { return open(productId, 'add', null); },
            openEdit: function (item) { return open(item.product_id, 'edit', item); },
            close: lifecycle.close,
            getState: function () { return state; }
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
