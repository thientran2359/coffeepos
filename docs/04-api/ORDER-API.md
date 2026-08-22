# ORDER-API.md

# CoffeePOS Order API

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
