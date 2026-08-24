(function (window, document) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    const sprintf = window.wp.i18n.sprintf;
    CoffeePOS.screens = CoffeePOS.screens || {};

    CoffeePOS.screens.createReportsController = function (appRoot) {
        const root = appRoot.querySelector('[data-component="reports-screen"]');
        const client = CoffeePOS.api.createClient();
        const api = CoffeePOS.api.createPosApi(client);
        const renderer = new CoffeePOS.ui.TemplateRenderer();
        const filters = root.querySelector('[data-component="report-filters"]');
        const groupsNode = root.querySelector('[data-component="report-groups"]');
        const errorNode = root.querySelector('[data-component="report-error"]');
        const statusNode = root.querySelector('[data-component="report-status"]');
        const emptyNode = root.querySelector('[data-component="report-empty"]');
        const warningsNode = root.querySelector('[data-component="report-warnings"]');
        const warningList = root.querySelector('[data-component="report-warning-list"]');
        const customFrom = root.querySelector('[data-component="report-custom-from"]');
        const customTo = root.querySelector('[data-component="report-custom-to"]');
        let state = 'loading';
        let report = null;
        let requestController = null;
        let exporting = false;

        function values(extra) {
            const data = new window.FormData(filters);
            const preset = String(data.get('preset') || 'today');
            const result = {
                preset: preset,
                product_limit: String(data.get('product_limit') || '10')
            };
            if (preset === 'custom') {
                result.date_from = String(data.get('date_from') || '');
                result.date_to = String(data.get('date_to') || '');
            }
            return Object.assign(result, extra || {});
        }

        function setState(next, message) {
            state = next;
            root.setAttribute('data-state', next);
            statusNode.textContent = message || '';
            statusNode.hidden = next === 'ready' || next === 'empty';
        }

        function showError(error) {
            errorNode.textContent = error && error.message ? error.message : __('The report could not be loaded.', 'coffeepos');
            errorNode.hidden = false;
            setState('error', __('Report unavailable. Adjust the filters or try again.', 'coffeepos'));
        }

        function renderWarnings(items) {
            const warnings = Array.isArray(items) ? items.map(function (message) { return {message: message}; }) : [];
            renderer.renderList('coffeepos-report-warning-template', warnings, warningList);
            warningsNode.hidden = warnings.length === 0;
        }

        function renderGroup(group) {
            const fragment = renderer.render('coffeepos-report-currency-template', group);
            renderer.renderList('coffeepos-report-payment-template', group.payments || [], fragment.querySelector('[data-component="report-payment-list"]'));
            renderer.renderList('coffeepos-report-product-template', group.top_by_revenue || [], fragment.querySelector('[data-component="report-products-revenue"]'));
            renderer.renderList('coffeepos-report-product-template', group.top_by_quantity || [], fragment.querySelector('[data-component="report-products-quantity"]'));
            renderer.renderList('coffeepos-report-hour-template', group.peak_hours || [], fragment.querySelector('[data-component="report-hours"]'));
            return fragment;
        }

        function render(nextReport) {
            report = nextReport;
            const groups = Array.isArray(nextReport.currency_groups) ? nextReport.currency_groups : [];
            const output = document.createDocumentFragment();
            groups.forEach(function (group) { output.appendChild(renderGroup(group)); });
            groupsNode.replaceChildren(output);
            renderWarnings(nextReport.warnings);
            const rangeLabel = appRoot.querySelector('[data-field="report-range-label"]');
            rangeLabel.textContent = nextReport.range.label + ' · ' + nextReport.range.timezone;
            emptyNode.hidden = groups.length !== 0;
            setState(groups.length === 0 ? 'empty' : 'ready', groups.length === 0 ? __('No sales found.', 'coffeepos') : '');
        }

        async function load() {
            if (requestController) {
                requestController.abort();
            }
            const requestValues = values();
            requestController = new window.AbortController();
            errorNode.hidden = true;
            emptyNode.hidden = true;
            warningsNode.hidden = true;
            groupsNode.replaceChildren();
            setState('loading', __('Loading sales report…', 'coffeepos'));
            Array.from(filters.elements).forEach(function (element) { element.disabled = true; });
            try {
                const data = await api.loadSalesReport(requestValues, requestController.signal);
                render(data.report);
            } catch (error) {
                if (!error || error.name !== 'AbortError') {
                    showError(error);
                }
            } finally {
                Array.from(filters.elements).forEach(function (element) { element.disabled = false; });
                requestController = null;
            }
        }

        async function exportReport(format) {
            if (exporting) {
                return;
            }
            exporting = true;
            errorNode.hidden = true;
            setState('exporting', sprintf(__('Preparing %s export…', 'coffeepos'), format.toUpperCase()));
            try {
                const file = await api.downloadSalesReport(values({format: format}));
                const url = window.URL.createObjectURL(file.blob);
                const anchor = document.createElement('a');
                anchor.href = url;
                anchor.download = file.filename;
                anchor.hidden = true;
                document.body.appendChild(anchor);
                anchor.click();
                anchor.remove();
                window.setTimeout(function () { window.URL.revokeObjectURL(url); }, 1000);
                setState(report && report.currency_groups.length === 0 ? 'empty' : 'ready', '');
            } catch (error) {
                showError(error);
            } finally {
                exporting = false;
            }
        }

        function toggleCustom() {
            const custom = filters.elements.preset.value === 'custom';
            customFrom.hidden = !custom;
            customTo.hidden = !custom;
            filters.elements.date_from.required = custom;
            filters.elements.date_to.required = custom;
        }

        function onClick(event) {
            const trigger = event.target.closest('[data-action="export-report"]');
            if (!trigger) {
                return;
            }
            exportReport(String(trigger.getAttribute('data-format') || 'csv'));
        }

        function init() {
            filters.addEventListener('submit', function (event) { event.preventDefault(); load(); });
            filters.elements.preset.addEventListener('change', toggleCustom);
            root.addEventListener('click', onClick);
            toggleCustom();
            load();
        }

        return {
            init: init,
            getState: function () { return {state: state, report: report, exporting: exporting}; }
        };
    };

    window.CoffeePOS = CoffeePOS;
}(window, document));
