(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.components = CoffeePOS.components || {};
    CoffeePOS.components.createReceiptPrinter = function (root, renderer) {
        const view = root.querySelector('[data-component="receipt"]');
        if (!view) { return { print: function () { return Promise.reject(new Error('Receipt template is unavailable.')); } }; }
        function field(name, value) { const node = view.querySelector('[data-field="' + name + '"]'); if (node) { node.textContent = String(value || ''); } }
        function display(value) { return value && typeof value === 'object' ? String(value.display || '') : String(value || ''); }
        function cleanup() { window.document.body.classList.remove('coffeepos-printing'); view.hidden = true; view.setAttribute('data-state', 'idle'); }
        function render(receipt) {
            if (!receipt || !receipt.store || !receipt.order || !receipt.totals || !Array.isArray(receipt.items) || !receipt.order.number || !display(receipt.totals.total)) { throw new Error('Receipt data is incomplete.'); }
            const service = receipt.service || {}; const payment = receipt.payment || {};
            const paperWidth = String(receipt.paper_width || window.CoffeePOSConfig && window.CoffeePOSConfig.receiptPaperWidth || '80');
            view.setAttribute('data-paper-width', paperWidth === '58' ? '58' : '80');
            const logo = view.querySelector('[data-component="receipt-logo"]');
            const logoUrl = String(receipt.store.logo_url || '');
            if (logo) { if (/^https?:\/\//i.test(logoUrl)) { logo.src = logoUrl; logo.hidden = false; } else { logo.removeAttribute('src'); logo.hidden = true; } }
            field('receipt-store-name', receipt.store.name); field('receipt-branch-name', receipt.store.branch_name); field('receipt-store-address', receipt.store.address); field('receipt-store-phone', receipt.store.phone); field('receipt-order-number', receipt.order.number); field('receipt-created-at', receipt.order.created_at_display || (receipt.order.created_at ? new Date(receipt.order.created_at).toLocaleString() : '')); field('receipt-footer', receipt.footer);
            field('receipt-cashier', receipt.cashier && receipt.cashier.display_name); field('receipt-customer', receipt.customer && receipt.customer.name); field('receipt-customer-phone', receipt.customer && receipt.customer.phone_masked);
            field('receipt-service', service.order_type === 'dine_in' ? 'Dine-in' + (service.table_label ? ' — ' + service.table_label : '') : 'Takeaway'); field('receipt-payment', payment.method_label || payment.method);
            field('receipt-subtotal', display(receipt.totals.subtotal)); field('receipt-discount', display(receipt.totals.discount)); field('receipt-refunded', display(receipt.totals.refunded)); field('receipt-total', display(receipt.totals.total)); field('receipt-received', payment.received_display); field('receipt-change', payment.change_display);
            view.querySelector('[data-component="receipt-refunded-row"]').hidden = !receipt.totals.refunded || Number(receipt.totals.refunded.amount || 0) === 0; view.querySelector('[data-component="receipt-received-row"]').hidden = !payment.received_display; view.querySelector('[data-component="receipt-change-row"]').hidden = !payment.change_display;
            const note = String(receipt.order_note || ''); view.querySelector('[data-component="receipt-order-note"]').hidden = note === ''; field('receipt-order-note', note);
            renderer.renderList('coffeepos-receipt-item-template', receipt.items, view.querySelector('[data-component="receipt-items"]')); view.hidden = false; view.setAttribute('data-state', 'ready');
        }
        window.addEventListener('afterprint', cleanup);
        return { print: function (receipt) { return new Promise(function (resolve, reject) { try { render(receipt); } catch (error) { cleanup(); reject(error); return; } window.document.body.classList.add('coffeepos-printing'); window.requestAnimationFrame(function () { window.requestAnimationFrame(function () { try { window.print(); } finally { cleanup(); resolve(); } }); }); }); }, destroy: function () { window.removeEventListener('afterprint', cleanup); cleanup(); } };
    };
    window.CoffeePOS = CoffeePOS;
}(window));
