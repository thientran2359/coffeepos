(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    const sprintf = window.wp.i18n.sprintf;
    CoffeePOS.screens = CoffeePOS.screens || {};
    CoffeePOS.screens.createOrderHistoryController = function (root) {
        const renderer = new CoffeePOS.ui.TemplateRenderer();
        const api = CoffeePOS.api.createPosApi(CoffeePOS.api.createClient());
        const canRefundOrders = !!(window.CoffeePOSConfig && window.CoffeePOSConfig.canRefundOrders);
        const confirmDialog = CoffeePOS.ui.createConfirmDialogController(root);
        const screen = root.querySelector('[data-component="order-history-screen"]');
        const filters = root.querySelector('[data-component="history-filters"]');
        const list = root.querySelector('[data-component="history-list"]');
        const empty = root.querySelector('[data-component="history-empty"]');
        const errorNode = root.querySelector('[data-component="history-error"]');
        const cardTemplate = root.querySelector('template[data-template="history-order-card"]');
        const detailTemplate = root.querySelector('template[data-template="history-detail-item"]');
        const detailDialog = root.querySelector('[data-component="history-detail-dialog"]');
        const refundDialog = root.querySelector('[data-component="refund-dialog"]');
        const refundForm = root.querySelector('[data-component="refund-form"]');
        const receiptPrinter = CoffeePOS.components.createReceiptPrinter(root, renderer);
        let page = 1; let pages = 0; let orders = []; let selected = null; let request = null; let pending = false;

        function operation(prefix) { return prefix + '-' + (window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2)); }
        function value(form, name) { return String(new window.FormData(form).get(name) || ''); }
        function criteria() { return {page: page, per_page: 20, date_from: value(filters, 'date_from'), date_to: value(filters, 'date_to'), status: value(filters, 'status'), order_type: value(filters, 'order_type'), search: value(filters, 'search')}; }
        function service(order) { const context = order.service || {}; return context.order_type === 'dine_in' ? __('Dine-in', 'coffeepos') + (context.table_label ? ' · ' + context.table_label : '') : __('Takeaway', 'coffeepos'); }
        function showError(node, error) { node.textContent = error && error.message ? error.message : __('The request could not be completed.', 'coffeepos'); node.hidden = false; }
        function setField(parent, name, text) { const node = parent.querySelector('[data-field="' + name + '"]'); if (node) { node.textContent = String(text === undefined || text === null ? '' : text); } }

        function renderList(data) {
            orders = Array.isArray(data.items) ? data.items : []; pages = Number(data.pages || 0); list.replaceChildren();
            orders.forEach(function (order) {
                const node = cardTemplate.content.firstElementChild.cloneNode(true); node.setAttribute('data-order-id', String(order.id));
                setField(node, 'number', sprintf(__('Order #%s', 'coffeepos'), order.number)); setField(node, 'created', order.created_at_display || new Date(order.created_at).toLocaleString());
                setField(node, 'customer', order.customer.display_name || __('Guest', 'coffeepos')); setField(node, 'service', service(order));
                setField(node, 'status', order.status_label); setField(node, 'total', order.totals.total.display); list.appendChild(node);
            });
            empty.hidden = orders.length > 0; setField(screen, 'history-count', data.total || 0);
            setField(screen, 'page-status', pages > 0 ? sprintf(__('Page %1$s of %2$s', 'coffeepos'), page, pages) : __('Page 0 of 0', 'coffeepos'));
            root.querySelector('[data-action="previous-page"]').disabled = page <= 1;
            root.querySelector('[data-action="next-page"]').disabled = pages === 0 || page >= pages;
        }
        async function load() {
            if (request) { request.abort(); } request = new window.AbortController(); errorNode.hidden = true; screen.setAttribute('data-state', 'loading');
            try { const data = await api.loadOrderHistory(criteria(), request.signal); renderList(data); screen.setAttribute('data-state', 'ready'); }
            catch (error) { if (error && error.name === 'AbortError') { return; } showError(errorNode, error); screen.setAttribute('data-state', 'error'); }
        }
        function renderDetail(order) {
            selected = order; setField(detailDialog, 'detail-status', order.status_label); setField(detailDialog, 'detail-number', sprintf(__('Order #%s', 'coffeepos'), order.number));
            setField(detailDialog, 'detail-created', order.created_at_display || new Date(order.created_at).toLocaleString()); setField(detailDialog, 'detail-customer', order.customer.display_name || __('Guest', 'coffeepos'));
            setField(detailDialog, 'detail-service', service(order)); setField(detailDialog, 'detail-payment', order.payment.method_label || order.payment.method || '—');
            setField(detailDialog, 'detail-subtotal', order.totals.subtotal.display); setField(detailDialog, 'detail-discount', order.totals.discount.display);
            setField(detailDialog, 'detail-refunded', order.totals.refunded.display); setField(detailDialog, 'detail-total', order.totals.total.display);
            const itemList = detailDialog.querySelector('[data-component="detail-items"]'); itemList.replaceChildren();
            (order.items || []).forEach(function (item) { const node = detailTemplate.content.firstElementChild.cloneNode(true); setField(node, 'name', item.name); setField(node, 'quick_notes', item.quick_note_summary || ''); setField(node, 'note', item.note || ''); setField(node, 'quantity', '× ' + item.quantity); setField(node, 'total', item.total.display); itemList.appendChild(node); });
            const orderNote = detailDialog.querySelector('[data-component="history-order-note"]'); setField(detailDialog, 'detail-order-note', order.order_note || ''); orderNote.hidden = !order.order_note;
            detailDialog.querySelector('[data-action="cancel-history-order"]').hidden = !order.actions.can_cancel;
            detailDialog.querySelector('[data-action="refund-order"]').hidden = !order.actions.can_refund || !canRefundOrders;
            detailDialog.querySelector('[data-action="reorder-order"]').hidden = !order.actions.can_reorder;
            detailDialog.querySelector('[data-action="reprint-order"]').hidden = !order.actions.can_reprint;
            detailDialog.querySelector('[data-component="detail-error"]').hidden = true;
        }
        async function openDetail(id) {
            try { const data = await api.loadOrderDetail(id); renderDetail(data.order); if (!detailDialog.open) { detailDialog.showModal(); } }
            catch (error) { showError(errorNode, error); }
        }
        async function refreshSelected() { if (!selected) { return; } const data = await api.loadOrderDetail(selected.id); renderDetail(data.order); await load(); if (!detailDialog.open) { detailDialog.showModal(); } }
        async function cancelSelected() {
            if (!selected || pending) { return; } pending = true;
            try { await api.cancelOperationalOrder(selected.id, {expected_state: selected.kds.state, expected_revision: selected.kds.revision, client_operation_id: operation('history-cancel'), reason: __('Cancelled from Order History', 'coffeepos')}); await refreshSelected(); }
            catch (error) { if (!detailDialog.open) { detailDialog.showModal(); } showError(detailDialog.querySelector('[data-component="detail-error"]'), error); } finally { pending = false; }
        }
        async function reorderSelected() {
            if (!selected || pending) { return; } pending = true;
            try {
                const data = await api.reorderOrder(selected.id, {client_operation_id: operation('history-reorder')});
                window.sessionStorage.setItem('coffeepos.pos_session_id', String(data.cart.pos_session_id));
                const back = root.querySelector('.coffeepos-operations__header a'); window.location.assign(back.href);
            } catch (error) { showError(detailDialog.querySelector('[data-component="detail-error"]'), error); pending = false; }
        }
        async function submitRefund(event) {
            event.preventDefault(); if (!selected || pending) { return; } pending = true; const error = refundDialog.querySelector('[data-component="refund-error"]'); error.hidden = true;
            Array.from(refundForm.elements).forEach(function (element) { element.disabled = true; });
            try { await api.refundOrder(selected.id, {amount: value(refundForm, 'amount'), reason: value(refundForm, 'reason'), client_operation_id: operation('history-refund')}); refundDialog.close(); refundForm.reset(); await refreshSelected(); }
            catch (failure) { showError(error, failure); } finally { pending = false; Array.from(refundForm.elements).forEach(function (element) { element.disabled = false; }); }
        }
        async function printSelected() {
            if (!selected) { return; }
            try {
                const data = await api.loadReceipt(selected.id); await receiptPrinter.print(data.receipt);
            } catch (error) { showError(detailDialog.querySelector('[data-component="detail-error"]'), error); }
        }
        function openRefund() { if (!selected) { return; } refundForm.querySelector('[name="amount"]').value = selected.totals.refundable_amount; setField(refundDialog, 'refund-maximum', sprintf(__('Maximum: %s', 'coffeepos'), selected.totals.refundable.display)); refundDialog.querySelector('[data-component="refund-error"]').hidden = true; refundDialog.showModal(); }
        function onClick(event) {
            const trigger = event.target.closest('[data-action]'); if (!trigger) { return; } const action = trigger.getAttribute('data-action');
            if (action === 'view-order') { const card=trigger.closest('[data-order-id]'); if(card){openDetail(Number(card.getAttribute('data-order-id')));} }
            else if (action === 'previous-page' && page > 1) { page--; load(); }
            else if (action === 'next-page' && page < pages) { page++; load(); }
            else if (action === 'close-detail') { detailDialog.close(); }
            else if (action === 'close-refund') { refundDialog.close(); }
            else if (action === 'refund-order') { openRefund(); }
            else if (action === 'reprint-order') { printSelected(); }
            else if (action === 'cancel-history-order' && selected) { detailDialog.close(); confirmDialog.open({title:sprintf(__('Cancel order #%s?', 'coffeepos'), selected.number),message:__('This cancels the WooCommerce order but does not return money. Use Refund when money must be returned.', 'coffeepos'),action:'cancel-order'}); }
            else if (action === 'reorder-order' && selected) { detailDialog.close(); confirmDialog.open({title:sprintf(__('Reorder order #%s?', 'coffeepos'), selected.number),message:__('A new cashier cart will be created using current prices and availability.', 'coffeepos'),action:'reorder-order'}); }
        }
        function onConfirm(event) { const action=String(event.detail&&event.detail.action||''); if(action==='cancel-order'){cancelSelected();} if(action==='reorder-order'){reorderSelected();} }
        function init() { filters.addEventListener('submit',function(event){event.preventDefault();page=1;load();}); root.addEventListener('click',onClick); root.addEventListener('coffeepos:confirm',onConfirm); refundForm.addEventListener('submit',submitRefund); load(); }
        return {init:init,getState:function(){return{page:page,pages:pages,orders:orders,selected:selected,pending:pending};}};
    };
    window.CoffeePOS = CoffeePOS;
}(window));
