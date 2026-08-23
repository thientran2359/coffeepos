(function (window) {
    'use strict';

    const CoffeePOS = window.CoffeePOS || {};

    function ApiError(code, message, status, details) {
        this.name = 'CoffeePOSApiError';
        this.code = code || 'request_failed';
        this.message = message || 'The request failed.';
        this.status = Number(status || 0);
        this.details = details && typeof details === 'object' ? details : {};

        if (Error.captureStackTrace) {
            Error.captureStackTrace(this, ApiError);
        }
    }

    ApiError.prototype = Object.create(Error.prototype);
    ApiError.prototype.constructor = ApiError;

    CoffeePOS.api = CoffeePOS.api || {};

    CoffeePOS.api.createClient = function () {
        const config = window.CoffeePOSConfig && typeof window.CoffeePOSConfig === 'object'
            ? window.CoffeePOSConfig
            : {};
        const baseUrl = String(config.restBase || '').replace(/\/+$/, '') + '/';

        async function request(path, options) {
            const settings = options || {};
            const headers = {
                Accept: 'application/json'
            };

            if (config.restNonce) {
                headers['X-WP-Nonce'] = String(config.restNonce);
            }

            if (settings.body !== undefined) {
                headers['Content-Type'] = 'application/json';
            }

            let response;

            try {
                response = await window.fetch(baseUrl + String(path || '').replace(/^\/+/, ''), {
                    method: settings.method || 'GET',
                    credentials: 'same-origin',
                    headers: headers,
                    body: settings.body === undefined ? undefined : JSON.stringify(settings.body),
                    signal: settings.signal
                });
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    throw error;
                }

                throw new ApiError('network_error', 'The server could not be reached.', 0, {});
            }

            let envelope;

            try {
                envelope = await response.json();
            } catch (error) {
                throw new ApiError('invalid_response', 'The server returned an invalid response.', response.status, {});
            }

            if (!response.ok || !envelope || envelope.success !== true) {
                const responseError = envelope && envelope.error && typeof envelope.error === 'object'
                    ? envelope.error
                    : {};

                throw new ApiError(
                    responseError.code || 'request_failed',
                    responseError.message || 'The request failed.',
                    response.status,
                    responseError.details || {}
                );
            }

            if (!envelope.data || typeof envelope.data !== 'object' || Array.isArray(envelope.data)) {
                throw new ApiError('invalid_response', 'The server returned an invalid data envelope.', response.status, {});
            }

            return envelope.data;
        }

        async function download(path, signal) {
            const headers = {};
            if (config.restNonce) {
                headers['X-WP-Nonce'] = String(config.restNonce);
            }
            let response;
            try {
                response = await window.fetch(baseUrl + String(path || '').replace(/^\/+/, ''), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: headers,
                    signal: signal
                });
            } catch (error) {
                if (error && error.name === 'AbortError') {
                    throw error;
                }
                throw new ApiError('network_error', 'The server could not be reached.', 0, {});
            }

            if (!response.ok) {
                let envelope = null;
                try {
                    envelope = await response.json();
                } catch (ignored) {
                    envelope = null;
                }
                const responseError = envelope && envelope.error && typeof envelope.error === 'object' ? envelope.error : {};
                throw new ApiError(responseError.code || 'request_failed', responseError.message || 'The download failed.', response.status, responseError.details || {});
            }

            const disposition = String(response.headers.get('Content-Disposition') || '');
            const match = disposition.match(/filename="?([^";]+)"?/i);
            return {
                blob: await response.blob(),
                filename: match ? match[1] : 'coffeepos-report'
            };
        }

        return { request: request, download: download };
    };

    CoffeePOS.api.createPosApi = function (client) {
        function query(params) {
            const search = new window.URLSearchParams();

            Object.keys(params || {}).forEach(function (key) {
                const value = params[key];

                if (value !== undefined && value !== null && value !== '') {
                    search.set(key, String(value));
                }
            });

            const value = search.toString();
            return value === '' ? '' : '?' + value;
        }

        return {
            loadCatalog: function (signal) {
                return client.request('catalog', { signal: signal });
            },
            loadProduct: function (productId, signal) {
                return client.request('products/' + encodeURIComponent(String(productId)), { signal: signal });
            },
            resolveVariation: function (productId, attributes, signal) {
                return client.request('products/' + encodeURIComponent(String(productId)) + '/variation', {
                    method: 'POST',
                    body: { attributes: attributes },
                    signal: signal
                });
            },
            createCartSession: function () {
                return client.request('cart/session', { method: 'POST', body: {} });
            },
            getCart: function (posSessionId) {
                return client.request('cart' + query({ pos_session_id: posSessionId }));
            },
            getCustomerCart: function (posSessionId, signal) {
                return client.request('cart' + query({ pos_session_id: posSessionId, view: 'customer' }), { signal: signal });
            },
            addCartItem: function (payload) {
                return client.request('cart/items', { method: 'POST', body: payload });
            },
            updateCartItem: function (itemId, payload) {
                return client.request('cart/items/' + encodeURIComponent(String(itemId)), {
                    method: 'PATCH',
                    body: payload
                });
            },
            removeCartItem: function (itemId, payload) {
                return client.request('cart/items/' + encodeURIComponent(String(itemId)), {
                    method: 'DELETE',
                    body: payload
                });
            },
            clearCart: function (payload) {
                return client.request('cart', { method: 'DELETE', body: payload });
            },
            setOrderNote: function (payload) {
                return client.request('cart/order-note', { method: 'PUT', body: payload });
            },
            clearOrderNote: function (payload) {
                return client.request('cart/order-note', { method: 'DELETE', body: payload });
            },
            lookupCustomer: function (phone, signal) {
                return client.request('customers/lookup' + query({ phone: phone }), { signal: signal });
            },
            createCustomer: function (payload) {
                return client.request('customers', { method: 'POST', body: payload });
            },
            loadTables: function (signal) {
                return client.request('tables', { signal: signal });
            },
            attachCustomer: function (payload) {
                return client.request('cart/customer', { method: 'PUT', body: payload });
            },
            removeCustomer: function (payload) {
                return client.request('cart/customer', { method: 'DELETE', body: payload });
            },
            setServiceContext: function (payload) {
                return client.request('cart/service-context', { method: 'PUT', body: payload });
            },
            loadApplicableCoupons: function (posSessionId, signal) {
                return client.request('coupons/applicable' + query({ pos_session_id: posSessionId }), { signal: signal });
            },
            applyCoupon: function (payload) {
                return client.request('cart/coupon', { method: 'POST', body: payload });
            },
            removeCoupon: function (payload) {
                return client.request('cart/coupon', { method: 'DELETE', body: payload });
            },
            checkout: function (payload) {
                return client.request('orders/checkout', { method: 'POST', body: payload });
            },
            previewVietQr: function (payload) {
                return client.request('payments/vietqr-preview', { method: 'POST', body: payload });
            },
            paymentStatus: function (orderId, signal) {
                return client.request('orders/' + encodeURIComponent(String(orderId)) + '/payment', { signal: signal });
            },
            loadReceipt: function (orderId, signal) {
                return client.request('orders/' + encodeURIComponent(String(orderId)) + '/receipt', { signal: signal });
            },
            loadKdsOrders: function (states, signal) {
                return client.request('kds/orders' + query({ states: (states || []).join(','), limit: 100 }), { signal: signal });
            },
            transitionKdsOrder: function (orderId, payload) {
                return client.request('kds/orders/' + encodeURIComponent(String(orderId)) + '/transition', { method: 'POST', body: payload });
            },
            loadOrderQueue: function (filters, signal) {
                const value = filters || {};
                return client.request('order-queue/orders' + query({ kds_state: value.kds_state || 'all', order_type: value.order_type || 'all', limit: 100 }), { signal: signal });
            },
            completeOperationalOrder: function (orderId, payload) {
                return client.request('orders/' + encodeURIComponent(String(orderId)) + '/complete', { method: 'POST', body: payload });
            },
            cancelOperationalOrder: function (orderId, payload) {
                return client.request('orders/' + encodeURIComponent(String(orderId)) + '/cancel', { method: 'POST', body: payload });
            },
            getCurrentShift: function () {
                return client.request('shifts/current');
            },
            openShift: function (payload) {
                return client.request('shifts/open', { method: 'POST', body: payload });
            },
            closeShift: function (shiftId, payload) {
                return client.request('shifts/' + encodeURIComponent(String(shiftId)) + '/close', { method: 'POST', body: payload });
            },
            loadShiftHistory: function (limit) {
                return client.request('shifts/history' + query({ limit: limit || 50 }));
            },
            loadOrderHistory: function (filters, signal) {
                return client.request('orders' + query(filters || {}), { signal: signal });
            },
            loadOrderDetail: function (orderId, signal) {
                return client.request('orders/' + encodeURIComponent(String(orderId)), { signal: signal });
            },
            refundOrder: function (orderId, payload) {
                return client.request('orders/' + encodeURIComponent(String(orderId)) + '/refund', { method: 'POST', body: payload });
            },
            reorderOrder: function (orderId, payload) {
                return client.request('orders/' + encodeURIComponent(String(orderId)) + '/reorder', { method: 'POST', body: payload });
            },
            loadSalesReport: function (filters, signal) {
                return client.request('reports/sales' + query(filters || {}), { signal: signal });
            },
            downloadSalesReport: function (filters, signal) {
                return client.download('reports/sales/export' + query(filters || {}), signal);
            }
        };
    };

    CoffeePOS.api.ApiError = ApiError;
    window.CoffeePOS = CoffeePOS;
}(window));
