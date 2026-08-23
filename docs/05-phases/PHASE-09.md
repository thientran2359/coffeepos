# PHASE-09.md

# CoffeePOS Phase 09 — Shift Management

## 1. Objective

Phase 09 introduces the complete cashier shift lifecycle: open a shift, operate
sales inside it, inspect authoritative totals, close and reconcile the drawer,
and review closed-shift history.

The exit condition is a shift that can be opened, used by checkout, closed with
actual cash, and reviewed with its variance.

## 2. Source of Truth

This phase follows `ROADMAP.md`, the Shift requirements in `REQUIREMENTS.md`,
the Shift aggregate in `DOMAIN-MODEL.md`, the OPEN/CLOSED state machine, and
the `{wp_prefix}coffeepos_shifts` schema in `DATABASE.md`.

## 3. Prerequisites

- Phase 05 authoritative checkout and WooCommerce order creation
- Phase 07 operational orders
- Phase 08 membership
- Phase 00 shift table migration
- authenticated POS capability and REST nonce infrastructure

## 4. In Scope

- one active shift per cashier
- opening cash and optional opening note
- current-shift status on Cashier
- cash, bank-transfer, total sales, order count, and expected cash
- mandatory active shift at checkout
- `_coffeepos_shift_id` order association
- actual cash, optional closing note, and variance
- current cashier's closed-shift history
- concurrency-safe open and close operations
- dedicated `/pos/shifts/` UI

## 5. Out of Scope

- cash-in/cash-out drawer adjustments
- manager-wide staff filtering and editing closed shifts
- reopening a closed shift
- reporting exports and charts
- Order History/refunds UI
- loyalty rewards

## 6. State Contract

Only these persisted states exist:

```text
closed/no active row -> open -> closed
```

A closed shift is immutable. Opening a second active shift for the same cashier
returns `shift_already_open`. Closing a missing, foreign, or already closed
shift fails without changing data.

## 7. Persistence

Shift-owned inputs use `{wp_prefix}coffeepos_shifts`. Monetary values remain
fixed decimal strings at the REST boundary and DECIMAL values in storage.
Derived totals are not written by the browser.

Every new POS order stores:

```text
_coffeepos_shift_id = active authenticated cashier shift ID
```

## 8. Authoritative Totals

Eligible orders are WooCommerce `processing` or `completed` orders associated
with the shift. Net sales subtract WooCommerce refunds.

```text
cash_sales = net eligible cash orders
bank_sales = net eligible bank-transfer orders
total_sales = cash_sales + bank_sales
expected_cash = opening_cash + cash_sales
variance = actual_cash - expected_cash
```

The UI never submits sales, expected cash, order count, or variance.

## 9. REST Contract

```text
GET  /coffeepos/v1/shifts/current
POST /coffeepos/v1/shifts/open
POST /coffeepos/v1/shifts/{id}/close
GET  /coffeepos/v1/shifts/history?limit=50
```

Open accepts `opening_cash` and `opening_note`. Close accepts `actual_cash` and
`closing_note`. All routes require the existing POS capability and REST nonce.

The ShiftView contains identity, state, timestamps, cashier display name,
opening/closing inputs, derived totals, order count, currency, and variance.

## 10. Checkout Integration

Before authoritative order creation, checkout resolves the active shift for
the authenticated cashier. With no active shift it returns `shift_required`.
The resolved server-side ID is included in the checkout idempotency fingerprint
and order context. The client cannot select or submit a shift ID.

## 11. Cashier UI

The Cashier header displays `No shift open` or `Shift #N open`. Its Shift action
navigates to `/pos/shifts/`. Attempting Checkout with no active shift displays a
clear message and does not open the payment workflow.

## 12. Shift UI

With no active shift, show an Open Shift form. With an active shift, show its
live metrics and Close Shift form. Closed history shows period, total sales,
expected cash, actual cash, and variance. Loading, error, empty, submitting,
open, and closed states must be defined.

Repeated history markup is owned by a native PHP `<template>` and cloned by
Vanilla JavaScript. No client HTML template strings are allowed.

## 13. Validation and Security

- amounts are normalized, non-negative, and limited to currency precision
- notes are sanitized and limited to 2,000 characters
- cashier ID always comes from the authenticated WordPress user
- locks are keyed by cashier and never trusted from the client
- close ownership is checked server-side
- derived totals always come from WooCommerce orders/refunds
- raw database errors are not exposed

## 14. Stable Errors

```text
shift_required
shift_already_open
shift_not_found
shift_state_conflict
shift_write_failed
invalid_shift_amount
invalid_shift_note
```

## 15. Required Verification

- PHP and JavaScript syntax checks
- Phase 09 service/API/storage/order-association scenarios
- regression tests for prior phases
- runtime migration/table availability
- browser acceptance for open, current totals, close, variance, and history
- Cashier header and no-shift checkout behavior

## 16. Acceptance Criteria

- [ ] A cashier can open only one shift.
- [ ] Opening time, cashier, cash, and note are persisted.
- [ ] Cashier shows current shift state and links to Shift Management.
- [ ] Checkout without an active shift is rejected.
- [ ] New orders receive the authoritative `_coffeepos_shift_id`.
- [ ] Totals distinguish cash and bank transfer and account for refunds.
- [ ] Expected cash is calculated server-side.
- [ ] A cashier can close only their own open shift.
- [ ] Actual cash and closing note are persisted.
- [ ] Variance is calculated server-side.
- [ ] Closed shifts appear in history and cannot be reopened.
- [ ] All REST routes enforce POS authorization.
- [ ] No previous-phase regression is introduced.

## 17. Definition of Done

Phase 09 is complete only when implementation, contracts, tests, runtime schema,
and real-browser acceptance agree. Stop after Shift Management; Order History,
refunds, quick reorder, reports, and analytics remain Phase 10+ work.
