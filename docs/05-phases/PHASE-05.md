# PHASE-05.md

# CoffeePOS Phase 05 — Checkout, Payment & WooCommerce Order

> **Approved manual-bank revision (2026-08-23):** Bank transfer is now a
> pre-order flow. Selecting it requests a server-generated VietQR preview for
> the canonical cart revision and creates no WooCommerce order. An authorized
> cashier manually confirms receipt in the final checkout request; only that
> request creates and immediately pays the order. This supersedes conflicting
> provider-only and pending-order language below. The cart remains ACTIVE while
> the modal prevents editing; final checkout still rejects a stale revision.

## 1. Objective

Turn the complete Phase-04 WooCommerce session-backed cart into one standard,
recoverable, and idempotent WooCommerce order.

Primary workflow:

```text
Phase-04 CartView
    ↓
Coupon Selection / Entry
    ↓
Canonical Cart Recalculation
    ↓
Checkout Validation
    ↓
Cash OR Bank Transfer
    ↓
WooCommerce Order
    ↓
Trusted Payment Result
    ↓
Receipt / Success State
    ↓
Fresh POS Cart Session
```

At the end of this phase:

- the cashier can list, enter, apply, and remove WooCommerce coupons
- checkout reloads and validates the canonical session cart server-side
- cash checkout validates the received amount and calculates change server-side
- quick cash controls and exact-cash entry are functional
- bank transfer can create a pending order/payment context and display a dynamic
  VietQR for the authoritative order amount
- bank transfer is marked paid only through a trusted configured provider flow
- a successful checkout creates exactly one standard WooCommerce order
- the order preserves the required customer, service, payment, and item context
- receipt data is derived from the saved WooCommerce order
- the cashier can print the browser receipt after a successful payment
- duplicate checkout requests do not create duplicate orders or payment attempts
- failed or pending checkout does not silently discard the active cart
- Customer Display synchronization, KDS, Order Queue, shifts, and historical
  order operations remain out of scope

---

# 2. Prerequisites

Codex MUST read:

```text
AGENTS.md

docs/00-project/PROJECT.md
docs/00-project/REQUIREMENTS.md
docs/00-project/ROADMAP.md

docs/01-architecture/ARCHITECTURE.md
docs/01-architecture/DOMAIN-MODEL.md
docs/01-architecture/STATE-MACHINES.md
docs/01-architecture/DATA-FLOW.md
docs/01-architecture/SECURITY.md
docs/01-architecture/CODING-STANDARDS.md

docs/02-database/DATABASE.md
docs/02-database/WOOCOMMERCE-DATA.md

docs/03-ui/UI-ARCHITECTURE.md
docs/03-ui/COMPONENTS.md
docs/03-ui/CASHIER-UI.md

docs/04-api/API-ARCHITECTURE.md
docs/04-api/POS-API.md
docs/04-api/ORDER-API.md
docs/04-api/SYNC-API.md

docs/05-phases/PHASE-01.md
docs/05-phases/PHASE-02.md
docs/05-phases/PHASE-03.md
docs/05-phases/PHASE-04.md
```

Codex MUST inspect and preserve the completed Phase-04 catalog, cart,
customer, membership projection, order-type, table, revision, and Cashier
component contracts.

Do not create a second cart, duplicate WooCommerce pricing, or rebuild customer
and service context during checkout.

---

# 3. Scope

## In Scope

```text
Applicable active coupon listing
Manual coupon-code entry
Coupon apply and remove
WooCommerce coupon validation and recalculation
Checkout entry and modal flow
Final server-side checkout validation
Cash payment
Quick cash denomination controls
Exact cash control
Server-side cash received/change calculation
Bank-transfer payment selection
Dynamic VietQR generation for the authoritative amount
Bank-transfer pending state
Trusted bank-transfer verification boundary
Bank-transfer success when a provider verifies payment
Standard WooCommerce order creation
WooCommerce order and order-item metadata
Checkout idempotency
Order/payment projection
Payment success dialog
Order-derived receipt projection
Browser receipt printing
Safe cart finalization and fresh cart session after success
Loading, pending, failure, retry, and duplicate-submission behavior
```

## Out of Scope

Do NOT implement:

```text
Customer Display synchronization
BroadcastChannel payment messages
KDS
Order Queue
Shift management or cash drawer reconciliation
Order History
Receipt reprint from history or queue
Hardware receipt-printer drivers
Provider-specific bank integrations without an approved contract
Manual client-side "mark paid" for bank transfer
Credit/debit card payment
Digital wallets other than the approved bank-transfer/VietQR flow
Partial payment
Split tender
Tips
Store credit
Loyalty points earning or redemption
Refunds
Order cancellation workflows
Quick reorder
Held carts
Quick stock adjustment
Reports
Custom WooCommerce order statuses
New custom database tables
```

---

# 4. Architecture

All Phase-05 operations use the existing application boundary:

```text
Cashier UI
    ↓
REST / API client
    ↓
CheckoutService / CouponService / PaymentService
    ↓
Cart + Payment domain rules
    ↓
WooCommerce Cart / Coupon / Order / Payment adapters
    ↓
WooCommerce CRUD and APIs
    ↓
OrderView / PaymentView / ReceiptView
```

Rules:

- controllers remain thin
- the browser never submits an authoritative cart snapshot or total
- the server reloads the cart by `pos_session_id`
- every checkout verifies `expected_revision`
- WooCommerce remains canonical for products, variations, prices, stock,
  coupons, customers, orders, order items, and payment-related order state
- payment integrations remain behind an application contract
- order writes use WooCommerce CRUD/APIs and remain HPOS-compatible
- checkout and payment completion are idempotent operations
- JSON responses contain projections, not rendered HTML fragments

---

# 5. Coupon Contract

Coupon operations occur before checkout and mutate the active cart.

Supported actions:

```text
list applicable active coupons
apply coupon code
remove applied coupon
```

WooCommerce coupon rules remain authoritative for:

```text
existence
status
date validity
usage limits
customer restrictions
product/category restrictions
minimum/maximum spend
stacking and individual-use behavior
discount calculation
```

The browser must not calculate coupon eligibility or discount value.

---

# 6. Coupon API

Use the documented operations:

```text
GET    /coffeepos/v1/coupons/applicable
POST   /coffeepos/v1/cart/coupon
DELETE /coffeepos/v1/cart/coupon
```

If the applicable-coupon list route is not yet finalized in `POS-API.md`, it
must be documented there before implementation. The route must return only safe
selection fields and must not expose private coupon configuration unnecessarily.

Every coupon mutation accepts:

```text
pos_session_id
expected_revision
coupon code or stable coupon identifier where applicable
```

Every successful coupon mutation:

- delegates validation and discount calculation to WooCommerce
- increments the single cart revision exactly once
- returns the complete canonical `CartView`
- includes the applied coupon projection and recalculated totals

Conceptual applied coupon projection:

```json
{
  "code": "WELCOME10",
  "label": "WELCOME10",
  "discount": "10000.00"
}
```

The server must not trust a client-supplied discount, coupon label, or total.

---

# 7. Checkout Entry

The Checkout action is enabled only when the latest `CartView` indicates that
the cart may enter validation.

The UI may block obvious invalid states such as:

```text
empty cart
pending cart mutation
known invalid cart
dine-in without table
missing POS session
```

Client checks improve UX only. Final validation always occurs server-side.

Opening the checkout modal does not create an order and does not change payment
state.

---

# 8. Checkout Validation

Checkout must follow this order:

```text
Authenticate and authorize
    ↓
Validate request and idempotency key
    ↓
Load authenticated WooCommerce session cart
    ↓
Verify pos_session_id + expected_revision
    ↓
Reject empty or invalid cart
    ↓
Reload products and variations
    ↓
Revalidate price, quantity, purchasability, and stock
    ↓
Revalidate coupons and authoritative totals
    ↓
Reload and validate customer context
    ↓
Validate order type and table invariant
    ↓
Validate payment method and payment-specific input
    ↓
Create/populate WooCommerce order
    ↓
Initialize or complete payment according to trusted method
```

The WooCommerce cart used as the server-side pricing engine must be an isolated
scratch cart populated only from the addressed CoffeePOS `pos_session_id`.
When a custom REST request lazily initializes WooCommerce, the normal storefront
cart session must finish loading before the scratch cart is created; storefront
cart items must never be imported into POS pricing or mutated by POS pricing.

Validation failure must return a stable error and enough safe current projection
data for the Cashier to reconcile where applicable.

The browser must not be allowed to override server-resolved:

```text
product or variation identity relationships
unit price
line total
discount
tax
fee
order total
stock availability
customer details
table label
payment success
change due
```

---

# 9. Checkout API

Use:

```text
POST /coffeepos/v1/orders/checkout
```

Conceptual request:

```json
{
  "pos_session_id": "01J...",
  "expected_revision": 12,
  "client_operation_id": "cashier-20260823-abc123",
  "payment": {
    "method": "cash",
    "received_amount": "100000.00"
  }
}
```

Allowed payment methods are exactly:

```text
cash
bank_transfer
```

Do not add another method without updating the domain, API, database, UI, and
phase contracts.

---

# 10. Idempotency and Duplicate Checkout

`client_operation_id` is required for checkout.

The key is scoped sufficiently to prevent collisions across cashiers, POS
sessions, and installations.

Rules:

- the client generates one key for one deliberate checkout attempt
- disabling the submit action is required but is not the idempotency mechanism
- retrying the same attempt reuses the same key
- changing cart revision or payment input creates a new deliberate attempt/key
- a repeated identical request returns the original order/payment result
- a reused key with materially different input is rejected
- the order ID/result mapping must survive request retries
- two concurrent requests for the same key must not create two orders

The implementation may use a WooCommerce order query plus reserved order meta or
another documented durable WooCommerce-compatible mechanism. Do not add a custom
table without updating `DATABASE.md` and receiving approval.

Before implementation, document the final idempotency metadata key and lookup
strategy in `DATABASE.md` and `ORDER-API.md`.

---

# 11. Cash Payment

Cash checkout request includes:

```text
method = cash
received_amount
```

Server rules:

```text
order total > 0 where payment is required
received_amount is a valid non-negative money input
received_amount >= authoritative order total
change = received_amount - authoritative order total
```

The Cashier may preview change, but the checkout response is authoritative.

The server must not accept client-supplied `change`, `paid`, or order total.

Cash state flow:

```text
UNPAID
    ↓
CASH_COUNTED
    ↓
PAID
```

Only after order creation and the server-side cash rules succeed may the order
be marked paid using the appropriate WooCommerce API/status behavior.

---

# 12. Quick Cash Controls

The cash panel supports:

```text
+10k
+20k
+50k
+100k
+200k
+500k
Exact
```

Rules:

- denomination controls update only the cash input/preview
- `Exact` sets the displayed received amount to the latest authoritative total
- manual entry remains available
- currency precision follows WooCommerce settings
- locale formatting must not change the submitted normalized money value
- controls must not mark payment complete

If additive denominations produce an amount below total, checkout remains
invalid until sufficient cash is entered.

---

# 13. Bank Transfer and VietQR

Bank-transfer checkout request includes:

```text
method = bank_transfer
```

Initial flow:

```text
Validate Cart
    ↓
Create WooCommerce Order / Payment Context
    ↓
Generate VietQR for authoritative order total
    ↓
PENDING
    ↓
Trusted Provider Verification
    ↓
PAID OR FAILED/PENDING
```

The VietQR payload must be generated server-side from approved settings and the
saved order/payment amount.

The QR must correspond to:

```text
configured beneficiary
authoritative amount
order-specific transfer content/reference
provider-required fields
```

Do not put credentials, secret keys, or unrelated customer data into the client
projection.

Displaying or scanning a QR is not proof of payment.

---

# 14. Payment Provider Boundary

The exact bank/payment verification provider is not defined by the baseline
documents. Phase 05 must therefore isolate it behind an application contract
such as:

```text
PaymentGatewayInterface
├── initialize(order, payment_context)
├── getStatus(order, provider_reference)
└── verifyCompletion(order, trusted_provider_input)
```

Provider results must normalize to the documented payment states and safe
projection fields.

Rules:

- a configured provider may return pending, paid, or failed
- provider identity and references are validated server-side
- provider webhook/callback signatures are verified where applicable
- repeated provider events are idempotent
- an unavailable provider leaves the payment pending or fails safely
- the default/fallback adapter must not simulate a successful transfer
- Cashier polling may read trusted status but may not authoritatively write paid

If no approved verification provider is configured, Phase 05 supports generating
the configured VietQR and retaining a pending order only. Bank-transfer success
must remain unavailable; it must not be replaced by a manual browser confirmation.

---

# 15. Payment Completion API

Use only when a trusted provider contract defines a completion operation:

```text
POST /coffeepos/v1/orders/{id}/payment/complete
```

The route must validate trusted provider evidence or invoke the configured
provider verification. It must never accept this as proof:

```json
{
  "paid": true
}
```

If status polling is required, document a read-only order/payment-status route
in `ORDER-API.md` before implementation.

Payment completion must be idempotent. Repeated verified completion must return
the existing paid result without applying payment twice.

---

# 16. Payment State

Use the documented payment state machine:

```text
UNPAID
    ↓
PENDING
    ↓
PAID
```

Failure and retry:

```text
PENDING
    ↓
FAILED
    ↓
PENDING
```

Cash adds the server-owned intermediate state:

```text
UNPAID → CASH_COUNTED → PAID
```

The UI may project these states as lowercase values:

```text
unpaid
pending
paid
failed
```

Do not invent a new domain payment state only for presentation convenience.

---

# 17. WooCommerce Order Creation

The canonical order is a standard WooCommerce order created through supported
WooCommerce CRUD/APIs.

The order must contain:

```text
products
variations
quantities
authoritative prices and totals
WooCommerce coupon/discount data
customer association/details required for the sale
payment method and payment state
order status appropriate to the trusted payment result
POS order metadata
WooCommerce order items
POS order-item metadata
```

Order creation must remain HPOS-compatible. Do not write directly to
`wp_posts`, `wp_postmeta`, or WooCommerce internal order tables.

If an order is created but payment initialization fails, return a recoverable
order/payment result. Do not create another order automatically on retry.

---

# 18. Order Context and Metadata

Persist the documented POS context through WooCommerce CRUD APIs.

Order metadata:

```text
_coffeepos_order_type
_coffeepos_table_id
_coffeepos_table_label
_coffeepos_cashier_id
_coffeepos_payment_method where native fields are insufficient
_coffeepos_payment_reference when applicable
```

`_coffeepos_shift_id` is reserved but must not be populated until a valid active
shift exists in Phase 09.

Order-item metadata:

```text
_coffeepos_note
_coffeepos_quick_notes
_coffeepos_modifiers
```

Rules:

- dine-in requires table ID and captured table label
- takeaway must not retain table metadata
- the cashier ID comes from the authenticated WordPress user
- payment reference must not contain secrets or sensitive credentials
- item metadata is rebuilt from the trusted canonical cart item
- all keys must match `DATABASE.md`
- no arbitrary order meta keys are allowed

The final idempotency key and cash-received/change storage decision, if these
must persist for receipt/retry behavior, must be documented in `DATABASE.md`
before code writes them.

---

# 19. Order and Payment Projection

Conceptual paid cash response:

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
      "received_amount": "100000.00",
      "change": "15000.00"
    },
    "receipt": {
      "available": true
    }
  }
}
```

Conceptual pending bank-transfer response:

```json
{
  "success": true,
  "data": {
    "order": {
      "id": 1002,
      "number": "1002",
      "status": "pending"
    },
    "payment": {
      "method": "bank_transfer",
      "state": "pending",
      "amount": "85000.00",
      "reference": "POS-1002",
      "qr": {
        "payload": "..."
      }
    },
    "receipt": {
      "available": false
    }
  }
}
```

The exact order status string follows the actual WooCommerce mapping. Do not
create a custom status merely to match these examples.

---

# 20. Cart Finalization

The cart follows the existing state machine:

```text
ACTIVE
    ↓
CHECKOUT
    ↓
COMPLETED
```

Before an order exists, validation/payment-input failure leaves the cart
`ACTIVE` and editable.

After a recoverable order is created, including a pending bank transfer, the
source cart is in `CHECKOUT` and is immutable. Item, coupon, customer, and
service-context mutations against that cart must be rejected. The cashier may
retry or refresh only the payment/order workflow associated with the same
idempotent checkout attempt.

The cart must not be cleared or marked `COMPLETED` when:

```text
checkout validation fails
order creation fails before a recoverable order exists
cash validation fails
payment initialization fails without a recoverable result
bank transfer remains pending
payment verification fails
```

For a pending/failed payment with an existing order, preserving cart context
means preserving the frozen checkout snapshot for recovery; it does not mean
allowing the cart to diverge from the created order.

After a trusted paid result:

```text
Finalize old pos_session_id
    ↓
Prevent reuse for another checkout
    ↓
Create a fresh empty POS cart session
    ↓
Return or load the fresh CartView
```

The success response/follow-up flow must make recovery possible if the browser
closes after order creation but before receiving the new cart projection.

The old cart must not be checked out twice. A duplicate checkout request returns
the original result through idempotency handling.

---

# 21. Receipt Contract

Use:

```text
GET /coffeepos/v1/orders/{id}/receipt
```

Receipt data is derived from the persisted WooCommerce order, not the mutable
browser cart.

Receipt projection should contain only required display data:

```text
store identity configured for receipt
order number
order date/time
cashier display context where allowed
customer summary where allowed
order type and table
line items
variation/configuration labels
quick notes/custom notes where appropriate
subtotal
discount
tax/fees when present
total
payment method
cash received and change when persisted/available
bank-transfer reference when safe
```

The receipt endpoint requires authorization. Receipt fields must be escaped at
render time and must not expose provider secrets or unnecessary customer data.

---

# 22. Receipt Printing

Phase 05 supports a dedicated print-friendly PHP-owned receipt view and browser
printing.

Rules:

- receipt HTML structure belongs to PHP templates
- repeated item rows use PHP-owned markup/templates
- the print view renders saved `ReceiptView` data
- print CSS hides application controls and targets receipt media appropriately
- print is offered only when receipt data is available under payment policy
- closing or failing the print dialog must not alter order/payment state

Native printer drivers, raw ESC/POS, automatic cash-drawer opening, and silent
printing are out of scope until an approved receipt-printer contract exists.

---

# 23. Checkout Modal UI

The checkout modal follows:

```text
Order Summary
    ↓
Payment Method
    ↓
Payment Details
    ↓
Submit / Pending
    ↓
Success OR Recoverable Error
```

Required states:

```text
closed
validating
ready
submitting
payment_pending
payment_failed
success
error
```

The order summary renders the latest server-confirmed totals. If validation
returns a newer cart projection, the modal and Cashier must reconcile before
allowing submission.

---

# 24. Coupon UI

The coupon surface supports:

```text
closed
loading
normal
empty
applying
applied
removing
invalid
error
```

Stable component/action hooks should include:

```text
data-component="coupon-selector"
data-component="coupon-list"
data-component="coupon-option"
data-action="open-coupon-selector"
data-action="apply-coupon"
data-action="remove-coupon"
data-coupon-code
```

Applicable coupon results use PHP-owned native `<template>` markup and the
shared `TemplateRenderer`. Coupon code and labels render as text.

---

# 25. Cash Payment UI

The cash panel displays:

```text
authoritative total
received amount input
quick denomination controls
exact control
preview change or insufficient amount state
submit action
```

Stable hooks should include:

```text
data-component="cash-payment"
data-field="payment-total"
data-field="cash-received"
data-field="cash-change"
data-action="cash-quick-amount"
data-action="cash-exact"
data-action="submit-checkout"
data-amount
```

The server response replaces the previewed change after checkout.

---

# 26. Bank Transfer UI

The bank-transfer panel displays:

```text
authoritative amount
VietQR
safe transfer reference/content
payment status
retry/status action where supported
```

Required states:

```text
initializing
pending
paid
failed
provider_unavailable
```

Stable hooks should include:

```text
data-component="bank-transfer-payment"
data-component="vietqr"
data-field="payment-amount"
data-field="payment-reference"
data-field="payment-status"
data-action="refresh-payment-status"
```

The UI must state clearly when payment is pending. It must not display success
merely because the QR loaded.

---

# 27. Payment Success Dialog

The success dialog may show:

```text
order number
total
payment method
cash received/change when applicable
safe bank reference when applicable
```

Actions:

```text
print receipt
start new order
```

Stable hooks should include:

```text
data-component="payment-success"
data-action="print-receipt"
data-action="start-new-order"
```

The dialog is shown only from a trusted paid result. A pending bank transfer is
not a success state.

---

# 28. PHP Templates

Create only templates actually used.

Expected conceptual additions:

```text
templates/components/
├── coupon-selector.php
├── coupon-option.php
├── checkout-modal.php
├── cash-payment.php
├── bank-transfer-payment.php
└── payment-success.php

templates/receipt/
└── receipt.php
```

Repeated/AJAX-driven coupon and receipt rows use named native `<template>`
blueprints where client rendering is required, with the documented:

```text
data-field
data-attr
data-key
```

JavaScript must not build application markup using HTML template strings.

---

# 29. JavaScript Modules

Extend the existing Cashier graph.

Suggested additions:

```text
assets/js/api/
└── extend existing clients with coupon, checkout, payment, and receipt calls

assets/js/components/
├── coupon-selector.js
├── checkout.js
├── cash-payment.js
├── bank-transfer-payment.js
└── payment-success.js
```

Do not create a second Cashier controller, cart store, API client, modal engine,
template renderer, money authority, or payment authority.

---

# 30. Cashier Client State

The client may maintain projections of:

```text
coupon-list status/results
checkout modal state
selected payment method
cash input and preview change
checkout operation ID
pending checkout request
order/payment result
bank payment-status request state
receipt loading/printing state
latest CartView
```

The client must not maintain authoritative:

```text
discount eligibility
cart/order total
stock
payment success
order status
cash change
provider verification
```

---

# 31. Revision and Concurrency

Coupon and checkout operations use the single Phase-03/04 cart revision.

```text
expected_revision != current revision
    ↓
HTTP 409 cart_revision_conflict
    ↓
latest CartView
    ↓
Cashier visibly reconciles
```

A stale coupon mutation must not overwrite newer cart/customer/table changes.

A stale checkout must not create an order from a cart that changed after the
cashier reviewed it.

Do not create separate coupon, checkout, or payment cart revisions.

Once an order exists, payment status concurrency is owned by the order/payment
workflow and its idempotency/provider rules, not by mutating the finalized cart.

---

# 32. Error Handling

Cart/coupon errors:

```text
empty_cart
invalid_cart
invalid_coupon
coupon_not_applicable
cart_session_not_found
cart_revision_conflict
out_of_stock
invalid_variation
invalid_customer
invalid_order_type
invalid_table
```

Checkout/payment/order errors:

```text
invalid_payment
insufficient_cash
order_creation_failed
payment_failed
payment_pending
payment_provider_unavailable
payment_verification_failed
invalid_order_state
order_not_found
duplicate_operation_conflict
unauthorized
```

Before implementation, add any new stable error code to the relevant shared
error/API contract. Do not invent codes only inside JavaScript.

Rules:

- invalid coupon preserves the entered code
- coupon failure preserves the last valid CartView
- revision conflict renders the latest returned CartView
- validation failure preserves the cart
- order creation failure never shows payment success
- pending payment retains the recoverable order/payment context
- failed verification does not mark the order paid
- safe retries reuse the relevant idempotency key
- errors must be actionable without exposing internals or credentials

---

# 33. Race Conditions and Double Submission

Handle at minimum:

```text
apply coupon A then quickly coupon B
remove coupon while apply is pending
change cart while checkout modal is open
double-click checkout
checkout response arrives after modal close/reopen
payment status requests resolve out of order
provider sends the same completion event repeatedly
browser retries after order creation but before receiving the response
```

Use pending state, request sequencing/abort signals where appropriate,
`expected_revision`, and server idempotency.

UI disabling alone is not sufficient duplicate-order protection.

---

# 34. Security

All routes require:

- authenticated WordPress/WooCommerce session where applicable
- CoffeePOS capability checks
- REST nonce where applicable
- ownership/access checks for the requested `pos_session_id` or order
- input validation and sanitization
- output-safe projections
- rate/abuse consideration for payment-status operations

Never trust client-provided:

```text
cart contents
price or total
coupon discount
customer fields
table label
cash change
payment success
provider reference without verification
order status
cashier identity
```

Provider callbacks must validate signatures/secrets according to the approved
provider contract. Secrets must remain server-side and must not appear in logs,
REST responses, receipt output, or browser configuration.

---

# 35. Database and WooCommerce Rules

No new custom database table is allowed in Phase 05.

WooCommerce remains canonical for:

```text
coupons
customers
orders
order items
prices and totals
stock
payment method/state where supported
order status
```

CoffeePOS may persist only documented POS order/order-item metadata required to
reconstruct the sale context.

Do not:

```text
create a parallel order table
create a parallel payment ledger
write raw postmeta when WooCommerce CRUD is available
assume wp_posts order storage
invent arbitrary order meta
store provider secrets or payment credentials
```

Any new option for VietQR/provider configuration or any new meta used for
idempotency, cash received, change, or payment recovery must be documented in
`DATABASE.md` before implementation.

---

# 36. Acceptance Criteria

Phase 05 is complete when:

## Coupon

1. The cashier can open the coupon selector.
2. Applicable active coupons can be listed through a documented API.
3. The cashier can enter a coupon code manually.
4. WooCommerce validates coupon eligibility server-side.
5. Applying a valid coupon increments cart revision exactly once.
6. Applying a coupon returns a complete recalculated `CartView`.
7. Invalid/not-applicable coupon is a controlled error state.
8. An applied coupon can be removed server-side.
9. Removing a coupon increments revision and recalculates totals.
10. The browser never supplies an authoritative discount or total.

## Checkout Validation

11. Empty cart checkout is rejected.
12. Unknown/expired `pos_session_id` is rejected.
13. Stale `expected_revision` is rejected before order creation.
14. Products, variations, quantity, price, stock, and coupons are revalidated.
15. Customer and order-type/table context are revalidated.
16. Validation failure preserves the active cart.
17. A cart changed after modal opening cannot be checked out silently.

## Cash

18. Cash method accepts normalized received amount only.
19. Received amount below total is rejected server-side.
20. Exact cash is supported.
21. Quick denomination controls update cash input/preview.
22. Server calculates authoritative change.
23. Client-supplied change/payment-success fields are ignored or rejected.
24. Successful cash checkout creates one paid WooCommerce order.

## Bank Transfer

25. Bank transfer creates a recoverable order/payment context.
26. VietQR is generated for the authoritative saved order amount.
27. QR/reference data comes from approved server settings/provider output.
28. Showing or scanning QR leaves payment pending.
29. The UI clearly distinguishes pending, failed, and paid.
30. Payment becomes paid only after trusted provider verification.
31. Without an approved verification provider, manual client success is blocked.
32. Repeated verified completion does not apply payment twice.

## WooCommerce Order

33. Checkout creates a standard WooCommerce order through CRUD/APIs.
34. Order creation remains HPOS-compatible.
35. Products, variations, quantities, prices, discounts, and totals are correct.
36. The selected WooCommerce customer is associated where applicable.
37. Order type and table metadata follow `DATABASE.md` invariants.
38. Cashier identity comes from the authenticated WordPress user.
39. Payment method/reference metadata follows documented ownership rules.
40. Item notes, quick notes, and modifiers are preserved as order-item metadata.
41. Takeaway orders contain no stale table context.
42. No arbitrary metadata or custom order status is introduced.

## Idempotency and Recovery

43. Checkout requires `client_operation_id`.
44. Duplicate identical checkout requests create exactly one order.
45. Concurrent identical checkout requests create exactly one order.
46. Reusing an operation ID with different input is rejected.
47. A retry after order creation returns the original recoverable result.
48. Failed checkout does not clear the cart.
49. Failure before order creation leaves the active cart editable.
50. An existing pending-payment order freezes its source cart in `CHECKOUT`.
51. Frozen checkout cart mutations are rejected without changing the order.
52. Pending bank transfer does not silently discard the recoverable cart context.
53. Trusted paid completion finalizes the old cart once.
54. Starting the next order uses a fresh isolated `pos_session_id`.

## Receipt and UI

55. Success dialog appears only for trusted paid results.
56. Receipt data is derived from the saved WooCommerce order.
57. The authorized receipt endpoint returns the documented projection.
58. A print-friendly PHP-owned receipt view is available.
59. Browser printing does not mutate order/payment state.
60. Receipt output safely escapes notes, labels, and customer fields.
61. Coupon/payment dynamic markup uses PHP-owned templates.
62. Duplicate checkout controls are visibly disabled while pending.
63. Customer Display, KDS, Queue, shifts, refunds, and history remain out of scope.

## Storage and Security

64. No new custom database table is created.
65. WooCommerce CRUD/APIs are used for orders and order items.
66. Order access is capability/ownership protected.
67. Provider secrets are never exposed to the browser or receipt.
68. All new settings/meta/error/route contracts are documented before use.

---

# 37. Required Test Cases

## Coupon

```text
TC-01 list applicable coupons
TC-02 applicable coupon list empty/error
TC-03 apply valid coupon
TC-04 reject invalid/expired/not-applicable coupon
TC-05 remove coupon
TC-06 coupon changes authoritative totals
TC-07 stale coupon mutation rejected
TC-08 rapid coupon responses cannot overwrite newer CartView
TC-09 markup-like coupon label renders as text
```

## Checkout Validation

```text
TC-10 reject empty cart
TC-11 reject missing/expired POS session
TC-12 reject stale cart revision
TC-13 reject unavailable product/variation
TC-14 reject changed/out-of-stock quantity
TC-15 resolve current WooCommerce pricing
TC-16 revalidate coupon
TC-17 revalidate customer
TC-18 reject dine-in without valid table
TC-19 takeaway excludes table context
TC-20 validation failure preserves cart
```

## Cash

```text
TC-21 exact cash
TC-22 quick denomination input
TC-23 manual cash input normalization
TC-24 reject malformed/negative cash input
TC-25 reject cash below total
TC-26 server computes correct change
TC-27 ignore/reject client change and paid fields
TC-28 successful cash order is paid once
```

## Bank Transfer

```text
TC-29 initialize bank-transfer order
TC-30 QR amount matches authoritative order total
TC-31 safe order-specific transfer reference
TC-32 QR display remains pending
TC-33 provider unavailable remains pending/fails safely
TC-34 reject untrusted manual paid request
TC-35 trusted provider verifies payment
TC-36 invalid provider evidence rejected
TC-37 repeated provider event is idempotent
TC-38 out-of-order status response cannot regress paid UI
```

## Order Creation and Metadata

```text
TC-39 create standard WooCommerce order
TC-40 preserve product/variation/quantity and totals
TC-41 persist customer association
TC-42 persist dine-in table snapshot
TC-43 takeaway omits table metadata
TC-44 persist authenticated cashier ID
TC-45 persist payment method/reference safely
TC-46 persist item note/quick notes/modifiers
TC-47 WooCommerce order creation failure is recoverable
TC-48 HPOS-compatible CRUD path
```

## Idempotency and Cart Lifecycle

```text
TC-49 repeated identical checkout returns same order
TC-50 concurrent identical checkout creates one order
TC-51 operation ID with different payload rejected
TC-52 retry after response loss returns original result
TC-53 failed checkout retains cart and revision
TC-54 pre-order failure leaves active cart editable
TC-55 pending transfer freezes source cart in CHECKOUT
TC-56 reject item/coupon/customer/service mutation on frozen checkout cart
TC-57 pending transfer retains recoverable context
TC-58 paid checkout finalizes old cart once
TC-59 fresh cart uses new isolated pos_session_id
TC-60 old finalized cart cannot create a second order
```

## Receipt, Rendering, and Security

```text
TC-61 authorized receipt projection from saved order
TC-62 unauthorized receipt rejected
TC-63 receipt totals/payment match WooCommerce order
TC-64 receipt note/label markup renders as text
TC-65 print action does not mutate order
TC-66 coupon list uses PHP-owned template
TC-67 checkout/payment panels use PHP-owned templates
TC-68 unauthorized checkout rejected
TC-69 client price/total/customer/table/cashier values are not trusted
TC-70 provider secrets absent from REST/log/receipt output
TC-71 no custom order/payment table created
```

---

# 38. Browser Verification

Phase 05 is checkout/payment UI and must be verified in a real
WordPress/WooCommerce environment.

Minimum browser checks:

```text
Coupon selector loading/empty/error/normal
Manual valid and invalid coupon
Apply and remove coupon with totals update
Checkout disabled for empty/invalid cart
Checkout modal open/close and focus behavior
Cart change while checkout modal is open
Cash exact amount
Cash quick denominations
Cash insufficient and malformed input
Cash success and server-confirmed change
Double-click checkout protection
Bank-transfer QR amount/reference
Bank transfer pending/provider unavailable
Trusted bank success when configured
Untrusted/manual bank success blocked
Payment failure and retry
Success dialog
Receipt load and print preview
Fresh cart after paid checkout
Responsive layout and keyboard behavior
```

Do not claim these passed unless they were actually tested in a browser.

---

# 39. Documentation Gate

Before implementing Phase 05, resolve and document any contract needed by the
chosen implementation, including:

```text
applicable-coupon list route and projection
checkout idempotency persistence key/lookup strategy
cash received/change persistence when required
VietQR settings keys and validation
approved bank-transfer provider and verification mechanism
provider callback/status routes when applicable
payment-to-WooCommerce status mapping
receipt projection and print route/template ownership
fresh-cart response/finalization contract
new stable error codes
```

If the bank-transfer verification provider remains undecided, implementation
must stop at the documented pending boundary for bank transfer. Cash checkout,
order creation, VietQR initialization, and pending-state recovery may proceed;
bank-transfer success may not be simulated.

---

# 40. Definition of Done

Phase 05 is complete only when:

```text
Phase-04 Canonical Cart
        ↓
WooCommerce Coupon and Totals
        ↓
Server Checkout Validation
        ↓
Idempotent WooCommerce Order
        ↓
Cash PAID OR Bank Transfer PENDING → Trusted PAID
        ↓
Order-derived Receipt
        ↓
Finalized Old Cart + Fresh POS Session
```

works end-to-end without trusting browser totals/payment state, creating a
parallel order/payment database, or claiming unverified bank payment success.

If no approved bank verification provider exists, the phase is only partially
complete for bank transfer: the pending/VietQR workflow may pass, but the
bank-transfer paid acceptance criteria remain blocked and must be reported as
such.

---

# 41. Final Phase 05 Rule

When Phase 05 is complete:

STOP.

Do not automatically implement Phase 06 or Phase 07.

Phase 06 will explicitly introduce:

```text
Customer Display route and shell
BroadcastChannel synchronization
Session-scoped payment/VietQR projection
Ready/request/snapshot recovery
Payment success and reset display states
```

Phase 07 will explicitly consume the completed WooCommerce order for:

```text
KDS
Order Queue
Operational order state
Reprint actions
```
