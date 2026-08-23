# PHASE-04.md

# CoffeePOS Phase 04 — Customer, Membership & Order Type

## 1. Objective

Add trusted customer and service context to the Phase-03 WooCommerce
session-backed cart.

Primary workflow:

```text
Phase-03 Cart
    ↓
Guest OR Customer Lookup
    ↓
WooCommerce Customer
    ↓
Optional Membership Projection
    ↓
Takeaway OR Dine-in
    ↓
Table Context when Dine-in
    ↓
Canonical CartView
```

At the end of this phase:

- the cashier can keep a cart in guest mode
- the cashier can find a WooCommerce customer by phone
- a found customer can be attached to the active cart
- the selected customer can be removed and the cart returned to guest mode
- optional membership/points information can be displayed when supplied by an
  approved integration
- takeaway and dine-in can be selected
- dine-in requires a selected service table
- takeaway clears table context
- every context mutation follows the cart revision contract
- the complete customer/order-type/table projection is returned in `CartView`
- checkout, payment, coupon, order creation, and Customer Display sync remain
  out of scope

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
docs/04-api/SYNC-API.md

docs/05-phases/PHASE-01.md
docs/05-phases/PHASE-02.md
docs/05-phases/PHASE-03.md
```

Codex MUST inspect and preserve the completed Phase-03 catalog, product modal,
cart session, revision, projection, and Cashier component contracts.

Do not rebuild the Phase-03 cart or create a second customer/cart store.

Phase 04 is the customer-context foundation only. Member creation, automatic
lookup UX, and privacy-safe member identity on Customer Display are completed
in Phase 08. Loyalty earning, tier assignment, and member discounts are not
part of either phase.

---

# 3. Scope

## In Scope

```text
Guest customer mode
WooCommerce customer lookup by phone
Customer lookup validation
Customer found/not-found/error states
Customer selection
Customer removal / return to guest
Customer summary in Cashier
Member presentation for selected WooCommerce customers
Optional membership projection abstraction
Optional points/balance presentation when a provider supplies it
Takeaway selection
Dine-in selection
Minimal service-table list
Table selection
Clearing table context when switching to takeaway
Customer/order-type/table mutations on the session cart
Cart revision conflict handling
Cart projection updates after every context mutation
Loading, empty, error, and retry UX
```

## Out of Scope

Do NOT implement:

```text
Customer creation or editing
Customer address management
Independent CoffeePOS customer database
Independent loyalty or points ledger
Points earning or redemption
Membership tier administration
Advanced table CRUD
Floor plans
Reservations
Table occupancy tracking
Merging or transferring tables
Coupons
Checkout
Payment
WooCommerce order creation
Receipt printing
Customer Display synchronization
KDS
Order Queue
Order History
Held carts
Shifts
Reports
Refunds
```

---

# 4. Architecture

All Phase-04 mutations use the existing application boundary:

```text
Cashier UI
    ↓
REST / API client
    ↓
Application service
    ↓
Cart domain + WooCommerce customer integration
    ↓
CartSessionStoreInterface
    ↓
WooCommerce session
    ↓
CartView
```

Rules:

- the browser does not create authoritative customer context
- the browser does not decide membership status or points
- the browser does not bypass the cart revision contract
- REST controllers remain thin
- WooCommerce remains canonical for customer identity and phone data
- `Cart` remains authoritative for order type and table invariants
- context mutations return the complete canonical `CartView`

---

# 5. Customer Meaning

Phase 04 supports two cart customer modes:

```text
guest
member
```

For this phase, `member` means a selected, valid WooCommerce customer attached
to the cart. It does not imply that CoffeePOS owns a loyalty account.

Conceptual customer projection:

```text
mode
customer_id
display_name
phone
membership
```

Guest projection:

```json
{
  "mode": "guest",
  "customer_id": 0,
  "display_name": "Guest customer",
  "phone": "",
  "membership": null
}
```

Selected customer projection:

```json
{
  "mode": "member",
  "customer_id": 123,
  "display_name": "Nguyen Van A",
  "phone": "0900000000",
  "membership": null
}
```

Do not expose email, addresses, order history, or other customer fields unless a
later approved contract explicitly needs them.

---

# 6. Customer Lookup

Use the documented operation:

```text
GET /coffeepos/v1/customers/lookup?phone={phone}
```

Flow:

```text
Phone input
    ↓
Normalize and validate
    ↓
CustomerService
    ↓
WooCommerceCustomerGateway
    ↓
CustomerView
```

The lookup endpoint only returns a candidate projection. Finding a customer does
not attach that customer to the cart automatically.

The cashier must explicitly select the found customer.

---

# 7. Phone Rules

Phone input must:

- be trimmed
- normalize presentation separators before lookup
- retain a leading international `+` only when supported by the lookup policy
- reject an empty or clearly malformed value
- use a single server-side normalization policy

JavaScript may perform early validation for UX, but server validation remains
authoritative.

Do not implement fuzzy phone matching that can return the wrong customer.

Stable errors:

```text
invalid_customer_phone
customer_not_found
customer_lookup_failed
```

`customer_not_found` is a controlled result, not an infrastructure failure.

---

# 8. Duplicate Phone Results

WooCommerce installations may contain more than one customer with the same
normalized phone.

Phase 04 must not silently choose an arbitrary customer.

If the gateway finds multiple exact matches, return a controlled ambiguous
lookup result or stable error containing only safe candidate summaries.

The UI may ask the cashier to choose a candidate when the API contract supports
multiple results. Deterministic selection by lowest/highest customer ID is not
allowed unless explicitly approved.

---

# 9. Customer Selection

Selection is a cart mutation:

```text
Selected customer_id
    ↓
pos_session_id + expected_revision
    ↓
Load canonical cart
    ↓
Reload WooCommerce customer
    ↓
Validate customer
    ↓
CartService sets CustomerContext
    ↓
Persist + increment revision
    ↓
Return full CartView
```

The server must ignore client-supplied customer name, phone, membership status,
points, and order history.

The cart mutation accepts the customer ID and resolves the projection again
from trusted sources.

---

# 10. Return to Guest

The cashier can remove the selected customer.

Flow:

```text
Remove customer
    ↓
pos_session_id + expected_revision
    ↓
CartService sets guest CustomerContext
    ↓
Persist + increment revision
    ↓
Return full CartView
```

Returning to guest must not clear cart items or change prices in Phase 04.

---

# 11. Membership Projection

Membership and points are optional projection data.

If supported, use an application contract such as:

```text
MembershipProviderInterface
    ↓
MembershipView | null
```

Possible read-only fields:

```text
status_label
tier_label
points_display
balance_display
```

Rules:

- membership data must come from an approved provider
- the default provider may return `null`
- the Cashier must work when membership is unavailable
- provider failure must not misidentify the WooCommerce customer
- no points are awarded, spent, or recalculated in Phase 04
- do not infer points from WooCommerce order totals in JavaScript
- do not create a CoffeePOS loyalty table

The UI must hide unavailable membership fields rather than display invented zero
balances.

---

# 12. Order Type

Supported values are exactly:

```text
dine_in
takeaway
```

The existing Phase-01 domain rules remain authoritative:

```text
dine_in → table context required
takeaway → table context prohibited
```

Do not invent a third state such as `delivery`, `unset`, or `unknown` in this
phase.

---

# 13. Default Order Type

The cart already has an application-owned default order type.

The UI must render the value returned in `CartView`; it must not assume its
Phase-02 visual default is authoritative.

If the operational default remains takeaway:

```text
new cart → takeaway → no table
```

Changing the operational default later requires a documented setting and domain
decision.

---

# 14. Dine-in Transition

Selecting dine-in requires table context.

Recommended UI flow:

```text
Click Dine-in
    ↓
Open Table Selector
    ↓
Select table
    ↓
Submit one atomic context mutation
    ↓
order_type = dine_in + table
```

Do not persist an invalid intermediate cart containing `dine_in` without a
table.

Canceling the table selector leaves the previous canonical order type unchanged.

---

# 15. Takeaway Transition

Selecting takeaway is an atomic cart mutation:

```text
order_type = takeaway
table = none
```

Any previous dine-in table context must be cleared server-side in the same
mutation.

The UI must update from the returned `CartView`.

---

# 16. Table Context

The minimal Phase-04 table projection is:

```text
table_id
table_label
```

Rules:

- `table_id` is a stable identifier from the approved table provider
- `table_label` is presentation data returned by the provider
- the client submits only the table identifier
- the server resolves the current label
- disabled or unknown tables cannot be selected
- no table context is allowed for takeaway

Do not create reservations, occupancy state, floor coordinates, or table orders.

---

# 17. Table Provider Boundary

Because the project does not define a full table-management system, Phase 04
must isolate table retrieval behind an application contract such as:

```text
TableProviderInterface
├── listAvailable()
└── findAvailableById(table_id)
```

The implementation may use an existing approved settings/integration source.

If implementation requires a new WordPress option, metadata key, or database
table, update `docs/02-database/DATABASE.md` before adding that storage.

Do not hard-code table labels in JavaScript.

---

# 18. Table API

Use the documented table-list operation:

```text
GET /coffeepos/v1/tables
```

Conceptual response:

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "table-01",
        "label": "Table 01",
        "enabled": true,
        "sort_order": 10
      }
    ]
  }
}
```

The endpoint returns selection data only. It is not table-management CRUD.

---

# 19. Cart Context API Operations

Phase 04 requires equivalent operations for:

```text
attach customer to cart
return cart to guest
set dine-in with table
set takeaway and clear table
```

Every mutation accepts:

```text
pos_session_id
expected_revision
operation-specific trusted identifiers
```

Every successful mutation returns the full canonical cart projection and
increments `revision` exactly once.

Before implementation, add the final REST paths and request/response examples
to `docs/04-api/POS-API.md`. Do not invent route shapes only inside JavaScript.

Recommended resource-oriented shape:

```text
PUT    /coffeepos/v1/cart/customer
DELETE /coffeepos/v1/cart/customer
PUT    /coffeepos/v1/cart/service-context
```

An equivalent shape is acceptable only when documented consistently in
`POS-API.md`, the controller, API client, and tests.

---

# 20. Cart Projection

After Phase 04, `CartView` must consistently contain:

```text
pos_session_id
revision
items
subtotal
discount
total
customer
order_type
table
validation
```

Customer, order type, and table must be present in initial, empty, and populated
cart projections.

The Cashier renders context only from this projection after a mutation.

---

# 21. Revision and Concurrency

Customer and service context use the Phase-03 revision contract.

```text
expected_revision != current revision
    ↓
HTTP 409 cart_revision_conflict
    ↓
latest CartView
    ↓
Cashier visibly reconciles
```

A stale customer selection must not overwrite a newer table or cart-item change.

A stale order-type mutation must not overwrite a newer customer selection.

Do not maintain separate customer, table, and item revisions.

---

# 22. Customer Lookup UI

The customer lookup surface must support:

```text
closed
idle
searching
found
not_found
error
```

Semantic structure:

```text
Customer Lookup
├── phone input
├── search action
├── lookup status
├── found customer summary
├── optional membership summary
├── select action
└── cancel action
```

Lookup errors preserve the entered phone number.

Selecting a customer prevents double submission while the cart mutation is
pending.

---

# 23. Customer Summary UI

Guest state:

```text
Guest customer
Find customer
```

Selected state may show:

```text
display name
phone
membership status when available
points/balance when available
change customer
remove customer
```

Do not display raw internal metadata or customer addresses.

---

# 24. Order Type UI

The Phase-02 order-type selector becomes functional.

Stable values:

```text
data-order-type="dine_in"
data-order-type="takeaway"
```

Stable action:

```text
data-action="select-order-type"
```

The selected visual state follows the latest `CartView`, not the last clicked
button.

While a service-context mutation is pending, both order-type buttons must prevent
duplicate requests.

---

# 25. Table Selector UI

The table selector supports:

```text
loading
normal
empty
error
selected
```

Stable hooks:

```text
data-component="table-selector"
data-component="table-list"
data-component="table-option"
data-action="select-table"
data-table-id
```

Dynamic table options must render from PHP-owned `<template>` markup through the
shared `TemplateRenderer`.

JavaScript must not build table buttons with HTML template strings.

---

# 26. PHP Templates

Create only templates actually used.

Expected conceptual additions:

```text
templates/components/
├── customer-lookup.php
├── customer-result.php
├── customer-summary.php
├── membership-summary.php
├── table-selector.php
└── table-option.php
```

Repeated/AJAX-driven customer results and table options use named native
`<template>` blueprints with:

```text
data-field
data-attr
data-key
```

REST responses contain JSON projections, not rendered HTML fragments.

---

# 27. JavaScript Modules

Extend the Phase-03 module graph.

Suggested additions:

```text
assets/js/api/
└── context operations in the existing POS API client

assets/js/components/
├── customer-lookup.js
├── customer-summary.js
├── order-type.js
└── table-selector.js

assets/js/state/
└── extend cashier-store.js
```

Do not create a second Cashier controller, API client, cart store, modal engine,
or template renderer.

---

# 28. Cashier Client State

The client may maintain projections of:

```text
customer lookup query/result/status
table list/status
currently open selector
latest CartView
pending context mutation
```

The client must not maintain an authoritative customer, membership balance,
order type, or table context separate from `CartView`.

---

# 29. Error Handling

Customer errors:

```text
invalid_customer_phone
customer_not_found
customer_lookup_failed
invalid_customer
```

Service-context errors:

```text
invalid_order_type
invalid_table
table_required
table_not_allowed
cart_revision_conflict
cart_session_not_found
```

Rules:

- lookup failure preserves the phone input
- attach failure preserves the found customer result
- table load failure preserves the current canonical cart context
- mutation failure does not optimistically replace `CartView`
- revision conflict renders the latest returned cart projection
- error messages must be actionable and safe

---

# 30. Race Conditions

The implementation must handle:

```text
lookup phone A
quickly lookup phone B
```

The older response must not replace the newer result.

Also handle:

```text
open customer lookup
close/reopen lookup
```

and:

```text
open table selector
switch back to takeaway before table load completes
```

Use request sequencing or abort signals where practical.

---

# 31. Double Submission

Prevent duplicate:

```text
customer attach
customer remove
order-type change
table selection
```

Use pending state, disabled actions, and `expected_revision`.

Do not allow two context mutations to use the same cart revision concurrently.

---

# 32. Security

All routes require:

- authenticated WordPress/WooCommerce session
- CoffeePOS capability checks
- REST nonce where applicable
- input validation and sanitization
- output-safe projections

Never trust client-provided:

```text
customer name
customer phone after selection
membership status
points/balance
table label
order type outside documented values
cart revision
```

Lookup responses must expose only the minimum customer data needed by Cashier.

---

# 33. Database and WooCommerce Rules

No new custom database table is allowed in Phase 04.

WooCommerce remains canonical for:

```text
customer ID
customer name
billing phone
customer account
```

Customer, order type, and table context remain in the active WooCommerce session
cart until Phase 05 creates an order.

Do not write Phase-05 order metadata during Phase 04.

Do not create a draft WooCommerce order merely to persist context.

---

# 34. Acceptance Criteria

Phase 04 is complete when:

## Customer

1. A new cart starts with a guest customer projection.
2. The cashier can open customer lookup.
3. A valid phone can locate the correct WooCommerce customer.
4. Invalid phone input is rejected.
5. Not-found is a controlled UI state.
6. Lookup infrastructure failure is distinguishable from not-found.
7. Older lookup responses cannot replace newer results.
8. A found customer is not attached until explicitly selected.
9. Customer selection reloads trusted WooCommerce customer data server-side.
10. Selected customer appears in `CartView` and Cashier summary.
11. The customer can be removed and the cart returns to guest mode.
12. Customer changes do not clear cart items.
13. Unnecessary customer fields are not exposed.

## Membership

14. A selected WooCommerce customer is presented as member mode.
15. Optional membership data is accessed through an abstraction.
16. Cashier works when no membership provider is configured.
17. Optional points/tier fields render only when provided.
18. No loyalty database, earning, redemption, or client calculation is added.

## Order Type and Table

19. `takeaway` and `dine_in` are the only supported order types.
20. The initial UI renders order type from `CartView`.
21. Selecting dine-in opens the table-selection flow.
22. Canceling table selection preserves the previous cart context.
23. Dine-in cannot persist without a valid table.
24. Table choices load through `TableProviderInterface` or equivalent boundary.
25. The client submits table ID, not a trusted table label.
26. Unknown or disabled table IDs are rejected server-side.
27. Selecting a table atomically persists dine-in and table context.
28. Selecting takeaway atomically clears table context.
29. The table summary renders from returned `CartView`.

## Cart and Reliability

30. Every successful customer/context mutation increments cart revision once.
31. A stale expected revision is rejected without overwriting newer state.
32. A revision conflict visibly reconciles to the latest `CartView`.
33. Duplicate rapid context mutations are prevented.
34. Context mutation errors preserve the last valid cart projection.
35. Customer, order type, and table survive cart serialization/hydration.
36. Two `pos_session_id` values do not share context.

## Templates and Scope

37. Dynamic customer/table lists use PHP-owned native templates.
38. JSON text bindings do not interpret values as HTML.
39. REST responses contain projections, not HTML fragments.
40. Checkout remains disabled and no WooCommerce order is created.
41. Coupon and Customer Display synchronization remain out of scope.
42. No new custom database table is created.

---

# 35. Required Test Cases

## Customer Lookup

```text
TC-01 guest cart projection
TC-02 valid normalized phone lookup
TC-03 invalid phone
TC-04 customer not found
TC-05 lookup infrastructure failure
TC-06 duplicate exact phone handling
TC-07 stale lookup response
TC-08 lookup close/reopen stale response
```

## Customer Context

```text
TC-09 attach valid WooCommerce customer
TC-10 reject unknown customer ID
TC-11 ignore client customer name/phone/membership fields
TC-12 selected customer appears in CartView
TC-13 remove customer to guest
TC-14 customer mutation preserves cart items/totals
TC-15 customer context serializes and hydrates
```

## Membership

```text
TC-16 no membership provider
TC-17 provider returns membership projection
TC-18 provider failure does not misidentify customer
TC-19 absent optional fields remain hidden
```

## Order Type and Table

```text
TC-20 initial takeaway projection
TC-21 table list loading
TC-22 table list empty/error
TC-23 dine-in with valid table
TC-24 reject dine-in without table
TC-25 reject unknown/disabled table
TC-26 switching takeaway clears table
TC-27 cancel table selector preserves context
TC-28 table context serializes and hydrates
```

## Revision and Reliability

```text
TC-29 customer mutation increments revision
TC-30 service-context mutation increments revision
TC-31 stale customer mutation rejected
TC-32 stale table mutation rejected
TC-33 duplicate attach prevented
TC-34 duplicate table selection prevented
TC-35 separate POS sessions retain separate context
```

## Rendering and Security

```text
TC-36 customer JSON renders from PHP-owned template
TC-37 table JSON renders from PHP-owned template
TC-38 markup-like customer/table labels render as text
TC-39 unauthorized lookup rejected
TC-40 unauthorized context mutation rejected
TC-41 checkout remains disabled
TC-42 no order/custom table created
```

---

# 36. Browser Verification

Phase 04 is UI-heavy and must be verified in a real WordPress/WooCommerce
environment.

Minimum browser checks:

```text
Cashier starts in guest mode
Open/close customer lookup
Invalid phone
Customer not found
Customer found
Attach customer
Optional membership display
Remove customer
Takeaway selected from CartView
Open/close table selector
Table loading/empty/error states
Select dine-in table
Switch dine-in to takeaway and clear table
Cart items remain unchanged through context changes
Revision-conflict reconciliation
Rapid-click protection
Keyboard/focus behavior
Responsive POS layout
```

Do not claim these passed unless they were actually tested in a browser.

---

# 37. Definition of Done

Phase 04 is complete only when:

```text
WooCommerce Customer Lookup
        ↓
Trusted Customer Projection
        ↓
Guest / Member Cart Context
        ↓
Takeaway OR Dine-in + Table
        ↓
Phase-03 Session Cart + Revision
        ↓
Canonical CartView
        ↓
Cashier Customer and Service Summary
```

works end-to-end without creating a WooCommerce order or introducing an
independent customer, loyalty, or table-management database.

---

# 38. Final Phase 04 Rule

When Phase 04 is complete:

STOP.

Do not automatically implement Phase 05.

Phase 05 will explicitly introduce:

```text
Checkout validation
Coupon application
Payment
WooCommerce order creation
Receipt flow
```

and must build on the complete Phase-04 cart context.
