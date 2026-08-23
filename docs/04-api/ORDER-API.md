# ORDER-API.md

# CoffeePOS Order API

## Approved pre-order VietQR API (2026-08-23)

`POST /coffeepos/v1/payments/vietqr-preview` accepts only
`pos_session_id` and `expected_revision`. It reloads and validates the active
cart, calculates the authoritative total, and returns a customer-safe
`payment` projection with `state=awaiting_cashier_confirmation`. It creates no
order and persists no payment transaction. The projection includes a trusted
`summary.subtotal`, `summary.discount`, and `summary.total`, each with
`amount_minor` and WooCommerce-formatted `display`, so the Customer Display
summary is based on the same pricing result as the VietQR amount.

Final bank checkout uses `POST /coffeepos/v1/orders/checkout` with
`payment.method=bank_transfer` and `payment.confirmed_received=true`. The route
requires the POS capability; the authorized cashier confirmation is the trusted
manual input. The server revalidates the same revision, creates one order,
marks it paid, records cashier/time audit metadata, completes the source cart,
and returns a fresh cart. Client-supplied totals or paid states remain forbidden.

## 1. Purpose

This document defines the server contract for turning a POS cart into a WooCommerce order and performing operational order actions.

---

# 2. Checkout

## POST /coffeepos/v1/orders/checkout

Purpose:

Validate the POS cart, create the WooCommerce order, and initialize the selected payment workflow.

Request concept:

```json
{
  "pos_session_id": "01J...",
  "expected_revision": 12,
  "client_operation_id": "cashier-20260822-abc123",
  "payment": {
    "method": "cash"
  }
}
```

The browser does not submit an authoritative cart snapshot. The server loads
the identified cart from the authenticated WooCommerce session.

---

# 3. Checkout Server Flow

```text
Authenticate
 ↓
Authorize
 ↓
Validate request
 ↓
Load WooCommerce session cart
 ↓
Verify pos_session_id + expected_revision
 ↓
Validate cart
 ↓
Validate products/variations
 ↓
Validate stock
 ↓
Validate coupons
 ↓
Validate customer
 ↓
Validate order type/table
 ↓
Create WooCommerce order
 ↓
Create/populate order items
 ↓
Attach POS metadata
 ↓
Initialize payment
 ↓
Return checkout result
```

---

# 4. Checkout Response

Conceptual success:

```json
{
  "success": true,
  "data": {
    "order": {
      "id": 1001,
      "number": "1001",
      "status": "processing",
      "total": "85000.00"
    },
    "payment": {
      "method": "cash",
      "state": "paid",
      "amount": "85000.00",
      "change": "15000.00"
    },
    "receipt": {
      "available": true
    }
  }
}
```

For bank transfer:

```json
{
  "success": true,
  "data": {
    "order": {},
    "payment": {
      "method": "bank_transfer",
      "state": "pending",
      "amount": "85000.00",
      "qr": {
        "payload": "..."
      }
    }
  }
}
```

---

# 5. Cash Checkout

Request:

```json
{
  "payment": {
    "method": "cash",
    "received_amount": "100000"
  }
}
```

Server must validate:

```text
received >= total
```

Server computes:

```text
change = received - total
```

The UI may preview this calculation but must not be authoritative.

---

# 6. Bank Transfer Checkout

Initial flow:

```text
Checkout
 ↓
Order/payment context
 ↓
VietQR generation
 ↓
PENDING
 ↓
Payment verification
 ↓
PAID
```

The exact verification provider is not defined in the feature source.

Therefore the first implementation must keep the payment gateway behind an abstraction and must not pretend that showing a QR means payment is complete.

---

# 7. Payment Completion

## POST /coffeepos/v1/orders/{id}/payment/complete

Use only when the payment integration defines a trusted completion operation.

Request may contain:

```text
provider reference
payment transaction reference
```

Server validates the payment.

The endpoint MUST NOT simply trust:

```json
{
  "paid": true
}
```

from the browser.

---

# 8. Order Detail

## GET /coffeepos/v1/orders/{id}

Response should include:

```text
order
customer
order_type
table
items
totals
payment
POS context
```

Only authorized users may retrieve privileged order data.

---

# 9. Order List

## GET /coffeepos/v1/orders

Supported filters:

```text
page
per_page
status
date_from
date_to
search
order_type
```

The exact set should match the Order History requirements.

---

# 10. Order Completion

## POST /coffeepos/v1/orders/{id}/complete

Purpose:

Complete an order from an operational screen where policy permits.

Server must:

- authorize
- verify valid state transition
- update WooCommerce/order workflow
- emit resulting state

---

# 11. Order Cancellation

## POST /coffeepos/v1/orders/{id}/cancel

Server must:

- authorize
- verify order state
- cancel through WooCommerce mechanisms
- prevent invalid repeated cancellation

---

# 12. Refund

## POST /coffeepos/v1/orders/{id}/refund

Request concept:

```json
{
  "amount": "85000.00",
  "reason": "Customer request",
  "items": []
}
```

WooCommerce refund APIs remain authoritative.

Do not mark an order refunded merely because the request was submitted.

---

# 13. Quick Reorder

## POST /coffeepos/v1/orders/{id}/reorder

Purpose:

Convert a historical order into a new cart projection.

Flow:

```text
Historical Order
 ↓
Load items
 ↓
Revalidate current products
 ↓
Revalidate current variations
 ↓
Revalidate current stock
 ↓
Reconstruct cart
```

Historical prices MUST NOT be copied as authoritative current prices.

The reconstructed cart is written into a logical WooCommerce session cart and
returned with `pos_session_id` and a new revision.

---

# 14. Receipt

## GET /coffeepos/v1/orders/{id}/receipt

Returns receipt data for printing/rendering.

Receipt data should be derived from the WooCommerce order.

---

# 15. Error Cases

Relevant codes:

```text
empty_cart
invalid_cart
cart_session_not_found
cart_revision_conflict
out_of_stock
invalid_variation
invalid_coupon
order_creation_failed
payment_failed
payment_pending
invalid_order_state
order_not_found
refund_failed
unauthorized
```

---

# 16. Duplicate Checkout Protection

Checkout MUST use an idempotency/client-operation identifier.

Example:

```json
{
  "client_operation_id": "cashier-20260822-abc123",
  "pos_session_id": "01J...",
  "expected_revision": 12,
  "payment": {}
}
```

A repeated request with the same operation identifier should not create another order.

The identifier and resulting order ID must be persisted atomically enough to
survive request retries. A repeated request returns the original result rather
than creating another order or payment attempt.

---

# 17. Order Context

Order creation should persist relevant POS context:

```text
order type
table
shift
cashier
POS payment method/reference
item notes
modifier configuration
quick notes
```

All keys must match `DATABASE.md`.

---

# 18. Phase-05 Finalized Contracts

Checkout persists `_coffeepos_operation_id` and a normalized request hash in
WooCommerce order metadata, queries through `wc_get_orders()`, and uses a short
installation-scoped database advisory lock for the lookup/create section. The
same key with different material input returns
`duplicate_operation_conflict`.

Paid checkout responses include `next_cart`, a fresh empty `CartView`. Its
session ID is persisted on the order, so an identical retry returns the same
fresh session instead of creating another cart.

Read-only recovery routes are:

```text
GET /coffeepos/v1/orders/{id}/payment
GET /coffeepos/v1/orders/{id}/receipt
```

Both require CoffeePOS capability. Payment state is derived from the
WooCommerce order. The fallback bank-transfer adapter can initialize a
configured VietQR and report pending/provider-unavailable, but exposes no
browser operation that can mark an order paid. Receipt data comes only from the
saved order.
