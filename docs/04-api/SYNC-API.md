# SYNC-API.md

# CoffeePOS Synchronization Contract

## 1. Purpose

This document defines the real-time synchronization contract between CoffeePOS application surfaces.

The primary required realtime connection is:

```text
Cashier
  ↕
BroadcastChannel
  ↕
Customer Display
```

BroadcastChannel is a transport mechanism for presentation synchronization.

It is not a database and not the source of truth for payment/order data.

---

# 2. Channel

Recommended channel name:

```text
coffeepos
```

If multiple POS instances/screens must coexist on the same origin, the implementation must define a screen/session identity mechanism so messages are not misrouted.

---

# 3. Message Envelope

All synchronization messages should use a consistent envelope:

```json
{
  "version": 1,
  "type": "cart.updated",
  "message_id": "uuid",
  "timestamp": "2026-08-22T12:00:00Z",
  "source": "cashier",
  "target": "customer",
  "payload": {}
}
```

Fields:

```text
version
type
message_id
timestamp
source
target
payload
```

---

# 4. Message Types

Baseline message types:

```text
cart.updated
customer.updated
checkout.started
payment.started
payment.updated
sale.completed
display.reset
```

Potential future events:

```text
cart.cleared
payment.failed
customer.cleared
```

New message types must be documented before implementation.

---

# 5. cart.updated

Purpose:

Synchronize the current cashier cart.

Payload:

```json
{
  "cart": {
    "items": [],
    "subtotal": "0.00",
    "discount": "0.00",
    "total": "0.00"
  },
  "customer": null,
  "order_type": "takeaway",
  "table": null
}
```

The Customer Display should render the projection.

---

# 6. customer.updated

Purpose:

Synchronize customer presentation context.

Payload:

```json
{
  "customer": {
    "id": 123,
    "name": "Customer",
    "membership": null
  }
}
```

Only customer-facing fields should be included.

---

# 7. checkout.started

Purpose:

Move Customer Display from cart/presentation into checkout state.

Payload:

```json
{
  "order": {
    "total": "85000.00"
  }
}
```

---

# 8. payment.started

Purpose:

Display payment instructions.

Payload:

```json
{
  "payment": {
    "method": "bank_transfer",
    "amount": "85000.00",
    "state": "pending",
    "qr": {
      "payload": "..."
    }
  }
}
```

---

# 9. payment.updated

Purpose:

Update payment state.

Payload:

```json
{
  "payment": {
    "state": "paid",
    "amount": "85000.00"
  }
}
```

The Customer Display must only render `paid` when the authoritative application flow sends it.

---

# 10. sale.completed

Purpose:

Show successful sale / thank-you state.

Payload:

```json
{
  "order": {
    "id": 1001,
    "number": "1001",
    "total": "85000.00"
  },
  "payment": {
    "method": "cash",
    "amount": "100000.00",
    "change": "15000.00"
  }
}
```

---

# 11. display.reset

Purpose:

Return Customer Display to idle.

Payload:

```json
{
  "reason": "sale_completed"
}
```

---

# 12. Message Handling

Customer Display must:

1. validate message version
2. validate message type
3. validate required payload
4. ignore unsupported messages safely
5. update presentation state

Do not mutate server-side business state from an incoming BroadcastChannel message.

---

# 13. Message Ordering

Messages may arrive quickly.

The implementation should prevent stale messages from overwriting newer state.

At minimum, compare:

```text
timestamp
message ordering/session version
```

The exact mechanism belongs to the sync implementation.

---

# 14. Duplicate Messages

Message handlers should be safe against duplicate delivery where practical.

`message_id` can be used to ignore duplicate messages within the relevant session.

---

# 15. Screen Targeting

Suggested targets:

```text
cashier
customer
all
```

Customer Display should ignore messages not addressed to it unless `target = all`.

---

# 16. Failure Handling

BroadcastChannel failure must not corrupt server-side order/payment state.

If synchronization is unavailable:

- cashier continues operating
- Customer Display may show its last valid state
- payment authority remains server-side
- a customer-facing connection state may be displayed

---

# 17. No Business Logic in Sync Handlers

Do not implement:

```text
price calculation
coupon validation
payment confirmation
stock mutation
order creation
```

inside BroadcastChannel handlers.

Handlers translate an application event into presentation state.

---

# 18. Future Cross-Screen Sync

KDS and Order Queue may later use polling, REST, or another transport.

Do not force all screen communication through BroadcastChannel.

Use the transport appropriate to each workflow.
