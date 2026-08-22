# PHASE-04.md

# CoffeePOS Phase 04 — Customer, Membership & Order Type

## 1. Objective

Extend the completed Cashier/Product/Cart workflow with customer and service context.

This phase introduces the customer and service information that belongs to the current POS cart:

```text
Guest / Member
    ↓
Customer Lookup
    ↓
Customer Context
    ↓
Order Type
    ├── Takeaway
    └── Dine-in
          ↓
       Table Selection
```

At the end of this phase:

- the cashier can operate as Guest or Member
- customer lookup by phone works
- a WooCommerce customer can be selected for the active cart
- customer information is displayed in the Cashier UI
- customer context is reflected in the cart projection
- Dine-in and Takeaway become real domain/application state
- Dine-in requires a table
- Takeaway cannot retain a table
- selected table is attached to the active cart context
- editing customer/order type/table updates the CartView
- invalid customer/order-type/table combinations are rejected
- customer and service context are ready for Phase 05 checkout/order creation

This phase does NOT create WooCommerce orders and does NOT implement checkout/payment.

---

# 2. Prerequisites

Junie MUST read:

```text
JUNIE.md
featured.txt

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

docs/05-phases/PHASE-00.md
docs/05-phases/PHASE-01.md
docs/05-phases/PHASE-02.md
docs/05-phases/PHASE-03.md
docs/05-phases/PHASE-04.md
```

Junie MUST inspect the actual Phase-03 implementation before making changes.

---

# 3. Scope

## In Scope

```text
Guest customer mode
Member/customer mode
Phone-based customer lookup
Customer selection
Customer summary
Customer context in active cart
Clear customer
Dine-in
Takeaway
Table selection
Table context in active cart
Clear table
Order type/cart validation
Customer/cart UI synchronization
Customer/order-type/table loading/error/empty states
API endpoints required for customer/table lookup
WooCommerce customer integration
Application service integration
```

## Out of Scope

Do NOT implement:

```text
WooCommerce order creation
Checkout
Cash payment
Bank transfer
VietQR
Receipt printing
Customer Display
KDS
Order Queue
Order History
Quick Reorder
Shift Management
Reports
Refund
Membership points calculation
Loyalty/reward engine
Full table management CRUD
Table occupancy management
Table reservation
Suspended Cart
Quick Stock Adjustment
```

---

# 4. Source Requirements

The baseline feature specification requires:

- Guest or Member mode
- automatic customer lookup by phone
- customer name/history/points where available
- Dine-in with table selection
- Takeaway

The feature source does not define a complete loyalty engine or table-management subsystem.

Therefore this phase must implement only:

```text
customer identity/context
phone lookup
table selection context
order type
```

Do not invent a full membership platform or restaurant table system.

---

# 5. Architecture

The required flow is:

```text
Cashier UI
    ↓
Customer / Order Type Controller
    ↓
REST/API
    ↓
Application Service
    ↓
Domain Context
    ↓
WooCommerce / approved integration
```

The browser MUST NOT directly access WooCommerce.

---

# 6. Customer Modes

The active cart supports:

```text
guest
member
```

Guest means:

```text
no WooCommerce customer attached
```

Member means:

```text
valid WooCommerce customer context attached
```

Do not create a fake customer ID for guests.

---

# 7. Customer Lookup

The primary lookup key is:

```text
phone
```

Flow:

```text
Cashier
 ↓
Enter phone
 ↓
Lookup customer
 ↓
Loading
 ↓
Found / Not found / Error
 ↓
Select customer
 ↓
Cart customer context updated
```

---

# 8. Customer Lookup API

Use the existing POS API contract.

Recommended endpoint:

```text
GET /coffeepos/v1/customers/lookup?phone={phone}
```

The response should use the standard API envelope:

```json
{
  "success": true,
  "data": {
    "customer": {
      "id": 123,
      "name": "Customer Name",
      "phone": "0900000000"
    }
  }
}
```

Do not expose unnecessary WooCommerce customer fields.

---

# 9. Customer Lookup Validation

Server-side validation must handle:

```text
missing phone
invalid phone format
customer not found
customer lookup failure
unauthorized request
```

Do not make the browser decide that a customer exists.

---

# 10. Phone Normalization

The project may normalize phone input before lookup.

The normalization rule MUST be consistent.

Do not implement multiple normalization rules across:

```text
JavaScript
REST
Application Service
WooCommerce adapter
```

Prefer:

```text
Request
 ↓
Application Service
 ↓
normalized lookup value
 ↓
WooCommerce gateway
```

The exact normalization policy should remain conservative and must not silently alter a valid number incorrectly.

---

# 11. Customer Projection

Return a safe `CustomerView`.

Possible fields:

```text
id
name
phone
membership
```

Membership may be:

```text
null
```

when no supported membership integration exists.

Do not expose:

```text
passwords
authentication data
internal metadata
unnecessary billing/shipping information
```

---

# 12. Customer Selection

When a customer is found:

```text
Customer result
 ↓
Select
 ↓
CustomerContext
 ↓
CartService.setCustomer()
 ↓
CartView updated
```

The cashier should immediately display the selected customer.

---

# 13. Customer Summary

The Cashier customer section should show:

```text
customer name
phone
member/guest state
```

Optional:

```text
membership status
points
```

only when supported.

---

# 14. Clear Customer

The cashier must be able to remove the current customer context.

Flow:

```text
Selected Customer
 ↓
Clear
 ↓
Guest
 ↓
CartView updated
```

Clearing the customer MUST NOT clear the cart items.

---

# 15. Customer Context Invariant

Cart may be:

```text
guest
```

or:

```text
member + valid customer
```

It MUST NOT hold:

```text
member mode + null customer
```

If the UI switches to Member mode but no customer is selected, the state should remain incomplete and checkout readiness remains false.

---

# 16. Order Type

Supported values:

```text
dine_in
takeaway
```

The Phase-01 domain OrderType rules remain authoritative.

---

# 17. Takeaway

When Takeaway is selected:

```text
order_type = takeaway
table = null
```

If a table was previously selected:

```text
Takeaway
 ↓
clear table context
 ↓
CartView updated
```

This is an explicit invariant.

---

# 18. Dine-in

When Dine-in is selected:

```text
order_type = dine_in
```

A table is then required.

The UI should guide the cashier:

```text
Dine-in selected
 ↓
Select table
```

Until a table is selected:

```text
service context = incomplete
```

---

# 19. Table Context

Initial table context is intentionally small:

```text
id
label
```

Do not create:

```text
table status
occupancy state
reservation
capacity management
floor plan
table CRUD
```

unless a later requirement explicitly introduces them.

---

# 20. Table API

The phase may use:

```text
GET /coffeepos/v1/tables
```

The endpoint should return a selectable table projection:

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 1,
        "label": "T01"
      },
      {
        "id": 2,
        "label": "T02"
      }
    ]
  }
}
```

The table source is deliberately minimal.

The implementation may use documented configuration or another approved source if the project does not yet have a table-management subsystem.

Do not invent a persistent table database during this phase.

---

# 21. Table Selection

Flow:

```text
Dine-in
 ↓
Open Table Selector
 ↓
Load tables
 ↓
Select table
 ↓
CartService.setTable()
 ↓
CartView updated
```

---

# 22. Table Empty State

If no tables are available:

```text
No tables available.
```

Dine-in cannot become checkout-ready.

---

# 23. Table Error State

If table loading fails:

```text
Unable to load tables.
Retry
```

Do not silently fall back to an arbitrary table.

---

# 24. Clear Table

Only allowed/required when:

```text
Takeaway selected
```

or when the user explicitly changes/clears the dine-in context.

When clearing a table while remaining Dine-in:

```text
table = null
```

and the cart becomes incomplete again.

---

# 25. Order-Type UI

The existing Phase-02 order type shell should become functional.

Required actions:

```text
select-dine-in
select-takeaway
open-table-selector
clear-table
```

The browser should render the current state from the CartView/order context projection.

---

# 26. Customer UI

The existing Phase-02 customer shell should become functional.

Required actions:

```text
find-customer
search-customer
select-customer
clear-customer
switch-to-guest
```

---

# 27. Customer Lookup Modal / Drawer

The UI should support:

```text
phone input
search
loading
result
not found
error
select
cancel
```

Example:

```text
┌─────────────────────────────────┐
│ Find Customer                 X │
├─────────────────────────────────┤
│ Phone                           │
│ [ 0900000000               ]   │
│                                 │
│            Search               │
├─────────────────────────────────┤
│ Customer Result                 │
│                                 │
│ Nguyen Van A                    │
│ 0900000000                      │
│                                 │
│             Select              │
└─────────────────────────────────┘
```

Exact visual design is flexible.

Behavior is not.

---

# 28. Customer Search Behavior

The search action should:

```text
validate input
 ↓
show loading
 ↓
call API
 ↓
show result / not found / error
```

Do not call the API on every keystroke.

A single explicit Search action is sufficient for this phase.

---

# 29. Customer Search Race Conditions

Prevent stale responses:

```text
Request A
Request B
```

If B is newer than A, A must not overwrite the selected/current customer UI.

---

# 30. Customer Selection Failure

If applying a selected customer to the cart fails:

```text
keep lookup result
show error
do not switch cart to invalid state
```

---

# 31. Order-Type State Flow

## Takeaway

```text
Current
 ↓
select takeaway
 ↓
set order_type
 ↓
clear table
 ↓
update CartView
```

## Dine-in

```text
Current
 ↓
select dine-in
 ↓
set order_type
 ↓
table required
 ↓
select table
 ↓
update CartView
```

---

# 32. Combined Context Rules

The cart context may be:

```text
Guest + Takeaway
Guest + Dine-in + Table
Member + Takeaway
Member + Dine-in + Table
```

Invalid/incomplete states include:

```text
Member + no customer
Dine-in + no table
```

Takeaway with a table must be normalized to:

```text
Takeaway + no table
```

---

# 33. CartView Extension

Phase 04 should extend the existing CartView without breaking Phase 03 consumers.

The projection must expose:

```text
customer
order_type
table
```

in a stable shape.

Example:

```json
{
  "customer": {
    "mode": "member",
    "id": 123,
    "name": "Customer Name",
    "phone": "0900000000"
  },
  "order_type": "dine_in",
  "table": {
    "id": 5,
    "label": "T05"
  }
}
```

For guest takeaway:

```json
{
  "customer": {
    "mode": "guest"
  },
  "order_type": "takeaway",
  "table": null
}
```

---

# 34. Application Services

Reuse Phase-01 services where possible.

Expected responsibilities:

```text
CustomerService
CartService
CartValidationService
```

Do not create:

```text
CustomerManager
OrderTypeManager
TableManager
```

unless a concrete independent responsibility requires them.

---

# 35. Customer Service

CustomerService should:

```text
lookup by phone
map WooCommerce customer
return CustomerView
```

It should not:

```text
modify orders
change cart
render UI
```

---

# 36. Cart Service Extensions

CartService may expose:

```text
setCustomer()
clearCustomer()
setOrderType()
setTable()
clearTable()
```

All operations must preserve existing cart items.

---

# 37. Cart Validation Extensions

CartValidationService must validate:

```text
customer context
order type
table context
```

Rules:

```text
member → valid customer required
guest → customer null/guest
dine_in → table required
takeaway → table null
```

---

# 38. Membership Boundary

The feature source mentions membership information and points where available, but does not define a loyalty engine.

Therefore:

```text
membership status
points
history
```

are optional projections only.

Do NOT implement:

```text
points accrual
points redemption
reward calculation
free-cup program
loyalty rules
```

in this phase.

---

# 39. Customer History

The feature baseline mentions customer history where available.

Phase 04 MUST NOT implement an independent history subsystem.

If WooCommerce/customer data does not provide the required history, show no history rather than inventing a parallel customer-history system.

---

# 40. API Error Contract

Use the standard API envelope.

Examples:

```text
invalid_customer_phone
customer_not_found
customer_lookup_failed
invalid_customer
invalid_order_type
table_required
table_not_allowed
table_not_found
table_lookup_failed
```

Do not introduce a second response/error structure.

---

# 41. REST Endpoints

Expected endpoints:

```text
GET /coffeepos/v1/customers/lookup
GET /coffeepos/v1/tables
```

If an endpoint already exists from an earlier phase, extend it rather than creating a duplicate route.

---

# 42. REST Rules

Controllers must:

```text
authorize
validate
call application service
serialize result
```

Do not put:

```text
customer lookup logic
order type rules
table rules
```

inside the REST controller.

---

# 43. Security

Customer lookup and table endpoints must use the existing POS capability/permission foundation.

Validate:

```text
phone
customer id
table id
order type
```

Do not trust UI state.

---

# 44. Database Rules

No new database tables are allowed in this phase.

Do not create a table-management database.

Do not create a customer database.

Do not persist the active cart.

Use:

```text
WooCommerce Customer
```

for customer identity.

Use an approved/configured source for table selection.

---

# 45. UI State

The Cashier client store may contain:

```text
customerLookupState
customerMode
selectedCustomer
orderType
selectedTable
tableListState
```

But the final application state comes from the CartView returned by the application.

Do not maintain conflicting customer/order-type/table models in separate components.

---

# 46. Component Structure

Extend Phase-03 components.

Potential additions:

```text
assets/js/
├── api/
│   ├── customers.js
│   └── tables.js
│
├── components/
│   ├── customer-lookup.js
│   ├── customer-summary.js
│   ├── order-type.js
│   └── table-selector.js
│
└── screens/
    └── cashier.js
```

Do not duplicate existing components.

---

# 47. Template Structure

Potential additions:

```text
templates/cashier/
├── customer-section.php
├── order-type-section.php
└── table-selector.php

templates/components/
├── customer-lookup.php
├── customer-summary.php
├── order-type.php
└── table-selector.php
```

Use PHP templates for HTML structure.

Do not build the lookup modal or table selector entirely in JavaScript template strings.

---

# 48. Stable Selectors

Introduce stable contracts such as:

```text
data-component="customer-lookup"
data-component="customer-summary"
data-component="order-type"
data-component="table-selector"
```

Actions:

```text
find-customer
search-customer
select-customer
clear-customer
select-dine-in
select-takeaway
open-table-selector
select-table
clear-table
```

Do not change Phase-03 selectors unnecessarily.

---

# 49. Cart Rendering

When customer/order type/table changes:

```text
CartView updated
 ↓
Cashier store updated
 ↓
Customer summary updated
Order type updated
Table summary updated
Cart remains intact
```

Do not rerender unrelated product grid data unless necessary.

---

# 50. Customer + Product Cart Preservation

Selecting or clearing a customer MUST NOT remove products.

Changing order type MUST NOT remove products.

Changing table MUST NOT remove products.

The only automatic normalization required is:

```text
Takeaway → table cleared
```

---

# 51. Loading States

Customer:

```text
lookup loading
```

Tables:

```text
table loading
```

Cart context:

```text
context update pending
```

Disable the specific action while pending rather than freezing the entire Cashier unless necessary.

---

# 52. Error States

Customer:

```text
not found
invalid phone
lookup error
selection error
```

Table:

```text
no tables
load error
selection error
```

Order type:

```text
invalid state transition
```

Errors must preserve the existing cart whenever possible.

---

# 53. Accessibility

Customer lookup:

```text
label phone input
keyboard submit
focus result
```

Table selector:

```text
keyboard selectable
selected state
focus management
```

Order type:

```text
aria-pressed or equivalent selected state
```

Modal/drawer:

```text
focus
Escape
close
```

---

# 54. Performance

Customer lookup:

- explicit Search action
- no unnecessary polling

Tables:

- load when selector opens or use an approved cached list
- avoid polling

Do not query all WooCommerce customers.

---

# 55. Browser Verification

This phase is UI + integration heavy.

Manual/browser verification is REQUIRED.

Minimum flows:

## Guest Takeaway

```text
Open Cashier
 ↓
Guest
 ↓
Takeaway
 ↓
Cart remains valid
```

## Guest Dine-in

```text
Open Cashier
 ↓
Guest
 ↓
Dine-in
 ↓
Select table
 ↓
Table visible
 ↓
Cart context valid
```

## Member Takeaway

```text
Guest
 ↓
Find Customer
 ↓
Phone
 ↓
Customer found
 ↓
Select
 ↓
Takeaway
 ↓
Customer visible in cart
```

## Member Dine-in

```text
Find Customer
 ↓
Select Customer
 ↓
Dine-in
 ↓
Select Table
 ↓
Both contexts visible
```

## Switch Dine-in → Takeaway

```text
Dine-in + Table
 ↓
Takeaway
 ↓
Table automatically clears
```

## Clear Customer

```text
Member
 ↓
Clear customer
 ↓
Guest
 ↓
Cart items remain
```

---

# 56. Acceptance Criteria

Phase 04 is complete when:

## Customer

1. Guest mode works.
2. Member/customer mode works.
3. Customer can be searched by phone.
4. Loading state is shown.
5. Customer not-found state is shown.
6. Lookup errors are handled.
7. Customer can be selected.
8. Customer summary renders.
9. Customer can be cleared.
10. Clearing customer does not clear cart items.

## Order Type

11. Takeaway can be selected.
12. Dine-in can be selected.
13. OrderType is stored in the Cart domain/application state.
14. Takeaway removes table context.
15. Dine-in requires table.

## Table

16. Table selector opens.
17. Tables can be loaded.
18. Empty table state works.
19. Table error state works.
20. Table can be selected.
21. Selected table appears in cart context.
22. Table can be cleared where valid.

## Combined Context

23. Guest + Takeaway works.
24. Guest + Dine-in + Table works.
25. Member + Takeaway works.
26. Member + Dine-in + Table works.
27. Member without customer is invalid/incomplete.
28. Dine-in without table is invalid/incomplete.

## Integration

29. Customer lookup uses WooCommerce.
30. No duplicate customer storage is created.
31. No table database is created.
32. No WooCommerce order is created.
33. No payment is initiated.

## UI

34. Customer context does not break product/cart behavior.
35. Order-type changes preserve cart items.
36. Stable selectors remain compatible with Phase 03.
37. Loading/error/empty states work.
38. Browser verification passes for all required flows.

---

# 57. Required Test Cases

## Customer

```text
TC-01 guest
TC-02 valid phone lookup
TC-03 invalid phone
TC-04 customer not found
TC-05 customer lookup error
TC-06 select customer
TC-07 clear customer
```

## Order Type

```text
TC-08 select takeaway
TC-09 select dine-in
TC-10 dine-in requires table
TC-11 takeaway clears table
```

## Table

```text
TC-12 load tables
TC-13 empty tables
TC-14 table load error
TC-15 select table
TC-16 clear table
```

## Combined

```text
TC-17 guest + takeaway
TC-18 guest + dine-in + table
TC-19 member + takeaway
TC-20 member + dine-in + table
TC-21 member without customer
TC-22 dine-in without table
```

## Regression

```text
TC-23 add existing cart product
TC-24 edit existing cart product
TC-25 remove existing cart item
TC-26 clear cart
TC-27 product search remains functional
```

---

# 58. Regression Requirements

Phase 04 MUST NOT break Phase 03:

```text
Product search
Category filtering
Simple product
Variable product
Variation selection
Modifier selection
Quick notes
Custom note
Quantity
Add to cart
Edit cart item
Remove item
Clear cart
Cart totals
```

Run the Phase-03 relevant browser flows after implementing Phase 04.

---

# 59. No New PHP CLI Requirement

Do NOT make PHP CLI verification a mandatory Phase-04 gate.

Do NOT perform:

```text
where.exe /r ...
recursive PHP search
recursive Composer search
```

If PHP/Composer is already available in PATH, focused checks may be run.

If not available:

```text
report as BLOCKED
continue with browser/integration verification
```

The UI/browser and application integration behavior are the primary verification targets for this phase.

---

# 60. Definition of Done

Phase 04 is complete only when:

```text
Customer
   ↓
Customer Context
   +
Order Type
   ↓
Table Context
   ↓
CartView
   ↓
Cashier UI
```

works without breaking:

```text
Phase-03 Product / Variation / Cart
```

No checkout or payment functionality may exist yet.

---

# 61. Completion Report

Junie MUST report:

## Changed

All files created/modified.

## Customer

Lookup, selection, clear, projection.

## Order Type

Dine-in/takeaway behavior and invariants.

## Table

Selection source, API, UI behavior.

## Application

Services/gateways changed.

## API

Endpoints added/updated.

## UI

Templates/components/modules changed.

## Tests

Report:

```text
PASS
FAIL
BLOCKED
```

## Browser Verification

List actual flows tested.

## Regression

Confirm Phase-03 product/cart behavior remains functional.

## Scope

Explicitly confirm that these were NOT implemented:

```text
Checkout
Payment
WooCommerce order creation
Customer Display
KDS
Order Queue
Order History
Shift
Reports
Refund
Membership points/rewards
Table management
Suspended Cart
```

## Issues

List remaining issues or assumptions.

---

# 62. Final Phase 04 Rule

When Phase 04 is complete:

STOP.

Do not automatically implement Phase 05.

Phase 05 will introduce:

```text
Checkout
Cash Payment
Bank Transfer
VietQR
WooCommerce Order Creation
Receipt
```

and will depend on the customer/order-type/table context completed here.
