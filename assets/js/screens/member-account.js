(function (window, document) {
    'use strict';

    const root = document.querySelector('[data-component="member-account"]');
    const config = window.CoffeePOSMemberConfig || {};

    if (!root) {
        return;
    }

    const renderer = new window.CoffeePOS.ui.TemplateRenderer();
    const panels = Array.from(root.querySelectorAll('[data-panel]'));
    const loginForm = root.querySelector('[data-component="member-login-form"]');
    const pinForm = root.querySelector('[data-component="member-pin-form"]');
    const logoutButton = root.querySelector('[data-action="logout"]');
    const cancelPinButton = root.querySelector('[data-action="cancel-change-pin"]');
    const globalStatus = root.querySelector('[data-component="global-status"]');
    const orderDialog = document.querySelector('[data-component="member-order-dialog"]');
    let csrfToken = '';
    let orderPage = 1;
    let orderPages = 0;
    let returnToDashboard = false;

    function message(key, fallback) {
        return config.i18n && config.i18n[key] ? String(config.i18n[key]) : fallback;
    }

    async function request(path, options) {
        const settings = options || {};
        const headers = { Accept: 'application/json' };
        if (settings.body !== undefined) {
            headers['Content-Type'] = 'application/json';
        }
        if (settings.csrf && csrfToken) {
            headers['X-CoffeePOS-Member-CSRF'] = csrfToken;
        }

        let response;
        try {
            response = await window.fetch(String(config.restBase || '') + String(path || ''), {
                method: settings.method || 'GET',
                credentials: 'same-origin',
                headers: headers,
                body: settings.body === undefined ? undefined : JSON.stringify(settings.body)
            });
        } catch (error) {
            throw { code: 'network_error', message: message('requestFailed', 'The request could not be completed.'), status: 0 };
        }

        let envelope;
        try {
            envelope = await response.json();
        } catch (error) {
            throw { code: 'invalid_response', message: message('requestFailed', 'The request could not be completed.'), status: response.status };
        }
        if (!response.ok || !envelope || envelope.success !== true) {
            const failure = envelope && envelope.error ? envelope.error : {};
            throw {
                code: String(failure.code || 'request_failed'),
                message: String(failure.message || message('requestFailed', 'The request could not be completed.')),
                status: response.status,
                details: failure.details || {}
            };
        }

        return envelope.data || {};
    }

    function showPanel(name) {
        panels.forEach(function (panel) {
            panel.hidden = panel.getAttribute('data-panel') !== name;
        });
        logoutButton.hidden = name === 'login';
        root.setAttribute('data-state', name);
        const visible = root.querySelector('[data-panel="' + name + '"]');
        const heading = visible ? visible.querySelector('h1') : null;
        if (heading) {
            heading.setAttribute('tabindex', '-1');
            heading.focus();
        }
    }

    function setError(element, value) {
        if (!element) {
            return;
        }
        element.textContent = value || '';
        element.hidden = !value;
    }

    function setBusy(form, busy) {
        if (!form) {
            return;
        }
        Array.from(form.elements).forEach(function (element) { element.disabled = busy; });
        form.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function normalizePhoneInput(value) {
        return String(value || '')
            .replace(/[\u00a0\u200b-\u200d\u202f\u2060\ufeff\s]+/g, '')
            .trim();
    }

    function handleSessionFailure(error) {
        if (error && ['member_session_required', 'member_session_expired'].indexOf(error.code) !== -1) {
            csrfToken = '';
            globalStatus.textContent = message('sessionExpired', 'Your member session has expired. Please sign in again.');
            showPanel('login');
            return true;
        }
        return false;
    }

    async function bootstrap() {
        globalStatus.textContent = '';
        try {
            const session = await request('auth/session');
            csrfToken = String(session.csrf_token || '');
            if (session.pin_change_required) {
                returnToDashboard = false;
                cancelPinButton.hidden = true;
                showPanel('change-pin');
                return;
            }
            showPanel('dashboard');
            await loadDashboard();
        } catch (error) {
            csrfToken = '';
            showPanel('login');
        }
    }

    async function loadDashboard() {
        globalStatus.textContent = '';
        try {
            const results = await Promise.all([request('account'), loadOrders(1)]);
            renderAccount(results[0]);
        } catch (error) {
            if (!handleSessionFailure(error)) {
                globalStatus.textContent = error.message || message('requestFailed', 'The request could not be completed.');
            }
        }
    }

    function renderAccount(data) {
        const member = data.member || {};
        const membership = data.membership || {};
        root.querySelector('[data-field="member-name"]').textContent = String(member.display_name || '');
        root.querySelector('[data-field="member-phone"]').textContent = String(member.phone_masked || '');
        root.querySelector('[data-field="tier-label"]').textContent = String(membership.tier_label || '');
        root.querySelector('[data-field="lifetime-spend"]').textContent = String(membership.lifetime_spend_display || '');
        root.querySelector('[data-field="next-tier"]').textContent = String(membership.next_tier_label || '—');
        const remaining = String(membership.remaining_spend_display || '');
        root.querySelector('[data-field="tier-progress-copy"]').textContent = remaining
            ? remaining + ' to ' + String(membership.next_tier_label || '')
            : String(membership.tier_label || '');
        root.querySelector('[data-component="tier-progress-bar"]').style.width = Math.max(0, Math.min(100, Number(membership.progress_percent || 0))) + '%';
        root.querySelector('[data-component="next-tier-row"]').hidden = !membership.next_tier_label;
    }

    async function loadOrders(page) {
        const target = root.querySelector('[data-component="member-orders"]');
        const status = root.querySelector('[data-component="orders-message"]');
        status.textContent = '…';
        try {
            const data = await request('orders?page=' + encodeURIComponent(String(page)) + '&per_page=10');
            orderPage = Number(data.page || 1);
            orderPages = Number(data.pages || 0);
            const output = document.createDocumentFragment();
            (data.items || []).forEach(function (order) {
                const fragment = renderer.render('coffeepos-member-order-row', order);
                const button = fragment.querySelector('[data-action="view-order"]');
                button.setAttribute('data-order-id', String(order.id));
                output.appendChild(fragment);
            });
            target.replaceChildren(output);
            status.textContent = (data.items || []).length === 0 ? message('emptyOrders', 'You do not have any CoffeePOS orders yet.') : '';
            root.querySelector('[data-field="order-page"]').textContent = orderPages > 0 ? orderPage + ' / ' + orderPages : '';
            root.querySelector('[data-action="previous-orders"]').disabled = orderPage <= 1;
            root.querySelector('[data-action="next-orders"]').disabled = orderPages === 0 || orderPage >= orderPages;
            return data;
        } catch (error) {
            status.textContent = error.message || message('requestFailed', 'The request could not be completed.');
            if (handleSessionFailure(error)) {
                return {};
            }
            throw error;
        }
    }

    async function openOrder(orderId) {
        globalStatus.textContent = '';
        try {
            const data = await request('orders/' + encodeURIComponent(String(orderId)));
            const order = data.order || {};
            orderDialog.querySelector('[data-field="order-status"]').textContent = String(order.status_label || '');
            orderDialog.querySelector('[data-field="order-number"]').textContent = String(order.number || '');
            orderDialog.querySelector('[data-field="order-created"]').textContent = String(order.created_at_display || '');
            ['subtotal', 'discount', 'refunded', 'total'].forEach(function (key) {
                const value = order.totals && order.totals[key] ? order.totals[key].display : '';
                orderDialog.querySelector('[data-field="order-' + key + '"]').textContent = String(value || '');
            });
            renderer.renderList('coffeepos-member-order-item', order.items || [], orderDialog.querySelector('[data-component="order-items"]'));
            orderDialog.showModal();
        } catch (error) {
            if (!handleSessionFailure(error)) {
                globalStatus.textContent = error.message || message('requestFailed', 'The request could not be completed.');
            }
        }
    }

    loginForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const errorElement = loginForm.querySelector('[data-component="login-error"]');
        setError(errorElement, '');
        const phoneInput = loginForm.querySelector('[name="phone"]');
        const pinInput = loginForm.querySelector('[name="pin"]');
        const phone = normalizePhoneInput(phoneInput ? phoneInput.value : '');
        const pin = pinInput ? pinInput.value : '';
        if (phoneInput) {
            phoneInput.value = phone;
        }
        setBusy(loginForm, true);
        try {
            const session = await request('auth/login', { method: 'POST', body: { phone: phone, pin: pin } });
            csrfToken = String(session.csrf_token || '');
            loginForm.reset();
            if (session.pin_change_required) {
                returnToDashboard = false;
                cancelPinButton.hidden = true;
                showPanel('change-pin');
            } else {
                showPanel('dashboard');
                await loadDashboard();
            }
        } catch (error) {
            setError(errorElement, error.message || message('requestFailed', 'The request could not be completed.'));
        } finally {
            setBusy(loginForm, false);
        }
    });

    loginForm.addEventListener('input', function () {
        setError(loginForm.querySelector('[data-component="login-error"]'), '');
    });

    pinForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        const errorElement = pinForm.querySelector('[data-component="pin-error"]');
        setError(errorElement, '');
        const newPinInput = pinForm.querySelector('[name="new_pin"]');
        const confirmationInput = pinForm.querySelector('[name="new_pin_confirmation"]');
        const newPin = newPinInput ? newPinInput.value : '';
        const confirmation = confirmationInput ? confirmationInput.value : '';
        setBusy(pinForm, true);
        try {
            const session = await request('auth/change-pin', {
                method: 'POST',
                csrf: true,
                body: { new_pin: newPin, new_pin_confirmation: confirmation }
            });
            csrfToken = String(session.csrf_token || '');
            pinForm.reset();
            showPanel('dashboard');
            await loadDashboard();
            returnToDashboard = false;
        } catch (error) {
            if (!handleSessionFailure(error)) {
                setError(errorElement, error.message || message('requestFailed', 'The request could not be completed.'));
            }
        } finally {
            setBusy(pinForm, false);
        }
    });

    root.addEventListener('click', function (event) {
        const action = event.target.closest('[data-action]');
        if (!action || !root.contains(action)) {
            return;
        }
        const name = action.getAttribute('data-action');
        if (name === 'view-order') { openOrder(action.getAttribute('data-order-id')); }
        if (name === 'refresh-orders') { loadOrders(orderPage); }
        if (name === 'previous-orders' && orderPage > 1) { loadOrders(orderPage - 1); }
        if (name === 'next-orders' && orderPage < orderPages) { loadOrders(orderPage + 1); }
        if (name === 'show-change-pin') { returnToDashboard = true; cancelPinButton.hidden = false; showPanel('change-pin'); }
        if (name === 'cancel-change-pin' && returnToDashboard) { pinForm.reset(); setError(pinForm.querySelector('[data-component="pin-error"]'), ''); returnToDashboard = false; showPanel('dashboard'); }
    });

    logoutButton.addEventListener('click', async function () {
        logoutButton.disabled = true;
        try {
            await request('auth/logout', { method: 'POST', csrf: true, body: {} });
        } catch (error) {
            if (!handleSessionFailure(error)) {
                globalStatus.textContent = error.message || message('requestFailed', 'The request could not be completed.');
                logoutButton.disabled = false;
                return;
            }
        }
        csrfToken = '';
        logoutButton.disabled = false;
        showPanel('login');
    });

    orderDialog.addEventListener('click', function (event) {
        if (event.target.closest('[data-action="close-order"]')) {
            orderDialog.close();
        }
    });

    bootstrap();
}(window, document));
