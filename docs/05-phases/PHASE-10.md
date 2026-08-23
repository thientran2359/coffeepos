# PHASE-10.md

# CoffeePOS Phase 10 — Order History, Refund & Quick Reorder

## 1. Objective

Phase 10 provides safe historical operations for WooCommerce orders created by
CoffeePOS: filtered listing, privileged detail, receipt reprint, state-aware
cancellation, WooCommerce-authoritative refund, and quick reorder into a fresh
server-side cart using current product rules.

## 2. Prerequisites

- Phase 05 checkout, payment, order metadata, and receipt projection
- Phase 07 KDS/Order Queue state and revision contract
- Phase 09 order-to-shift association and refund-aware shift totals
- WooCommerce CRUD, HPOS-compatible order queries, and refund API

## 3. In Scope

- `/pos/order-history/`
- CoffeePOS-only order list and detail
- page, date range, WooCommerce status, order type, and search filters
- receipt reprint from WooCommerce data
- cancellation only through the Phase 07 state transition
- full or amount-based offline refund through `wc_create_refund`
- refund idempotency and order-scoped locking
- quick reorder with current product, variation, stock, price, modifier, and note validation
- fresh revisioned cart session returned to Cashier
- reorder idempotency while its recorded cart remains available

## 4. Out of Scope

- refunding a card/bank provider automatically
- item-quantity refund UI and automatic restocking
- editing or deleting WooCommerce orders
- copying historical prices, coupons, payment state, table, customer, or shift
- reports, analytics, or exports
- arbitrary non-CoffeePOS WooCommerce order administration

## 5. Source and Ownership

WooCommerce owns orders, items, status, totals, payment records, and refunds.
CoffeePOS owns only POS metadata and the presentation/application workflow.
All order access must confirm `created_via=coffeepos` or existing trusted POS
session metadata. UI values never authoritatively set status or refund state.

## 6. Order List Contract

```text
GET /coffeepos/v1/orders
page, per_page, status, date_from, date_to, search, order_type
```

Dates use `YYYY-MM-DD` in the WordPress timezone and are inclusive. Invalid
status, order type, date, range, or pagination returns a stable error. Queries
use `wc_get_orders` and must remain HPOS-compatible.

Each list item exposes order number/date, status, customer display name,
service context, payment summary, total, refund summary, and allowed actions.

## 7. Order Detail Contract

```text
GET /coffeepos/v1/orders/{id}
```

Detail includes items, notes, totals, refunded/refundable values, payment,
service context, shift ID, KDS state/revision, and allowed actions. A valid
WooCommerce order outside CoffeePOS scope is returned as `order_not_found`.

## 8. Cancellation

History reuses `POST /orders/{id}/cancel`. Cancellation is permitted only for
Phase 07 `new` or `preparing` orders with a matching expected revision. It does
not imply money was returned; paid money must use Refund.

## 9. Refund

```text
POST /coffeepos/v1/orders/{id}/refund
amount, reason, client_operation_id
```

The amount is positive, currency-precision normalized, and cannot exceed the
current WooCommerce refundable amount. Cancelled, failed, fully refunded, or
unpaid orders cannot be refunded. `wc_create_refund` is authoritative.

This phase records an offline/manual refund (`refund_payment=false`) because
Cash and manual Bank Transfer have no trusted automated provider refund. The UI
must tell the cashier to return funds through the original offline method.
Partial amount refunds do not infer item quantities and do not restock items.

Refund operations are serialized per order and keep a bounded idempotency
record. A failed WooCommerce refund must never appear successful.

## 10. Quick Reorder

```text
POST /coffeepos/v1/orders/{id}/reorder
client_operation_id
```

Historical line product/variation IDs and CoffeePOS configuration IDs are read
from WooCommerce order items. Before creating the new session every line is
validated against current catalog visibility, purchasability, variation
ownership, stock, modifier groups, quick notes, and configuration rules.

Historical prices are never copied. The reconstructed cart is Guest,
Takeaway, coupon-free, unpaid, belongs to a new `pos_session_id`, and uses the
current WooCommerce currency/prices. Cashier stores that session ID and loads
the authoritative returned CartView.

## 11. Receipt

History reuses `GET /orders/{id}/receipt`. PHP owns receipt markup and the UI
fills it from the WooCommerce-derived receipt projection before printing.

## 12. UI Contract

The screen defines loading, ready, empty, error, filtering, detail, confirming,
refunding, and redirecting states. Repeated order/item rows use native PHP
`<template>` elements. JavaScript must not construct application HTML strings.

The detail actions are conditionally shown from server projections. Destructive
actions require explicit confirmation; refund requires an amount and reason
form and stays open on server error.

## 13. Stable Errors

```text
order_not_found
invalid_order_filter
invalid_order_state
order_state_conflict
invalid_refund
refund_failed
reorder_failed
invalid_product
invalid_variation
out_of_stock
invalid_configuration
duplicate_operation_conflict
```

## 14. Security

- all routes require the existing POS capability and REST nonce
- refund additionally requires `manage_woocommerce`
- order ownership/scope is checked server-side
- search/filter input is sanitized and bounded
- refund amount and refundable balance are server-validated
- refund/reorder operation IDs are bounded and idempotent
- WooCommerce CRUD/refund APIs are used instead of direct order-table writes
- raw exceptions and customer credentials are not exposed

## 15. Acceptance Criteria

- [ ] CoffeePOS orders can be paged, searched, and filtered by date/status/type.
- [ ] Order detail reflects WooCommerce items, totals, payment, and POS context.
- [ ] Receipt can be reprinted from authoritative order data.
- [ ] Only valid active operational orders can be cancelled.
- [ ] Refund amount cannot exceed WooCommerce refundable balance.
- [ ] A successful refund creates a real WooCommerce refund.
- [ ] Failed or repeated refund requests cannot duplicate refunds.
- [ ] Quick reorder revalidates all historical items before creating a cart.
- [ ] Quick reorder uses current prices and availability.
- [ ] The returned cart opens on Cashier as a new revisioned session.
- [ ] Non-CoffeePOS orders cannot be operated through this API.
- [ ] Prior phase regression suites continue to pass.

## 16. Definition of Done

Implementation, documentation, automated tests, WordPress runtime checks, and
real-browser acceptance must agree. Stop after historical order operations;
reports, analytics, exports, and final hardening remain Phase 11.
