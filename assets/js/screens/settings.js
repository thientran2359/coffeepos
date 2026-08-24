(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.screens = CoffeePOS.screens || {};

    CoffeePOS.screens.createSettingsController = function (root) {
        const form = root.querySelector('[data-component="settings-form"]');
        const rows = root.querySelector('[data-component="quick-note-rows"]');
        const empty = root.querySelector('[data-component="quick-note-empty"]');
        const template = document.getElementById('coffeepos-quick-note-row-template');
        const optionName = 'coffeepos_quick_notes';
        const logo = root.querySelector('[data-component="settings-logo"]');
        const logoId = logo ? logo.querySelector('[data-component="settings-logo-id"]') : null;
        const logoPreview = logo ? logo.querySelector('[data-component="settings-logo-preview"]') : null;
        const removeLogoButton = logo ? logo.querySelector('[data-action="remove-settings-logo"]') : null;
        const slugInput = form ? form.querySelector('[name="coffeepos_pos_base_slug"]') : null;
        const slugPreview = form ? form.querySelector('[data-component="pos-slug-preview"]') : null;
        let mediaFrame = null;
        let nextIndex = rows ? rows.querySelectorAll('[data-component="quick-note-row"]').length : 0;

        function updateEmptyState() {
            if (!rows || !empty) {
                return;
            }

            empty.hidden = rows.querySelector('[data-component="quick-note-row"]') !== null;
        }

        function assignFieldNames(row, index) {
            row.querySelectorAll('[data-setting-field]').forEach(function (field) {
                field.name = optionName + '[' + String(index) + '][' + field.getAttribute('data-setting-field') + ']';
            });
        }

        function addQuickNote() {
            if (!rows || !template) {
                return;
            }

            const fragment = template.content.cloneNode(true);
            const row = fragment.querySelector('[data-component="quick-note-row"]');
            assignFieldNames(row, nextIndex);
            nextIndex += 1;
            rows.appendChild(fragment);
            updateEmptyState();
            row.querySelector('[data-setting-field="id"]').focus();
        }

        function removeQuickNote(button) {
            const row = button.closest('[data-component="quick-note-row"]');

            if (row) {
                row.remove();
                updateEmptyState();
            }
        }

        function validateUniqueIds(event) {
            if (event.submitter && ['export', 'import'].includes(event.submitter.value)) {
                return;
            }
            const seen = new Map();
            let firstInvalid = null;

            rows.querySelectorAll('[data-component="quick-note-row"] input[name$="[id]"]').forEach(function (input) {
                const id = String(input.value || '').trim();
                input.setCustomValidity('');

                if (id !== '' && seen.has(id)) {
                    input.setCustomValidity('Quick note IDs must be unique.');
                    if (!firstInvalid) {
                        firstInvalid = input;
                    }
                } else if (id !== '') {
                    seen.set(id, input);
                }
            });

            if (firstInvalid) {
                event.preventDefault();
                firstInvalid.reportValidity();
                firstInvalid.focus();
            }
        }

        function selectLogo() {
            if (!window.wp || !window.wp.media || !logoId || !logoPreview) {
                return;
            }
            mediaFrame = mediaFrame || window.wp.media({ title: 'Select CoffeePOS logo', button: { text: 'Use this logo' }, multiple: false, library: { type: 'image' } });
            mediaFrame.off('select').on('select', function () {
                const attachment = mediaFrame.state().get('selection').first().toJSON();
                logoId.value = String(attachment.id || '');
                logoPreview.src = String(attachment.sizes && attachment.sizes.medium ? attachment.sizes.medium.url : attachment.url || '');
                logoPreview.hidden = logoPreview.src === '';
                if (removeLogoButton) { removeLogoButton.hidden = logoPreview.hidden; }
            });
            mediaFrame.open();
        }

        function removeLogo() {
            if (logoId) { logoId.value = '0'; }
            if (logoPreview) { logoPreview.removeAttribute('src'); logoPreview.hidden = true; }
            if (removeLogoButton) { removeLogoButton.hidden = true; }
        }

        function onClick(event) {
            const trigger = event.target.closest('[data-action]');

            if (!trigger) {
                return;
            }

            if (trigger.getAttribute('data-action') === 'add-quick-note') {
                addQuickNote();
            }

            if (trigger.getAttribute('data-action') === 'remove-quick-note') {
                removeQuickNote(trigger);
            }

            if (trigger.getAttribute('data-action') === 'select-settings-logo') {
                selectLogo();
            }

            if (trigger.getAttribute('data-action') === 'remove-settings-logo') {
                removeLogo();
            }
        }

        function init() {
            if (!form || !rows || !template) {
                return;
            }

            root.addEventListener('click', onClick);
            form.addEventListener('submit', validateUniqueIds);
            if (slugInput && slugPreview) {
                slugInput.addEventListener('input', function () { slugPreview.textContent = String(slugInput.value || 'pos'); });
            }
            updateEmptyState();
        }

        return {
            init: init,
            getState: function () {
                return {
                    quickNoteCount: rows ? rows.querySelectorAll('[data-component="quick-note-row"]').length : 0
                };
            }
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window));
