(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    const sprintf = window.wp.i18n.sprintf;
    CoffeePOS.screens = CoffeePOS.screens || {};

    CoffeePOS.screens.createMembersController = function (root) {
        const api = CoffeePOS.api.createPosApi(CoffeePOS.api.createClient());
        const detailPage = root.querySelector('.coffeepos-members__detail-page');
        const orderDialog = root.querySelector('[data-component="history-detail-dialog"]');
        const editDialog = root.querySelector('[data-component="member-edit-dialog"]');
        const itemTemplate = root.querySelector('template[data-template="history-detail-item"]');
        const quickEditForm = root.querySelector('[data-component="member-quick-edit-form"]');

        function field(parent, name, value) {
            const node = parent && parent.querySelector('[data-field="' + name + '"]');
            if (node) { node.textContent = String(value === undefined || value === null ? '' : value); }
        }
        function service(order) {
            const context = order.service || {};
            return context.order_type === 'dine_in'
                ? __('Dine-in', 'coffeepos') + (context.table_label ? ' · ' + context.table_label : '')
                : __('Takeaway', 'coffeepos');
        }
        function renderOrder(order) {
            field(orderDialog, 'detail-status', order.status_label);
            field(orderDialog, 'detail-number', sprintf(__('Order #%s', 'coffeepos'), order.number));
            field(orderDialog, 'detail-created', order.created_at_display || new Date(order.created_at).toLocaleString());
            field(orderDialog, 'detail-customer', order.customer.display_name || __('Guest', 'coffeepos'));
            field(orderDialog, 'detail-service', service(order));
            field(orderDialog, 'detail-payment', order.payment.method_label || order.payment.method || '—');
            field(orderDialog, 'detail-subtotal', order.totals.subtotal.display);
            field(orderDialog, 'detail-discount', order.totals.discount.display);
            field(orderDialog, 'detail-refunded', order.totals.refunded.display);
            field(orderDialog, 'detail-total', order.totals.total.display);
            const items = orderDialog.querySelector('[data-component="detail-items"]');
            items.replaceChildren();
            (order.items || []).forEach(function (item) {
                const node = itemTemplate.content.firstElementChild.cloneNode(true);
                field(node, 'name', item.name); field(node, 'quick_notes', item.quick_note_summary || '');
                field(node, 'note', item.note || ''); field(node, 'quantity', '× ' + item.quantity);
                field(node, 'total', item.total.display); items.appendChild(node);
            });
            const note = orderDialog.querySelector('[data-component="history-order-note"]');
            field(orderDialog, 'detail-order-note', order.order_note || '');
            note.hidden = !order.order_note;
        }
        async function openOrder(id) {
            const error = orderDialog.querySelector('[data-component="detail-error"]');
            error.hidden = true;
            try {
                const data = await api.loadOrderDetail(id);
                renderOrder(data.order);
                orderDialog.showModal();
            } catch (failure) {
                error.textContent = failure && failure.message ? failure.message : __('The request could not be completed.', 'coffeepos');
                error.hidden = false;
                orderDialog.showModal();
            }
        }
        function onClick(event) {
            const trigger = event.target.closest('[data-action]');
            if (!trigger) { return; }
            const action = trigger.getAttribute('data-action');
            if (action === 'quick-edit-member') {
                event.preventDefault();
                let member = null;
                try { member = JSON.parse(trigger.getAttribute('data-member') || 'null'); } catch (error) { member = null; }
                if (member && quickEditForm && editDialog) {
                    quickEditForm.elements.customer_id.value = String(member.id || '');
                    quickEditForm.elements.member_name.value = String(member.name || '');
                    quickEditForm.elements.member_phone.value = String(member.phone || '');
                    quickEditForm.elements.member_email.value = String(member.email || '');
                    quickEditForm.elements.member_tier.value = String(member.tier || '');
                    quickEditForm.elements.member_reason.value = '';
                    editDialog.showModal();
                }
            }
            if (action === 'view-member-order') { openOrder(Number(trigger.getAttribute('data-order-id'))); }
            if (action === 'close-order-detail') { orderDialog.close(); }
            if (action === 'close-member-edit') { editDialog.close(); }
        }
        function init() {
            root.addEventListener('click', onClick);
            if (detailPage && detailPage.getAttribute('data-member-quick-edit') === '1' && editDialog) {
                editDialog.showModal();
            }
        }
        return {init: init, getState: function () { return {detail: !!detailPage}; }};
    };
    window.CoffeePOS = CoffeePOS;
}(window));
