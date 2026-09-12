(function (window) {
    'use strict';
    const CoffeePOS = window.CoffeePOS || {};
    const __ = window.wp.i18n.__;
    const sprintf = window.wp.i18n.sprintf;
    CoffeePOS.screens = CoffeePOS.screens || {};

    CoffeePOS.screens.createMembersController = function (root) {
        const client = CoffeePOS.api.createClient();
        const api = CoffeePOS.api.createPosApi(client);
        const detailPage = root.querySelector('.coffeepos-members__detail-page');
        const orderDialog = root.querySelector('[data-component="history-detail-dialog"]');
        const editDialog = root.querySelector('[data-component="member-edit-dialog"]');
        const itemTemplate = root.querySelector('template[data-template="history-detail-item"]');
        const quickEditForm = root.querySelector('[data-component="member-quick-edit-form"]');
        const pinResetForm = root.querySelector('[data-component="member-pin-reset-form"]');
        const pinDialog = root.querySelector('[data-component="member-pin-dialog"]');
        const config = window.CoffeePOSConfig || {};
        const pinChannel = window.BroadcastChannel && config.displayPairingScope
            ? new window.BroadcastChannel('coffeepos:member-pin:' + String(config.displayPairingScope))
            : null;
        let customerDisplayTransfer = null;

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
        async function resetTemporaryPin(event) {
            event.preventDefault();
            if (!pinResetForm || !pinDialog) { return; }
            const confirmation = pinResetForm.querySelector('[name="confirm_pin_reset"]');
            const customerId = pinResetForm.querySelector('[name="customer_id"]');
            const submit = pinResetForm.querySelector('[name="membership_action"]');
            const error = pinDialog.querySelector('[data-component="member-pin-error"]');
            if (!confirmation || !confirmation.checked || !customerId) {
                if (confirmation) { confirmation.focus(); }
                return;
            }
            error.hidden = true;
            if (submit) { submit.disabled = true; }
            pinResetForm.setAttribute('aria-busy', 'true');
            try {
                const data = await client.request('members/' + encodeURIComponent(customerId.value) + '/temporary-pin', {
                    method: 'POST',
                    body: {confirmed: true}
                });
                field(pinDialog, 'temporary-pin', data.temporary_pin || '');
                const displayButton = pinDialog.querySelector('[data-action="show-member-pin-customer"]');
                const copyButton = pinDialog.querySelector('[data-action="copy-member-pin"]');
                if (displayButton) { displayButton.disabled = false; displayButton.textContent = __('Show on Customer Display', 'coffeepos'); }
                if (copyButton) { copyButton.textContent = __('Copy PIN', 'coffeepos'); }
                confirmation.checked = false;
                const status = root.querySelector('[data-component="member-pin-status"]');
                if (status) { status.textContent = __('A temporary PIN is active and must be changed on first login.', 'coffeepos'); }
                pinDialog.showModal();
            } catch (failure) {
                field(pinDialog, 'temporary-pin', '');
                error.textContent = failure && failure.message ? failure.message : __('The member PIN could not be saved.', 'coffeepos');
                error.hidden = false;
                pinDialog.showModal();
            } finally {
                if (submit) { submit.disabled = false; }
                pinResetForm.removeAttribute('aria-busy');
            }
        }
        async function copyTemporaryPin(trigger) {
            const value = pinDialog ? pinDialog.querySelector('[data-field="temporary-pin"]') : null;
            const pin = value ? value.textContent.trim() : '';
            if (!pin) { return; }
            try {
                if (window.navigator.clipboard && window.isSecureContext) {
                    await window.navigator.clipboard.writeText(pin);
                } else {
                    const range = document.createRange();
                    range.selectNodeContents(value);
                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                    document.execCommand('copy');
                    selection.removeAllRanges();
                }
                trigger.textContent = __('Copied', 'coffeepos');
            } catch (failure) {
                trigger.textContent = __('Copy failed', 'coffeepos');
            }
        }
        function showTemporaryPinOnCustomerDisplay(trigger) {
            const value = pinDialog ? pinDialog.querySelector('[data-field="temporary-pin"]') : null;
            const pin = value ? value.textContent.trim() : '';
            if (!pin || !pinChannel) {
                trigger.textContent = __('Customer Display unavailable', 'coffeepos');
                return;
            }
            const requestId = window.crypto && typeof window.crypto.randomUUID === 'function'
                ? window.crypto.randomUUID()
                : String(Date.now()) + '-' + Math.random().toString(16).slice(2);
            customerDisplayTransfer = {requestId: requestId, trigger: trigger};
            trigger.disabled = true;
            trigger.textContent = __('Sending to Customer Display…', 'coffeepos');
            pinChannel.postMessage({type: 'coffeepos:show-member-pin', request_id: requestId, pin: pin});
            window.setTimeout(function () {
                if (!customerDisplayTransfer || customerDisplayTransfer.requestId !== requestId) { return; }
                trigger.disabled = false;
                trigger.textContent = __('Open Customer Display first', 'coffeepos');
                customerDisplayTransfer = null;
            }, 1500);
        }
        function onCustomerDisplayMessage(event) {
            if (!customerDisplayTransfer) { return; }
            const message = event.data && typeof event.data === 'object' ? event.data : {};
            if (message.type !== 'coffeepos:member-pin-shown' || message.request_id !== customerDisplayTransfer.requestId) { return; }
            customerDisplayTransfer.trigger.disabled = false;
            customerDisplayTransfer.trigger.textContent = __('Shown on Customer Display', 'coffeepos');
            customerDisplayTransfer = null;
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
            if (action === 'close-member-pin' && pinDialog) { pinDialog.close(); field(pinDialog, 'temporary-pin', ''); customerDisplayTransfer = null; }
            if (action === 'copy-member-pin' && pinDialog) { copyTemporaryPin(trigger); }
            if (action === 'show-member-pin-customer' && pinDialog) { showTemporaryPinOnCustomerDisplay(trigger); }
        }
        function init() {
            root.addEventListener('click', onClick);
            if (pinChannel) { pinChannel.addEventListener('message', onCustomerDisplayMessage); }
            if (pinResetForm) { pinResetForm.addEventListener('submit', resetTemporaryPin); }
            if (pinDialog) { pinDialog.addEventListener('close', function () { field(pinDialog, 'temporary-pin', ''); customerDisplayTransfer = null; }); }
            if (detailPage && detailPage.getAttribute('data-member-quick-edit') === '1' && editDialog) {
                editDialog.showModal();
            }
        }
        return {init: init, getState: function () { return {detail: !!detailPage}; }};
    };
    window.CoffeePOS = CoffeePOS;
}(window));
