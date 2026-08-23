# ORDER-API.md

# CoffeePOS Order API

## Phase-07 operational order API (2026-08-23)

Protected list routes:

```text
GET /coffeepos/v1/kds/orders?states=new,preparing,ready&limit=100
GET /coffeepos/v1/order-queue/orders?kds_state=all&order_type=all&limit=100
```

Both return `server_time`, effective `poll_interval_ms`, and a bounded `orders`
projection. Default limit is 100; maximum is 200. Only paid CoffeePOS orders in
active WooCommerce/operational state are returned.

Mutation routes:

```text
POST /coffeepos/v1/kds/orders/{id}/transition
POST /coffeepos/v1/orders/{id}/complete
POST /coffeepos/v1/orders/{id}/cancel
```

Each mutation requires `expected_state`, `expected_revision`, and a valid
`client_operation_id`; KDS transition also requires `target_state`. Valid state
flow is `new -> preparing -> ready -> completed`, with cancellation allowed
only from `new` or `preparing`. A stale request returns HTTP 409
`order_state_conflict` with the current safe KDS projection where available.
Successful mutations return `order` and an optional active `queue_order`.

KDS items expose saved order-item name, quantity, variation, modifiers, quick
notes, and custom note as plain text. Queue items expose order number, customer
display name, service/table, received time, WooCommerce total, KDS revision/state,
receipt availability, and server-derived allowed actions. Private billing and
payment-provider data are excluded.

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

For a Phase-08 member cart, validation reloads the WooCommerce customer selected
by canonical CustomerContext. Order creation passes that customer ID to
WooCommerce and copies the current billing identity through WooCommerce CRUD.
Browser lookup/create fields and Customer Display projection are never order
authority. Guest carts keep `customer_id = 0`.

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

Phase 10 implements this route for CoffeePOS-created orders only. The response
also includes server-derived allowed actions, refunded/refundable totals, shift
ID, and the Phase 07 operational revision.

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

Phase 10 supports full or amount-based offline refunds with an idempotency key.
It does not infer item quantities, restock stock, or claim a provider refund.

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

The Phase 10 result is a new Guest/Takeaway CartView and POS session. Customer,
coupon, table, payment, shift, and historical price context are not copied.

The reconstructed cart is written into a logical WooCommerce session cart and
returned with `pos_session_id` and a new revision.

---

# 14. Receipt

## GET /coffeepos/v1/orders/{id}/receipt

Returns receipt data for printing/rendering.

The projection is derived from the authoritative WooCommerce order and approved
CoffeePOS metadata. It contains:

```text
order ID and number
store name/address
creation time in the WordPress timezone
cashier display name
customer-safe display identity
order type and table
items, variation, modifiers, quick notes, custom item note
quantity, unit amount, line amount
subtotal, discounts, refunds, total, currency
payment method
cash received and change when stored
order note only when receipt setting allows it
```

Checkout print, Order Queue reprint, and Order History reprint MUST consume this
same ReceiptView. The endpoint requires `coffeepos_reprint_receipts`. A loading,
forbidden, missing, or failed projection is an error and MUST NOT open a blank
print dialog.

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
## Phase 09 Order-to-Shift Association

Checkout resolves the authenticated cashier's active shift before order
creation. The client cannot provide a shift ID. Every new POS order persists
the resolved ID in `_coffeepos_shift_id`; shift totals query this association.
