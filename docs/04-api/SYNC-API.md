# SYNC-API.md

# CoffeePOS Synchronization Contract

## Approved manual-bank checkout projection (2026-08-23)

`checkout.started` may be emitted again when Cashier changes the selected
method. Its customer-safe payload contains `cart` plus `payment.method`,
`payment.amount`, `payment.currency`, and a presentation `state`. Cash uses
`state=awaiting_cash`. A server-confirmed pre-order VietQR preview is published
as `payment.started` with `state=awaiting_cashier_confirmation`, `order=null`,
and a QR URL restricted to HTTPS `vietqr.app/img`. Its trusted `payment.summary`
travels with the preview so displayed subtotal/discount/total match the amount
encoded in that QR.

`sale.completed` is emitted only after the final checkout response contains a
paid WooCommerce order. Customer Display keeps `payment_success` until a valid
Cashier `display.reset`; it must not advance on a local timer.

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

Catalog data is not broadcast with every cart event. Cashier and Customer
Display independently load the same `CatalogView` from `GET /catalog`; the sync
channel carries session/cart/customer/payment presentation state only.

---

# 2. Channel

Required channel name:

```text
coffeepos:<pos_session_id>
```

`pos_session_id` is the opaque logical cart ID returned by the Cart API. It may
be passed to the Customer Display URL, but it must never contain or expose the
WooCommerce session token, authentication cookie, or REST nonce.

Cashier and Customer Display must join the same session-scoped channel. A
receiver must never subscribe to a global unscoped `coffeepos` channel.

This contract supports tabs/windows in the same browser storage partition and
origin, including a second monitor attached to the cashier device.
`BroadcastChannel` does not synchronize separate devices or browser profiles;
that topology requires an additional server transport in a future documented
architecture change.

---

# 3. Message Envelope

All synchronization messages should use a consistent envelope:

```json
{
  "version": 1,
  "type": "cart.updated",
  "message_id": "uuid",
  "timestamp": "2026-08-22T12:00:00Z",
  "pos_session_id": "01J...",
  "revision": 8,
  "source_instance_id": "cashier-window-uuid",
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
pos_session_id
revision
source_instance_id
source
target
payload
```

`revision` is the monotonic revision returned by the session Cart API. Messages
that do not represent cart/application state, such as `display.ready`, carry the
latest revision known by the sender.

---

# 4. Message Types

Baseline message types:

```text
display.ready
state.requested
state.snapshot
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

## 4.1 Session Handshake

Customer Display must not wait for the next cart mutation to obtain state.

```text
Customer Display opens
        ↓
display.ready(last_known_revision)
        ↓
Cashier sends state.snapshot
        ↓
Customer Display renders current state
```

When Customer Display detects a revision gap or reconnects after an error, it
sends `state.requested`; Cashier responds with another `state.snapshot`.

`display.ready` and `state.requested` are control messages only. They cannot
change the cart or another business state.

`state.snapshot` contains the full latest server-confirmed presentation state:

```json
{
  "screen_state": "cart",
  "cart": {},
  "customer": null,
  "payment": null
}
```

Cashier should send a snapshot whenever Customer Display reports an older
revision. Customer Display may also recover with `GET /coffeepos/v1/cart` before
continuing to consume realtime messages.

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

Cashier sends this event only after the Cart API successfully stores the
mutation and returns the incremented canonical projection.

Cashier broadcasts `cart.updated` immediately after every successful add,
update, remove, clear, coupon, customer, order-type, or table mutation that
changes the customer-facing projection. Cart synchronization does not use
polling. Customer Display renders the accepted revision immediately.

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
3. require the expected `pos_session_id`
4. validate `revision` and required payload
5. ignore unsupported, foreign-session, duplicate, or stale messages safely
6. update presentation state

Do not mutate server-side business state from an incoming BroadcastChannel message.

---

# 13. Message Ordering

State ordering is determined by `revision`, not wall-clock timestamp.

- accept a state message only when its revision is greater than the last
  rendered revision
- allow `state.snapshot` with the same revision only during initial hydration
- ignore lower revisions
- when a revision gap is detected, request a new snapshot

Timestamp is diagnostic metadata and must not decide which cart state wins.

---

# 14. Duplicate Messages

Message handlers must be safe against duplicate delivery.

Use `message_id` to ignore a repeated message and `revision` to reject repeated
state. The deduplication cache is local and bounded to the relevant session.

---

# 15. Screen Targeting

Suggested targets:

```text
cashier
customer
all
```

Customer Display should ignore messages not addressed to it unless `target = all`.

Every target check occurs after verifying `pos_session_id`. The `target` field
alone is not sufficient to isolate terminals.

---

# 16. Failure Handling

BroadcastChannel failure must not corrupt server-side order/payment state.

If synchronization is unavailable:

- cashier continues operating
- Customer Display may show its last valid state
- payment authority remains server-side
- Customer Display shows a neutral reconnecting state
- Customer Display retries the handshake and may fetch the current session cart

After recovery, a full snapshot must be rendered before incremental events are
accepted again.

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

Two-way synchronization means Customer Display may send readiness, snapshot
requests, and acknowledgements. It does not grant Customer Display authority to
mutate the cart or confirm payment.

---

# 18. Future Cross-Screen Sync

KDS and Order Queue may later use polling, REST, or another transport.

Do not force all screen communication through BroadcastChannel.

Use the transport appropriate to each workflow.

---

# 19. Phase-06 Snapshot and Safe Projection

Every Cashier presentation envelope also contains a non-negative
`workflow_sequence`. It is session-scoped and monotonic for accepted
presentation events. Cart `revision` remains the only ordering authority for
cart contents; `workflow_sequence` orders checkout/payment/completion/reset
without mutating the cart. `state.snapshot` is authoritative hydration and may
replace both accepted ordering values during initial/recovery handshake.

The finalized snapshot payload is:

```json
{
  "screen_state": "idle|cart|checkout|payment_pending|payment_success|thank_you",
  "workflow_sequence": 4,
  "cart": {},
  "customer": null,
  "payment": null,
  "order": null
}
```

`cart` is the server-defined `customer_display` projection embedded in
`CartView`. It contains item names/configuration summaries, quantities, display
prices/totals, safe customer name/membership, and service context. It excludes
phone, customer IDs, custom item notes, staff data, credentials, and internal
metadata. REST recovery requests `GET /cart?...&view=customer` to receive only
this projection.

Ordering rules:

```text
cart revision lower/equal       -> ignore incremental cart event
cart revision current + 1       -> accept
cart revision gap               -> enter recovery and send state.requested
workflow sequence lower/equal   -> ignore workflow event
workflow sequence current + 1   -> accept
workflow sequence gap           -> request state.snapshot
```

A paid/completed presentation cannot be regressed by pending/failed payment
payloads even when otherwise well formed.

---

# 20. Phase-06 Session Rollover

`display.reset` is the authenticated Cashier-to-display handoff event. Its
payload is:

```json
{
  "reason": "sale_completed|new_order",
  "next_pos_session_id": "opaque-id",
  "next_revision": 0
}
```

Only a valid envelope from `source=cashier`, addressed to `customer|all`, on the
currently paired channel may initiate handoff. The display validates the next
ID with the shared strict format, closes the old channel, clears deduplication
and ordering state, opens `coffeepos:<next_pos_session_id>`, registers its
listener, then sends `display.ready`. Old-channel messages are ignored by the
new generation. Failure leaves a visible reconnecting state.

QR image URLs are accepted only when parsed as HTTPS, the host is `vietqr.app`,
and the path is exactly `/img`; data/javascript/blob URLs and credentials in
URLs are rejected.
