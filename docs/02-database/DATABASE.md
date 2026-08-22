# DATABASE.md

# CoffeePOS Database Architecture

## 1. Purpose

This document defines the persistent data architecture for CoffeePOS.

The primary rule is:

> WooCommerce remains the canonical commerce data store. CoffeePOS only persists POS-specific state that does not belong to WooCommerce.

Every new persistent field, metadata key, or table MUST be documented here before implementation.

---

# 2. Storage Strategy

CoffeePOS uses three storage categories:

```text
A. WooCommerce-native data
B. WooCommerce order/customer/product metadata
C. CoffeePOS-owned storage
```

The implementation MUST choose the smallest storage mechanism that correctly represents the data.

---

# 3. Storage Ownership

## WooCommerce-native data

Use WooCommerce for:

- Product
- Product Variation
- Product Category
- Product Attribute
- Product Price
- Product Stock
- Customer
- Coupon
- Order
- Order Item
- Refund

Do not copy these into CoffeePOS tables as a second source of truth.

---

## WooCommerce metadata

Use order/order-item/customer metadata only for POS context that belongs to the WooCommerce entity.

Examples:

```text
Order
- POS order type
- POS table
- POS shift
- POS cashier
- POS-specific order context
- POS payment reference where appropriate

Order Item
- modifier configuration
- quick notes
- custom item note
```

Exact keys are defined in this document.

---

## CoffeePOS-owned storage

CoffeePOS-specific records may require:

- shift records
- suspended carts
- POS operational logs/projections
- other data that does not naturally belong to a WooCommerce entity

Custom tables are preferred only when the data is:

- independently queryable
- high volume
- relational
- operational
- or poorly represented as metadata

Do not create a custom table simply because it is technically possible.

---

# 4. Order Data Ownership

The WooCommerce order is authoritative.

Conceptual relation:

```text
CoffeePOS Cart
      ↓
WooCommerce Order
      ├── WooCommerce Order Data
      ├── POS Order Metadata
      └── WooCommerce Order Items
             └── POS Item Metadata
```

CoffeePOS must be able to reconstruct the POS-relevant order context from the WooCommerce order.

---

# 5. Order Metadata

The following keys are reserved conceptually for CoffeePOS.

The final implementation should use one stable prefix.

Recommended prefix:

```text
_coffeepos_
```

---

## `_coffeepos_order_type`

Purpose:

Identifies service type.

Allowed values:

```text
dine_in
takeaway
```

Storage:

WooCommerce order meta.

---

## `_coffeepos_table_id`

Purpose:

Stores POS table reference for dine-in orders.

Storage:

WooCommerce order meta.

Rules:

- required for dine-in where table selection is enabled
- should be absent or null for takeaway

---

## `_coffeepos_table_label`

Purpose:

Stores the display label captured at order time.

Reason:

The table display name may change later. Historical orders should retain the context that was displayed when the order was created.

Storage:

WooCommerce order meta.

---

## `_coffeepos_shift_id`

Purpose:

Associates the order with the active CoffeePOS shift.

Storage:

WooCommerce order meta.

---

## `_coffeepos_cashier_id`

Purpose:

Stores the WordPress user ID of the cashier responsible for the POS sale.

Storage:

WooCommerce order meta.

---

## `_coffeepos_payment_method`

Purpose:

Stores the POS-level payment selection when required for reporting/operational display.

Examples:

```text
cash
bank_transfer
```

WooCommerce's own payment method data remains canonical for actual WooCommerce payment integration.

Storage:

WooCommerce order meta only when the native WooCommerce field is insufficient for the POS requirement.

---

## `_coffeepos_payment_reference`

Purpose:

Stores a provider/reference identifier when a POS payment integration requires one.

Storage:

WooCommerce order meta.

Do not store sensitive payment credentials.

---

# 6. Order Item Metadata

Order item metadata is used for information specific to a configured line item.

Recommended prefix:

```text
_coffeepos_
```

---

## `_coffeepos_note`

Purpose:

Free-form customer note for the line item.

Example:

```text
Ít đá, ít ngọt
```

---

## `_coffeepos_quick_notes`

Purpose:

Stores structured quick-note selections.

Preferred representation:

JSON array of stable identifiers.

Example:

```json
[
  "less_ice",
  "less_sweet"
]
```

The identifiers must be stable and documented by the modifier/quick-note implementation.

Do not make human-readable labels the primary identifier.

---

## `_coffeepos_modifiers`

Purpose:

Stores the selected CoffeePOS modifier configuration for an order item when modifiers are not represented by native WooCommerce line items.

Preferred representation:

Structured JSON containing stable identifiers and captured display information.

Example:

```json
{
  "groups": [
    {
      "id": "milk",
      "options": [
        {
          "id": "oat",
          "label": "Oat",
          "price": "10000"
        }
      ]
    }
  ]
}
```

The final schema must be defined by the modifier implementation before Phase 03 completion.

---

# 7. Why Order Item Metadata Is Used

Configured item information belongs to the purchased line.

Do not store item-specific notes/modifiers only on the parent order because:

- multiple items may have different configurations
- KDS needs line-level context
- receipt rendering needs line-level context
- reorder needs line-level configuration

---

# 8. Customer Data

WooCommerce Customer remains canonical.

CoffeePOS MAY store POS-specific context only when required.

Do not duplicate:

```text
name
email
phone
billing address
shipping address
```

into a CoffeePOS customer table.

Membership/points are not fully specified by the feature source.

Until a dedicated membership architecture is approved:

- do not create an independent loyalty database
- do not duplicate WooCommerce customer records

---

# 9. Product Data

Products and variations must be queried through WooCommerce.

CoffeePOS should not persist:

```text
product_name
product_price
variation_price
stock_quantity
```

as authoritative product records.

A temporary cache/projection is possible only when documented and invalidated safely.

---

# 10. Suspended Cart Storage

A suspended cart is temporary POS state, not a WooCommerce order.

It requires a persistent store because it must survive navigation and allow later resume.

Recommended conceptual record:

```text
id
user_id
label
cart_payload
created_at
updated_at
```

The `cart_payload` stores the serialized POS cart configuration needed to restore the cart.

It MUST NOT be treated as authoritative WooCommerce order data.

---

# 11. Suspended Cart Storage Decision

For the initial implementation, use a dedicated CoffeePOS table if the project expects multiple suspended carts and operational querying.

Reason:

- independent lifecycle
- user filtering
- timestamp filtering
- resume/delete operations
- no WooCommerce order should be created
- data is not naturally a WordPress post

The exact SQL schema belongs below in the custom table section.

---

# 12. Shift Storage

A Shift is a CoffeePOS domain object and should have dedicated persistent storage.

It needs:

- independent lifecycle
- active/closed status
- opening/closing values
- reconciliation
- historical querying
- staff association

Do not represent a Shift only as a WordPress option.

Do not represent a Shift as a WooCommerce order.

---

# 13. Shift Table

Recommended table:

```text
{wp_prefix}coffeepos_shifts
```

Columns:

```text
id
user_id
status
opened_at
opening_cash
opening_note
closed_at
actual_cash
closing_note
created_at
updated_at
```

Suggested types:

```text
id              BIGINT UNSIGNED
user_id         BIGINT UNSIGNED
status          VARCHAR(20)
opened_at       DATETIME
opening_cash    DECIMAL(20,6)
opening_note    TEXT
closed_at       DATETIME NULL
actual_cash     DECIMAL(20,6) NULL
closing_note    TEXT NULL
created_at      DATETIME
updated_at      DATETIME
```

Currency amounts should use a fixed decimal representation, not floating-point storage.

---

# 14. Shift Totals

Do not store every derived KPI unless required for performance/audit.

Derived values include:

```text
cash_sales
bank_sales
total_sales
expected_cash
variance
```

Primary formulas:

```text
Expected Cash
=
Opening Cash
+ Cash Sales
- Cash Refunds
```

```text
Variance
=
Actual Cash
- Expected Cash
```

Where practical, derive totals from canonical transactions rather than allowing the UI to write them.

---

# 15. Shift Transaction Model

The initial architecture should avoid duplicating every WooCommerce order into a second transaction ledger unless reporting/performance requirements justify it.

Order-to-shift association is:

```text
WooCommerce Order
      ↓
_coffeepos_shift_id
      ↓
CoffeePOS Shift
```

Shift totals can be calculated from eligible WooCommerce orders and refunds.

If later scale requires a transaction projection table, add it through an explicit architecture change.

---

# 16. Suspended Cart Table

Recommended table:

```text
{wp_prefix}coffeepos_suspended_carts
```

Columns:

```text
id
user_id
label
cart_payload
created_at
updated_at
```

Suggested types:

```text
id             BIGINT UNSIGNED
user_id        BIGINT UNSIGNED
label          VARCHAR(191)
cart_payload   LONGTEXT
created_at     DATETIME
updated_at     DATETIME
```

Indexes:

```text
PRIMARY KEY (id)
INDEX user_id
INDEX created_at
```

---

# 17. Quick Notes Storage

Quick notes may be configuration rather than transaction data.

Initial recommendation:

- store default quick-note definitions in plugin settings/options
- store selected quick-note IDs on order item metadata

Do not create a custom table until there is a requirement for a full admin CRUD system with larger relational data.

---

# 18. POS Settings

Plugin configuration should use the WordPress Options API or an appropriate WooCommerce settings mechanism.

Examples:

```text
POS page
Customer display page
KDS polling interval
Order queue polling interval
Enable/disable customer display
Quick note definitions
Receipt settings
VietQR settings
POS capabilities/configuration
```

Do not put operational transaction data in options.

---

# 19. User/Staff Data

WordPress users remain canonical.

Use:

```text
user_id
```

to associate:

- cashier
- shift
- order context

Do not create a separate CoffeePOS staff table unless a dedicated staff-management feature is later introduced.

---

# 20. Tables That Must NOT Be Created Initially

Do not create separate CoffeePOS tables for:

```text
products
product_variations
customers
orders
order_items
coupons
```

unless a future documented requirement explicitly introduces a read model/cache.

---

# 21. Database Naming

Custom table prefix:

```text
{wp_prefix}coffeepos_
```

Examples:

```text
{wp_prefix}coffeepos_shifts
{wp_prefix}coffeepos_suspended_carts
```

Use lowercase snake_case.

---

# 22. Database Versioning

Custom tables must have a schema version.

The plugin should maintain a database/schema version option such as:

```text
coffeepos_db_version
```

Schema changes must use an explicit migration process.

Do not silently change table definitions at plugin load time.

---

# 23. Migrations

Each migration should be:

- versioned
- deterministic
- idempotent where practical
- logged when failure occurs

Example:

```text
1.0.0
1.1.0
1.2.0
```

The exact migration version strategy can follow the plugin versioning convention.

---

# 24. Deletion / Uninstall

The plugin MUST NOT delete WooCommerce orders/products/customers during uninstall.

CoffeePOS-owned data deletion must be deliberate.

The final uninstall policy should allow a site owner to choose whether CoffeePOS custom data is removed.

---

# 25. Data Integrity

For money:

- use decimal/fixed precision
- never use floating-point database columns for currency

For IDs:

- use WordPress/WooCommerce IDs where applicable

For timestamps:

- store in a consistent server/database representation
- convert for display in the UI

For JSON:

- validate structure before persistence
- version structured payloads where future schema evolution is likely

---

# 26. Required Documentation Before New Storage

Before introducing a persistent field/table, document:

```text
Name
Owner
Purpose
Type
Allowed values
Lifecycle
Writer
Readers
Index requirements
Migration strategy
Deletion policy
```

Junie MUST NOT invent persistent storage without this information.
