# DOMAIN-MODEL.md

# CoffeePOS Domain Model

## 1. Purpose

This document defines the conceptual domain model for CoffeePOS and separates:

- WooCommerce entities
- CoffeePOS domain objects
- application/use-case data
- UI-only state

The purpose is to prevent duplicated commerce models and arbitrary persistence.

---

# 2. Entity Ownership

## 2.1 WooCommerce-Owned Entities

WooCommerce is canonical for:

```text
Product
Product Variation
Product Attribute
Product Category
Customer
Coupon
Order
Order Item
Refund
Stock
Price
```

CoffeePOS reads and uses these entities.

CoffeePOS MUST NOT create a second canonical product/order/customer system.

---

# 2.2 CoffeePOS-Owned Domain Objects

CoffeePOS-specific concepts include:

```text
Cart
CartItem configuration
Order Context
Order Type
Table Context
Suspended Cart
Shift
Shift Reconciliation
KDS workflow state
Customer Display session
POS payment context
Receipt context
```

Some of these may map to WooCommerce data at persistence time.

---

# 3. Product

A Product represents WooCommerce catalog data exposed to the POS.

Relevant display information can include:

```text
id
name
type
image
price
stock_status
stock_quantity
categories
variations
```

The POS must not assume every product has the same attributes.

`CatalogView` is an application projection, not a new domain aggregate. It
groups POS-visible WooCommerce products under ordered WooCommerce categories and
is shared by Cashier and Customer Display. Screen-specific templates decide
whether a product is interactive, compact, disabled, or hidden without changing
canonical product identity or price.

---

# 4. Product Variation

A Product Variation represents a WooCommerce variation.

Conceptually:

```text
Product
└── Variation
    ├── attributes
    ├── price
    ├── stock
    └── availability
```

A variable product may require one or more variation attributes.

The UI MUST use product metadata rather than hard-coding attributes such as "Size".

---

# 5. Modifier / Option

Modifiers are CoffeePOS configuration choices that do not change price in the
current architecture. Choices that affect price must be modeled as WooCommerce
variations so WooCommerce remains the pricing source of truth.

Therefore the initial domain concept is:

```text
Modifier
ModifierGroup
ModifierOption
```

Conceptually:

```text
ModifierGroup
└── ModifierOption
```

Example:

```text
Milk
├── Regular
├── Oat
└── Soy
```

Modifier definitions are stored in CoffeePOS settings with stable group/option
IDs, labels, selection rules, enabled state, sort order, and optional
product/category applicability. Product detail projections expose only the
modifier groups applicable to the selected product.

Modifiers MUST NOT be implemented as arbitrary undocumented post meta.

---

# 6. Cart

Cart is a temporary POS domain aggregate.

```text
Cart
├── pos_session_id
├── revision
├── currency
├── CartItem[]
├── CustomerContext
├── OrderType
├── TableContext
├── CouponContext
├── order_note
└── Totals
```

The cart exists before a WooCommerce order is created.

The cart is NOT a WooCommerce order.

The active Cart is stored in the WooCommerce session and addressed by an opaque
`pos_session_id`. It also has a monotonic `revision` used for concurrency and
Cashier/Customer Display synchronization. The identifier is not a credential
and must not expose the WooCommerce session token.

`order_note` is one private, order-level plain-text note. It is revisioned with
the cart aggregate, is not an item note, and is not projected to the Customer
Display by default.

---

# 7. CartItem

A CartItem represents one configured purchasable line in the POS cart.

Conceptual fields:

```text
key
product_id
variation_id
quantity
configuration
note
unit_price_display
line_total_display
```

The final trusted price must be resolved/validated server-side.

The browser does not create a CartItem with an authoritative unit price. The
application resolves product/variation price through WooCommerce, then creates
or refreshes the CartItem using that price. Modifier and quick-note selections
do not alter price.

A cart item may include:

```text
variation selections
modifier selections
quick notes
custom note
```

---

# 8. Customer Context

Customer context represents the customer attached to the current POS cart.

Conceptual modes:

```text
guest
member
```

Member context may contain:

```text
customer_id
name
phone
masked phone projection
membership information
points/balance
```

WooCommerce remains canonical for member customer identity. Phase 08 adds
WooCommerce-backed member creation and exact normalized-phone identification;
it does not add an independent CoffeePOS customer table.

The Cashier may receive the full phone for identity confirmation. Customer
Display receives a dedicated safe projection containing only a masked phone,
for example `0353***250`, and must not receive/render full phone or email.

Loyalty benefits remain isolated behind the membership abstraction. Points,
buy-five-get-one progress, tier calculation, and tier coupon rules are not
defined by the identity model and must not be invented by clients.

---

# 9. Order Type

Supported values:

```text
dine_in
takeaway
```

Dine-in may carry table context. When `coffeepos_require_dine_in_table` is
enabled (the default), application validation requires a configured table;
when disabled, the domain permits dine-in with `TableContext::none()`.

Takeaway does not require a table.

---

# 10. Table Context

Table context is POS service information.

Conceptually:

```text
table_id
table_label
```

The initial scope does not define a full table-management subsystem.

Do not build a large table-management domain unless a later requirement requires it.

---

# 11. Coupon Context

A cart may have zero or more coupon references depending on WooCommerce coupon rules.

Coupon validity remains a WooCommerce/server-side concern.

The client must not determine coupon validity.

---

# 12. Suspended Cart

A Suspended Cart is a saved pre-checkout POS cart.

It contains enough information to resume the current ordering workflow.

Conceptually:

```text
SuspendedCart
├── id
├── label
├── created_at
├── updated_at
├── cashier/user context
└── serialized cart state
```

The storage method is defined in `DATABASE.md`.

A suspended cart MUST NOT automatically become a WooCommerce order.

---

# 13. Order

The canonical order is the WooCommerce Order.

CoffeePOS creates and enriches the WooCommerce order with POS context.

Possible POS order metadata includes:

```text
order_type
table
cashier
shift
POS payment method
POS notes/context
```

Exact keys and persistence rules belong in `DATABASE.md`.

The Phase-12 order-level note is persisted as `_coffeepos_order_note`. It is
staff-private by default and is not duplicated onto each order item.

---

# 14. Order Item

The canonical purchasable line is the WooCommerce Order Item.

CoffeePOS-specific line information may include:

```text
variation configuration
modifier configuration
quick notes
custom notes
```

The exact storage strategy must be documented.

Order item pricing MUST ultimately be consistent with WooCommerce order totals.

---

# 15. Payment Context

Payment is a workflow/domain context around the WooCommerce order and payment integration.

Conceptual model:

```text
Payment
├── method
├── state
├── amount
├── transaction/reference
└── timestamps
```

Supported baseline methods:

```text
cash
bank_transfer
```

Payment state is not determined by the browser.

---

# 16. Shift

A Shift represents one cashier operating session.

Conceptual fields:

```text
id
user_id
opened_at
opening_cash
opening_note
status
closed_at
actual_cash
closing_note
```

Derived values:

```text
cash_sales
bank_sales
total_sales
expected_cash
variance
```

The exact persistence structure belongs in `DATABASE.md`.

---

# 17. KDS Order Context

KDS uses WooCommerce orders as the underlying order entity, with CoffeePOS preparation state.

Conceptual KDS state:

```text
new
preparing
ready
completed
```

Timing information:

```text
received_at
started_at
ready_at
completed_at
```

Elapsed time is derived from server/order timestamps where possible.

The feature source explicitly requires warning after 5 minutes and critical state after 10 minutes.

---

# 18. Customer Display Session

Customer Display is a presentation/session concept.

It should not become a second order store.

It consumes synchronized cashier/order/payment state.

Conceptually:

```text
CustomerDisplaySession
├── screen_state
├── cart_projection
├── customer_projection
└── payment_projection
```

It may store local browser state, but authoritative business data remains server-side.

---

# 19. Receipt Context

Receipt is a presentation/output concept.

It may contain:

```text
order_id
store information
customer
items
totals
payment
table/order type
cashier
created time
optional private order note
```

Receipt printing must use the canonical WooCommerce order data plus only the
documented CoffeePOS order/order-item metadata. One authoritative ReceiptView is
shared by checkout printing, Order Queue reprinting, and Order History
reprinting. A receipt may not print until the projection and template are fully
populated.

---

# 20. Reports

Reports are projections/queries over canonical data.

Reports MUST NOT create a second source of truth for revenue.

Examples:

```text
Revenue
Order count
AOV
Products sold
Payment composition
Best sellers
Peak hours
```

Shift reports may additionally use Shift-owned data.

---

# 21. Aggregate Boundaries

Primary boundaries:

```text
Cart Aggregate
    └── CartItem[]

Shift Aggregate

SuspendedCart Aggregate

KDS Workflow Context

Payment Workflow Context
```

WooCommerce remains outside CoffeePOS domain aggregates and is accessed through integration/application boundaries.

---

# 22. Domain Rules

### Cart

- quantity must be positive
- cart item must identify a purchasable WooCommerce product/variation
- required variations must be selected
- active cart must belong to the current WooCommerce session and `pos_session_id`
- each successful mutation increments the cart revision
- stale expected revisions must not overwrite newer cart state
- product/variation price must be resolved through WooCommerce
- modifiers and quick notes do not change price
- totals must be validated server-side

### Order Type

- dine-in requires table context when the CoffeePOS table requirement is enabled
- dine-in without table context is valid only when that requirement is disabled
- takeaway does not require table context

### Payment

- amount must be positive where payment is required
- cash received must not be less than total
- payment success must not be inferred solely from UI state

### Shift

- only one active shift should normally exist for a cashier/user according to the final shift policy
- closing a shift must produce a deterministic reconciliation

### KDS

- state transitions must follow the documented state machine
- elapsed time must be calculated consistently

---

# 23. Domain vs UI State

## Domain/Application State

Examples:

```text
Cart items
Customer identity
Order type
Table
Coupon
Shift state
Payment state
KDS state
```

## UI-Only State

Examples:

```text
active category tab
search input text
currently open modal
modal step
selected visual tab
toast visibility
loading indicator
temporary focus
```

UI-only state MUST NOT be persisted as domain data.

---

# 24. Domain Model Gaps

The source feature specification does not fully define:

- membership/points storage
- full table management
- payment-provider abstraction
- receipt-printer implementation
- suspended-cart storage

These are architectural decisions that must be resolved before implementation reaches the affected phase.

Do not silently assume a storage model.
