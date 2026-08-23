# STATE-MACHINES.md

# CoffeePOS State Machines

## Approved manual bank-transfer presentation (2026-08-23)

```text
ACTIVE CART -> CHECKOUT OPEN -> AWAITING CASH OR BANK CONFIRMATION
            -> AUTHORIZED CASHIER COMPLETES -> PAID ORDER -> PAYMENT SUCCESS
            -> CASHIER STARTS NEW ORDER -> DISPLAY RESET
```

Selecting bank transfer and generating a VietQR preview do not create an order
or change the authoritative cart state. Final checkout validates the same cart
revision. Customer Display success is stable until `display.reset` and does not
automatically transition to THANK_YOU.

## 1. Purpose

This document defines permitted states and transitions for major CoffeePOS workflows.

States are contracts.

New states must not be introduced casually.

---

# 2. Cart State

Cart is an active working context.

The state and its monotonic revision are stored in the WooCommerce session under
the active `pos_session_id`.

```text
EMPTY
  ↓
ACTIVE
  ↓
CHECKOUT
  ↓
COMPLETED
```

The UI may reset to `EMPTY` after successful order completion.

A cart can also move:

```text
ACTIVE
  ↓
SUSPENDED

SUSPENDED
  ↓
ACTIVE
```

Invalid operations:

- checkout an empty cart
- add an unavailable required variation
- create an order from an invalid cart

---

# 3. Cart Item State

Cart items do not need a complex lifecycle.

Conceptually:

```text
CONFIGURING
    ↓
ADDED
    ↓
EDITING
    ↓
UPDATED
```

Removal removes the item from the aggregate.

---

# 4. Order State

WooCommerce remains the canonical order system.

CoffeePOS may use a POS workflow projection:

```text
DRAFT
  ↓
PENDING_PAYMENT
  ↓
PROCESSING
  ↓
READY
  ↓
COMPLETED
```

Cancellation/refund are terminal branches:

```text
DRAFT ─────→ CANCELLED
PENDING_PAYMENT ─→ CANCELLED
PROCESSING ─────→ CANCELLED
COMPLETED ──────→ REFUNDED
```

The final mapping to WooCommerce statuses must follow the actual WooCommerce configuration and integration design.

Do not create custom order statuses merely to represent CoffeePOS UI state unless explicitly required.

---

# 5. Payment State

```text
UNPAID
  ↓
PENDING
  ↓
PAID
```

Failure:

```text
PENDING
  ↓
FAILED
  ↓
PENDING
```

Refund:

```text
PAID
  ↓
REFUNDED
```

For cash:

```text
UNPAID
  ↓
CASH_COUNTED
  ↓
PAID
```

The exact implementation may use WooCommerce payment/order APIs.

Client UI MUST NOT authoritatively transition payment to PAID.

---

# 6. Customer Display State

The Customer Display is a presentation state machine:

```text
IDLE
  ↓
CART
  ↓
CHECKOUT
  ↓
PAYMENT_PENDING
  ↓
PAYMENT_SUCCESS
  ↓
THANK_YOU
  ↓
IDLE
```

Customer context may appear during `CART` or `CHECKOUT`.

Possible interruption:

```text
PAYMENT_PENDING
  ↓
PAYMENT_FAILED
  ↓
CHECKOUT
```

---

# 7. KDS State

```text
NEW
  ↓
PREPARING
  ↓
READY
  ↓
COMPLETED
```

Cancellation may occur where the operational policy allows:

```text
NEW → CANCELLED
PREPARING → CANCELLED
```

Timer thresholds:

```text
0–4:59     NORMAL
5:00–9:59   WARNING
10:00+      CRITICAL
```

These display severity states are derived states, not independent order workflow states.

---

# 8. Order Queue State

The queue is a projection of active orders.

Conceptually:

```text
ACTIVE
  ↓
COMPLETED

ACTIVE
  ↓
CANCELLED
```

The queue must not create a second order lifecycle.

---

# 9. Shift State

```text
CLOSED
  ↓
OPEN
  ↓
CLOSED
```

Opening:

```text
CLOSED
→ OPEN
```

Closing requires:

```text
actual_cash
closing_note
reconciliation
```

The shift should not become closed until required validation succeeds.

---

# 10. Suspended Cart State

```text
ACTIVE
  ↓
SUSPENDED
  ↓
RESUMED
  ↓
ACTIVE
```

Deletion:

```text
SUSPENDED
  ↓
DELETED
```

`DELETED` is terminal from the POS perspective.

---

# 11. Coupon Application State

Coupon is not a persistent order state.

Client flow:

```text
NOT_APPLIED
  ↓
VALIDATING
  ↓
APPLIED
```

Failure:

```text
VALIDATING
  ↓
REJECTED
```

Server-side validation is authoritative.

---

# 12. Refund State

```text
NOT_REFUNDED
  ↓
REFUND_PENDING
  ↓
REFUNDED
```

Failure:

```text
REFUND_PENDING
  ↓
REFUND_FAILED
```

The final refund implementation must use WooCommerce refund mechanisms.

---

# 13. Cross-Screen Event Sequence

Typical successful sale:

```text
Cashier
  ↓
Cart Updated
  ↓
Customer Display Updated
  ↓
Checkout
  ↓
Payment Pending
  ↓
Payment Success
  ↓
WooCommerce Order Created/Updated
  ↓
KDS Receives Order
  ↓
Order Queue Receives Order
  ↓
Customer Display Shows Thank You
  ↓
Cashier Resets
```

---

# 14. State Ownership

| State | Authoritative Owner |
|---|---|
| Cart draft | CartService + WooCommerce session (`pos_session_id`) |
| WooCommerce order | WooCommerce |
| Payment result | Server/payment integration |
| Shift | CoffeePOS server-side shift model |
| KDS workflow | CoffeePOS KDS application state mapped to order |
| Customer Display | Presentation projection |
| Order Queue | Query/projection of active orders |
| Reports | Query/projection |
| Coupon validity | WooCommerce/server |

---

# 15. State Transition Rules

1. Every transition must have a defined trigger.
2. Invalid transitions must be rejected.
3. Server-side validation is mandatory for business-critical transitions.
4. UI may request a transition but does not own authorization.
5. Successful server operations should emit/document the resulting state.
6. Repeated requests should be handled safely where practical.
