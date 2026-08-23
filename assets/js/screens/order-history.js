(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    CoffeePOS.screens = CoffeePOS.screens || {};
    CoffeePOS.screens.createOrderHistoryController = function (root) {
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
        let page = 1; let pages = 0; let orders = []; let selected = null; let request = null; let pending = false;

        function operation(prefix) { return prefix + '-' + (window.crypto && window.crypto.randomUUID ? window.crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2)); }
        function value(form, name) { return String(new window.FormData(form).get(name) || ''); }
        function criteria() { return {page: page, per_page: 20, date_from: value(filters, 'date_from'), date_to: value(filters, 'date_to'), status: value(filters, 'status'), order_type: value(filters, 'order_type'), search: value(filters, 'search')}; }
        function service(order) { const context = order.service || {}; return context.order_type === 'dine_in' ? 'Dine-in' + (context.table_label ? ' · ' + context.table_label : '') : 'Takeaway'; }
        function showError(node, error) { node.textContent = error && error.message ? error.message : 'The request could not be completed.'; node.hidden = false; }
        function setField(parent, name, text) { const node = parent.querySelector('[data-field="' + name + '"]'); if (node) { node.textContent = String(text === undefined || text === null ? '' : text); } }

        function renderList(data) {
            orders = Array.isArray(data.items) ? data.items : []; pages = Number(data.pages || 0); list.replaceChildren();
            orders.forEach(function (order) {
                const node = cardTemplate.content.firstElementChild.cloneNode(true); node.setAttribute('data-order-id', String(order.id));
                setField(node, 'number', 'Order #' + order.number); setField(node, 'created', new Date(order.created_at).toLocaleString());
                setField(node, 'customer', order.customer.display_name || 'Guest'); setField(node, 'service', service(order));
                setField(node, 'status', order.status_label); setField(node, 'total', order.totals.total.display); list.appendChild(node);
            });
            empty.hidden = orders.length > 0; setField(screen, 'history-count', data.total || 0);
            setField(screen, 'page-status', pages > 0 ? 'Page ' + page + ' of ' + pages : 'Page 0 of 0');
            root.querySelector('[data-action="previous-page"]').disabled = page <= 1;
            root.querySelector('[data-action="next-page"]').disabled = pages === 0 || page >= pages;
        }
        async function load() {
            if (request) { request.abort(); } request = new window.AbortController(); errorNode.hidden = true; screen.setAttribute('data-state', 'loading');
            try { const data = await api.loadOrderHistory(criteria(), request.signal); renderList(data); screen.setAttribute('data-state', 'ready'); }
            catch (error) { if (error && error.name === 'AbortError') { return; } showError(errorNode, error); screen.setAttribute('data-state', 'error'); }
        }
        function renderDetail(order) {
            selected = order; setField(detailDialog, 'detail-status', order.status_label); setField(detailDialog, 'detail-number', 'Order #' + order.number);
            setField(detailDialog, 'detail-created', new Date(order.created_at).toLocaleString()); setField(detailDialog, 'detail-customer', order.customer.display_name || 'Guest');
            setField(detailDialog, 'detail-service', service(order)); setField(detailDialog, 'detail-payment', order.payment.method_label || order.payment.method || '—');
            setField(detailDialog, 'detail-subtotal', order.totals.subtotal.display); setField(detailDialog, 'detail-discount', order.totals.discount.display);
            setField(detailDialog, 'detail-refunded', order.totals.refunded.display); setField(detailDialog, 'detail-total', order.totals.total.display);
            const itemList = detailDialog.querySelector('[data-component="detail-items"]'); itemList.replaceChildren();
            (order.items || []).forEach(function (item) { const node = detailTemplate.content.firstElementChild.cloneNode(true); setField(node, 'name', item.name); setField(node, 'note', item.note || ''); setField(node, 'quantity', '× ' + item.quantity); setField(node, 'total', item.total.display); itemList.appendChild(node); });
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
            try { await api.cancelOperationalOrder(selected.id, {expected_state: selected.kds.state, expected_revision: selected.kds.revision, client_operation_id: operation('history-cancel'), reason: 'Cancelled from Order History'}); await refreshSelected(); }
            catch (error) { if (!detailDialog.open) { detailDialog.showModal(); } showError(detailDialog.querySelector('[data-component="detail-error"]'), error); } finally { pending = false; }
        }
        async function reorderSelected() {
            if (!selected || pending) { return; } pending = true;
            try {
                const data = await api.reorderOrder(selected.id, {client_operation_id: operation('history-reorder')});
                window.sessionStorage.setItem('coffeepos.pos_session_id', String(data.cart.pos_session_id));
                const back = root.querySelector('.coffeepos-operations-header a'); window.location.assign(back.href);
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
                const data = await api.loadReceipt(selected.id); const receipt = data.receipt; const view = root.querySelector('[data-component="receipt"]');
                setField(view, 'receipt-store-name', receipt.store.name); setField(view, 'receipt-store-address', receipt.store.address); setField(view, 'receipt-order-number', receipt.order.number); setField(view, 'receipt-total', receipt.totals.total + ' ' + receipt.totals.currency);
                const container = view.querySelector('[data-component="receipt-items"]'); const template = view.querySelector('#coffeepos-receipt-item-template'); container.replaceChildren();
                (receipt.items || []).forEach(function (item) { const node = template.content.firstElementChild.cloneNode(true); setField(node, 'name', item.name); setField(node, 'quantity', item.quantity); setField(node, 'total', item.total); setField(node, 'note', item.note); container.appendChild(node); });
                view.hidden = false; window.print(); view.hidden = true;
            } catch (error) { showError(detailDialog.querySelector('[data-component="detail-error"]'), error); }
        }
        function openRefund() { if (!selected) { return; } refundForm.querySelector('[name="amount"]').value = selected.totals.refundable_amount; setField(refundDialog, 'refund-maximum', 'Maximum: ' + selected.totals.refundable.display); refundDialog.querySelector('[data-component="refund-error"]').hidden = true; refundDialog.showModal(); }
        function onClick(event) {
            const trigger = event.target.closest('[data-action]'); if (!trigger) { return; } const action = trigger.getAttribute('data-action');
            if (action === 'view-order') { const card=trigger.closest('[data-order-id]'); if(card){openDetail(Number(card.getAttribute('data-order-id')));} }
            else if (action === 'previous-page' && page > 1) { page--; load(); }
            else if (action === 'next-page' && page < pages) { page++; load(); }
            else if (action === 'close-detail') { detailDialog.close(); }
            else if (action === 'close-refund') { refundDialog.close(); }
            else if (action === 'refund-order') { openRefund(); }
            else if (action === 'reprint-order') { printSelected(); }
            else if (action === 'cancel-history-order' && selected) { detailDialog.close(); confirmDialog.open({title:'Cancel order #' + selected.number + '?',message:'This cancels the WooCommerce order but does not return money. Use Refund when money must be returned.',action:'cancel-order'}); }
            else if (action === 'reorder-order' && selected) { detailDialog.close(); confirmDialog.open({title:'Reorder order #' + selected.number + '?',message:'A new cashier cart will be created using current prices and availability.',action:'reorder-order'}); }
        }
        function onConfirm(event) { const action=String(event.detail&&event.detail.action||''); if(action==='cancel-order'){cancelSelected();} if(action==='reorder-order'){reorderSelected();} }
        function init() { filters.addEventListener('submit',function(event){event.preventDefault();page=1;load();}); root.addEventListener('click',onClick); root.addEventListener('coffeepos:confirm',onConfirm); refundForm.addEventListener('submit',submitRefund); load(); }
        return {init:init,getState:function(){return{page:page,pages:pages,orders:orders,selected:selected,pending:pending};}};
    };
    window.CoffeePOS = CoffeePOS;
}(window));
