# PHASE-01.md

# CoffeePOS Phase 01 — Application & Domain Core

## 1. Objective

Build the first real CoffeePOS application/domain layer on top of the completed Phase 00 infrastructure.

This phase establishes the reusable core that later POS screens and workflows will call.

The core objective is:

```text
WooCommerce / WordPress
        ↓
CoffeePOS Integration
        ↓
Application Services
        ↓
CoffeePOS Domain Objects
        ↓
Reusable results / DTOs
        ↓
Future Cashier / KDS / Customer Display / Admin UI
```

At the end of this phase:

- CoffeePOS has meaningful domain objects for the core POS workflow.
- Application services exist for operations required by upcoming phases.
- WooCommerce access is isolated behind integration/gateway boundaries.
- Cart state can be represented and validated without a UI.
- Product and variation data can be converted into POS-safe representations.
- Customer context can be represented without duplicating WooCommerce customers.
- Order type and table context can be represented.
- Payment context/state can be represented without implementing payment processing.
- Application results/errors have stable structures.
- Core behavior can be tested without requiring Cashier UI code.
- Phase 02 can build the POS shell against these contracts.

This phase MUST NOT implement the Cashier UI workflow.

---

# 2. Prerequisites

Junie MUST read:

```text
JUNIE.md

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

docs/05-phases/PHASE-00.md
```

Junie MUST inspect the actual Phase 00 implementation before starting.

Do not assume the examples in the documents correspond exactly to class names already created.

---

# 3. Scope

## In Scope

```text
Domain value objects
Cart domain
Cart item domain
Order type
Table context
Customer context
Product/variation DTOs
Modifier/quick-note representation foundation
Application service layer
WooCommerce integration/gateway layer
Product retrieval service
Variation validation foundation
Customer lookup service
Cart validation service
Cart calculation/projection foundation
Coupon validation boundary
Error/result contracts
Dependency boundaries
Unit-testable core
```

## Out of Scope

Do NOT implement:

```text
Cashier screen UI
Product grid UI
Product modal UI
Cart UI
Customer Display UI
KDS UI
Order Queue UI
Order History UI
Checkout UI
Cash payment processing
Bank transfer processing
VietQR generation
WooCommerce order creation
Receipt printing
Shift opening/closing
Shift reconciliation
Refund workflow
Reports
Analytics
Suspended cart persistence workflow
Stock adjustment UI/workflow
Membership points system
Table management system
```

---

# 4. Core Architectural Goal

Phase 01 must establish:

```text
Presentation
    ↓
Application
    ↓
Domain
    ↓
Integration
    ↓
WooCommerce
```

The Domain layer MUST NOT depend directly on:

```text
WordPress globals
WooCommerce objects
REST request objects
HTTP responses
PHP templates
JavaScript
```

The Application layer may depend on domain types and integration interfaces.

The Integration layer converts WooCommerce data to CoffeePOS representations.

---

# 5. Domain Model To Implement

The following concepts are required in this phase.

## 5.1 Product Projection

A POS-safe product representation.

Conceptual fields:

```text
id
name
type
price
image
stock_status
stock_quantity where allowed
category_ids
has_variations
```

This is NOT a second product entity.

It is a read model / DTO representing WooCommerce product data needed by the POS.

---

## 5.2 Variation Projection

Conceptual fields:

```text
id
product_id
attributes
price
stock_status
stock_quantity where allowed
purchasable
```

The attributes collection must remain dynamic.

Do NOT hard-code:

```text
size
temperature
milk
```

as domain properties.

---

## 5.3 Cart

Cart is the main Phase 01 aggregate.

Conceptual structure:

```text
Cart
├── items[]
├── customer_context
├── order_type
├── table_context
├── coupon_context
└── totals/projection
```

The Cart is a pre-checkout POS aggregate.

It is NOT a WooCommerce Order.

---

# 6. CartItem

CartItem represents one configured product line.

Required conceptual fields:

```text
key
product_id
variation_id
quantity
configuration
quick_notes
custom_note
```

The implementation may also carry product/variation snapshots required for UI projection.

However:

> snapshots are not authoritative price/stock truth.

---

# 7. Cart Item Identity

Cart item identity must be deterministic enough to distinguish:

```text
same product
different variation
different modifiers
different notes/configuration
```

Two identical product configurations may merge quantities if the cart policy allows.

Two configurations with materially different purchase context must remain separate cart items.

The identity strategy must be implemented once and reused by Cart operations.

Do not create one-off identity logic in JavaScript.

---

# 8. Quantity Rules

Cart domain must enforce:

```text
quantity >= 1
```

Decrease operation must not create a zero/negative active item.

Removing an item is a separate domain operation.

Server-side validation remains authoritative.

---

# 9. Order Type

Implement a value object/enum-like representation:

```text
dine_in
takeaway
```

Rules:

```text
dine_in → table context required
takeaway → table context must be absent/cleared
```

Do not support additional order types in this phase.

---

# 10. Table Context

Implement a small value object representing:

```text
id
label
```

The initial phase does not implement full table management.

This object only represents selected table context attached to the current cart.

---

# 11. Customer Context

Implement a POS customer context:

```text
guest
member
```

Member context may contain:

```text
customer_id
name
phone
membership projection where available
```

Do not create a CoffeePOS customer repository backed by a custom customer table.

WooCommerce remains the source of truth.

---

# 12. Membership Boundary

Membership/points are not sufficiently specified for a full implementation.

Phase 01 MUST create only a boundary/interface if later application services need it.

Example conceptual boundary:

```text
MembershipProviderInterface
```

Do not implement a custom loyalty database.

Do not invent points calculations.

---

# 13. Modifier Foundation

The domain must be able to carry product configuration without coupling the core to one hard-coded modifier implementation.

Conceptual structure:

```text
ModifierSelection
├── group_id
└── options[]
```

An option may contain:

```text
id
label
quantity where required
price adjustment
```

The exact persistence schema remains governed by `DATABASE.md`.

Phase 01 only establishes a representation that Phase 03 can use.

Do not build admin modifier CRUD.

---

# 14. Quick Note Foundation

Quick notes should use stable identifiers.

Conceptual:

```text
QuickNoteSelection
id
label
```

The domain should preserve IDs.

Do not make labels the primary identity.

Phase 01 does not build quick-note administration.

---

# 15. Price Representation

All monetary values in the application/domain layer must use a safe money representation.

Preferred conceptual model:

```text
Money
- amount
- currency
```

Do not use floating-point arithmetic for authoritative money calculations.

The exact WooCommerce currency integration must remain isolated in the integration layer.

---

# 16. Totals

The Cart domain/application layer may provide a projection containing:

```text
subtotal
discount
total
```

However:

- display calculations may be provisional
- authoritative checkout totals must be recalculated later
- WooCommerce pricing/coupon rules remain authoritative

Do not implement tax/fee business rules unless required by WooCommerce integration for the current cart projection.

---

# 17. Coupon Context

Implement a lightweight coupon context:

```text
code
status
discount projection
```

The coupon service must validate through WooCommerce.

Do not implement a custom coupon engine.

---

# 18. Payment Context

Phase 01 only establishes representation.

Conceptual:

```text
PaymentContext
├── method
├── state
└── amount
```

Supported method identifiers:

```text
cash
bank_transfer
```

Supported baseline state identifiers:

```text
unpaid
pending
paid
failed
refunded
```

Do not implement actual payment processing in Phase 01.

---

# 19. Application Services

The following services should exist where their responsibility is concrete.

## ProductService

Responsibilities:

- retrieve products
- retrieve product details
- retrieve categories if needed
- return POS-safe projections

Must not return raw WooCommerce objects to the presentation layer.

---

## VariationService

Responsibilities:

- retrieve variations
- validate variation belongs to product
- resolve a variation from selected attributes
- return POS-safe variation projection

Do not assume a specific attribute count.

---

## CustomerService

Responsibilities:

- lookup customer by phone
- return safe customer projection

Do not expose unnecessary customer data.

---

## CartService

Responsibilities:

- create/restore cart
- add item
- update item
- remove item
- clear cart
- change quantity
- set customer
- set order type
- set table
- set coupon context
- return cart projection

The service coordinates domain objects and infrastructure.

---

## CartValidationService

Responsibilities:

- validate product
- validate variation
- validate configuration
- validate quantity
- validate stock
- validate customer context
- validate order type/table
- validate coupon context

This validation prepares the cart for later checkout but does NOT create an order.

---

## CouponService

Responsibilities:

- validate coupon code
- return coupon/discount projection

Use WooCommerce.

Do not implement discount rules independently.

---

# 20. Integration Boundaries

Create interfaces/abstractions only where the application actually needs an external dependency.

Potential boundaries:

```text
ProductGatewayInterface
VariationGatewayInterface
CustomerGatewayInterface
CouponGatewayInterface
StockGatewayInterface
```

Do not create a giant:

```text
WooCommerceService
```

that owns every integration responsibility.

---

# 21. WooCommerce Adapter

Implement WooCommerce-specific adapters in the Integration layer.

Responsibilities:

```text
WooCommerce object
      ↓
adapter
      ↓
CoffeePOS DTO/projection
```

The application/domain layer should not depend on WooCommerce object types.

---

# 22. Product Gateway Rules

The Product gateway should:

- retrieve WooCommerce products
- map them to POS-safe projections
- expose only required fields

It MUST NOT:

- create products
- update products
- modify stock

unless a later feature explicitly requires it.

---

# 23. Variation Gateway Rules

The Variation gateway should:

- retrieve variations
- map attributes
- map price
- map stock/purchasability
- resolve configured variation

Variation matching must be based on WooCommerce attribute data.

Do not assume:

```text
Object.values(attributes)[0]
```

is sufficient.

All required attributes must be considered.

---

# 24. Customer Gateway Rules

Customer lookup should use WooCommerce customer mechanisms.

Phone lookup may normalize the search input according to a documented rule, but the implementation must not create duplicate customer records.

---

# 25. Stock Boundary

Stock is an external authoritative concern.

Provide a read/validation boundary.

Do not implement stock mutation in Phase 01.

---

# 26. DTOs / Projections

Application outputs should use explicit data structures.

Examples:

```text
ProductView
VariationView
CustomerView
CartView
CartItemView
CouponView
PaymentView
```

The exact class/array implementation may follow project style, but response shape must be stable.

Do not expose WooCommerce objects.

---

# 27. Error Contract

Use stable application error codes.

Relevant Phase 01 codes:

```text
invalid_product
invalid_variation
variation_not_found
invalid_quantity
out_of_stock
invalid_order_type
table_required
table_not_allowed
customer_not_found
invalid_customer
invalid_coupon
invalid_configuration
```

Each error should preserve:

```text
code
message
context/details where safe
```

---

# 28. Domain Invariants

The following MUST be enforced by domain/application logic.

### Order type

```text
dine_in → table required
takeaway → table absent
```

### Quantity

```text
quantity >= 1
```

### Product/variation

```text
variation belongs to selected product
```

### Cart

```text
cart may be empty during editing
cart cannot be considered checkout-ready when empty
```

### Customer

Customer data is either:

```text
guest
or
valid WooCommerce customer context
```

---

# 29. Cart Operations

Implement these operations:

```text
create
addItem
updateItem
removeItem
clear
setCustomer
setOrderType
setTable
applyCouponContext
removeCouponContext
validate
```

Do not implement checkout in these operations.

---

# 30. Cart State Transitions

Use the documented cart state model:

```text
EMPTY
 ↓
ACTIVE
 ↓
CHECKOUT
```

For Phase 01, only the foundation for:

```text
EMPTY
ACTIVE
```

needs to be implemented.

The checkout transition belongs to Phase 05.

Suspended cart persistence belongs to a later phase.

---

# 31. Cart Projection Example

Conceptual result:

```json
{
  "items": [
    {
      "key": "product-123-variation-456-config-abc",
      "product_id": 123,
      "variation_id": 456,
      "quantity": 2,
      "configuration": [],
      "quick_notes": [],
      "note": "",
      "unit_price": "45000.00",
      "line_total": "90000.00"
    }
  ],
  "customer": null,
  "order_type": "takeaway",
  "table": null,
  "coupon": null,
  "totals": {
    "subtotal": "90000.00",
    "discount": "0.00",
    "total": "90000.00"
  }
}
```

This is an application projection.

It is not a persisted WooCommerce order.

---

# 32. Price Calculation Boundary

The first implementation may calculate display totals from validated product/variation data.

However:

```text
CartService
    ↓
display projection
```

does not authorize final checkout price.

Phase 05 MUST perform final trusted price/order calculation through WooCommerce.

Do not design Phase 01 in a way that makes the browser's total authoritative.

---

# 33. Application Service Dependency Rules

Allowed:

```text
CartService
 → Domain
 → ProductGatewayInterface
 → CustomerGatewayInterface
 → CouponGatewayInterface
```

Forbidden:

```text
CartService
 → REST controller
CartService
 → PHP template
CartService
 → JavaScript
Domain
 → WooCommerce Product object
Domain
 → WordPress global state
```

---

# 34. Testing Strategy

Phase 01 must prioritize tests that do not require a full browser.

Test:

```text
OrderType rules
Table rules
Cart quantity
Cart item identity
Cart add/update/remove
Customer context
Variation matching
Product/variation validation
Coupon boundary behavior
Error codes
Money behavior
```

Integration tests should cover:

```text
WooCommerce product mapping
WooCommerce variation mapping
Customer lookup
Coupon validation
```

Where the local WooCommerce runtime is unavailable, clearly report integration tests as blocked.

Do not fake a passing result.

---

# 35. Acceptance Criteria

Phase 01 is complete when:

1. Domain classes represent the documented core concepts.
2. Cart can be created without UI code.
3. Cart can add a valid product configuration.
4. Cart item identity is deterministic.
5. Cart quantity rules are enforced.
6. Cart item can be updated.
7. Cart item can be removed.
8. Cart can be cleared.
9. Order type rules are enforced.
10. Dine-in requires table context.
11. Takeaway clears/disallows table context.
12. Customer context can be attached.
13. Customer lookup can return a safe projection.
14. Product mapping from WooCommerce works.
15. Variation mapping works with multiple attributes.
16. Invalid variation selection is rejected.
17. Stock validation has an integration boundary.
18. Coupon validation delegates to WooCommerce.
19. Money values do not depend on floating-point arithmetic.
20. Application services do not expose raw WooCommerce objects.
21. REST/controller/presentation layers do not contain domain logic.
22. No UI business implementation is added.
23. No WooCommerce order is created.
24. No payment processing is implemented.
25. Core logic is testable independently of Cashier UI.

---

# 36. Required Test Cases

## Cart

```text
TC-01 empty cart
TC-02 add simple product
TC-03 add variable product
TC-04 add same configuration twice
TC-05 add different configurations
TC-06 increase quantity
TC-07 decrease quantity
TC-08 remove item
TC-09 clear cart
```

## Variation

```text
TC-10 one attribute
TC-11 multiple attributes
TC-12 invalid attribute combination
TC-13 variation does not belong to product
TC-14 unavailable variation
```

## Order Type

```text
TC-15 dine-in without table → reject
TC-16 dine-in with table → accept
TC-17 takeaway without table → accept
TC-18 takeaway with table → clear/reject according to domain rule
```

## Customer

```text
TC-19 guest customer
TC-20 valid customer
TC-21 customer not found
```

## Coupon

```text
TC-22 valid coupon
TC-23 invalid coupon
TC-24 expired/rejected WooCommerce coupon
```

## Security

```text
TC-25 unauthorized application operation
TC-26 invalid product ID
TC-27 invalid variation ID
```

---

# 37. Files To Create

The exact final file names may vary if the existing Phase 00 conventions require it.

Junie SHOULD create a coherent structure similar to:

```text
includes/
├── Domain/
│   ├── Cart/
│   ├── Customer/
│   ├── Order/
│   ├── Payment/
│   ├── Product/
│   └── Shared/
│
├── Application/
│   ├── Cart/
│   ├── Customer/
│   ├── Product/
│   └── Coupon/
│
└── Integration/
    └── WooCommerce/
```

Do not create every possible class speculatively.

---

# 38. Suggested Domain Classes

Only create classes required by implementation.

Potential classes:

```text
Cart
CartItem
OrderType
TableContext
CustomerContext
ModifierSelection
QuickNoteSelection
Money
PaymentContext
```

---

# 39. Suggested Application Classes

Potential classes:

```text
CartService
CartValidationService
ProductService
VariationService
CustomerService
CouponService
```

---

# 40. Suggested Integration Classes

Potential classes:

```text
WooCommerceProductGateway
WooCommerceVariationGateway
WooCommerceCustomerGateway
WooCommerceCouponGateway
WooCommerceStockGateway
```

Interfaces should live at the application/domain boundary when practical.

---

# 41. Do Not Build

Do NOT create:

```text
WooCommerceService.php
CoffeePOSManager.php
POSHelper.php
GodController.php
CartController with all business logic
```

Avoid vague classes that become dumping grounds.

---

# 42. Template / UI Rule

Phase 01 should not build new cashier UI.

Existing Phase 00 placeholder templates remain unchanged unless a tiny compatibility fix is required.

No product cards.

No product modal.

No cart UI.

No checkout modal.

---

# 43. REST Rule

Phase 01 may add internal/application-facing REST endpoints only if they are required to verify the application core or are explicitly needed by Phase 02.

Do not implement the full POS API yet.

Do not expose every service through REST just because the service exists.

---

# 44. Database Rule

Do not add new persistent tables in Phase 01.

Do not modify the Phase 00 schema unless a documented architecture gap is found.

Cart remains an in-memory/application state object.

Suspended Cart persistence remains out of scope.

---

# 45. Performance Rule

Avoid unnecessary WooCommerce object loading.

For product list data:

- retrieve only required fields where practical
- avoid loading full variation objects for unrelated simple products
- do not perform N+1 queries deliberately

Do not prematurely introduce a cache.

---

# 46. Security Rule

Even though Phase 01 is mostly application code:

- never trust product IDs from caller input
- never trust variation IDs
- never trust quantity
- never trust customer IDs
- never trust coupon validity
- never trust price from frontend
- never bypass capability requirements of calling transport

---

# 47. Completion Report

Junie MUST report:

## Changed

Files created/modified.

## Domain

List implemented domain objects and invariants.

## Application

List services and their responsibilities.

## Integration

List WooCommerce adapters/gateways.

## API

Report any endpoints added.

## Tests

Report unit/integration checks.

## Not Implemented

Explicitly confirm:

```text
no Cashier UI
no Cart UI
no Checkout
no Payment
no WooCommerce order creation
no Customer Display
no KDS
no Shift logic
no Reports
```

## Issues

Report:

- WooCommerce runtime blockers
- unresolved architecture questions
- any assumptions

---

# 48. Definition of Done

Phase 01 is complete only when:

```text
Domain core exists
        ↓
Application services exist
        ↓
WooCommerce adapters exist
        ↓
Cart can be manipulated without UI
        ↓
Core validation works
        ↓
Tests cover invariants
        ↓
No Phase-02+ UI/business flow exists
```

The code must remain consistent with all architecture documents.

---

# 49. Final Phase 01 Rule

When Phase 01 is complete:

STOP.

Do not automatically implement Phase 02.

The next phase must be explicitly started using:

```text
PHASE-02.md
```
