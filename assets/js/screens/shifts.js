(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.screens = CoffeePOS.screens || {};
    CoffeePOS.screens.createShiftsController = function (root) {
        const api = CoffeePOS.api.createPosApi(CoffeePOS.api.createClient());
        const screen = root.querySelector('[data-component="shifts-screen"]');
        const openPanel = root.querySelector('[data-component="shift-open-panel"]');
        const activePanel = root.querySelector('[data-component="shift-active-panel"]');
        const errorNode = root.querySelector('[data-component="shift-error"]');
        const historyNode = root.querySelector('[data-component="shift-history"]');
        const emptyNode = root.querySelector('[data-component="shift-history-empty"]');
        const rowTemplate = root.querySelector('template[data-template="shift-history-row"]');
        let shift = null;
        let pending = false;

        function money(value, currency) {
            return new Intl.NumberFormat(undefined, {style: 'currency', currency: currency || 'USD'}).format(Number(value || 0));
        }
        function field(name, value) { const node = activePanel.querySelector('[data-field="' + name + '"]'); if (node) { node.textContent = value; } }
        function renderCurrent(value) {
            shift = value || null; openPanel.hidden = !!shift; activePanel.hidden = !shift;
            if (!shift) { return; }
            field('shift-number', 'Shift #' + shift.id);
            field('shift-meta', shift.cashier_name + ' · ' + new Date(shift.opened_at).toLocaleString());
            field('opening-cash', money(shift.opening_cash, shift.currency)); field('cash-sales', money(shift.cash_sales, shift.currency));
            field('bank-sales', money(shift.bank_sales, shift.currency)); field('total-sales', money(shift.total_sales, shift.currency));
            field('expected-cash', money(shift.expected_cash, shift.currency)); field('order-count', String(shift.order_count));
            activePanel.querySelector('[name="actual_cash"]').value = shift.expected_cash;
        }
        function renderHistory(items) {
            historyNode.replaceChildren(); emptyNode.hidden = items.length > 0;
            items.forEach(function (item) {
                const node = rowTemplate.content.firstElementChild.cloneNode(true);
                const values = {number: 'Shift #' + item.id, period: new Date(item.opened_at).toLocaleString() + ' – ' + new Date(item.closed_at).toLocaleString(), sales: money(item.total_sales, item.currency), expected: money(item.expected_cash, item.currency), actual: money(item.actual_cash, item.currency), variance: money(item.variance, item.currency)};
                Object.keys(values).forEach(function (key) { node.querySelector('[data-field="' + key + '"]').textContent = values[key]; });
                historyNode.appendChild(node);
            });
        }
        function fail(error) { errorNode.textContent = error.message || 'Shift request failed.'; errorNode.hidden = false; }
        async function load() {
            errorNode.hidden = true;
            try { const results = await Promise.all([api.getCurrentShift(), api.loadShiftHistory(50)]); renderCurrent(results[0].shift); renderHistory(results[1].items || []); screen.setAttribute('data-state', 'ready'); } catch (error) { fail(error); screen.setAttribute('data-state', 'error'); }
        }
        async function submit(event) {
            event.preventDefault(); if (pending) { return; } pending = true; errorNode.hidden = true;
            const form = event.currentTarget; const data = new window.FormData(form); Array.from(form.elements).forEach(function (el) { el.disabled = true; });
            try {
                if (form.matches('[data-component="open-shift-form"]')) { await api.openShift({opening_cash: data.get('opening_cash'), opening_note: data.get('opening_note')}); }
                else if (shift) { await api.closeShift(shift.id, {actual_cash: data.get('actual_cash'), closing_note: data.get('closing_note')}); }
                form.reset(); await load();
            } catch (error) { fail(error); } finally { pending = false; Array.from(form.elements).forEach(function (el) { el.disabled = false; }); }
        }
        function init() { root.querySelector('[data-component="open-shift-form"]').addEventListener('submit', submit); root.querySelector('[data-component="close-shift-form"]').addEventListener('submit', submit); load(); }
        return {init: init, getState: function () { return {shift: shift, pending: pending}; }};
    };
    window.CoffeePOS = CoffeePOS;
}(window));
