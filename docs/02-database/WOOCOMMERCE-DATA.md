# WOOCOMMERCE-DATA.md

# WooCommerce Data Ownership

## Phase-07 operational mapping (2026-08-23)

`new`, `preparing`, and `ready` are CoffeePOS order metadata and never custom
WooCommerce statuses. A Phase-07 `completed` transition sets WooCommerce status
`completed`; an allowed `cancelled` transition sets WooCommerce status
`cancelled`. Terminal WooCommerce status wins over conflicting KDS metadata.
All reads/writes use WooCommerce order CRUD for HPOS compatibility.

## 1. Purpose

This document defines how CoffeePOS interacts with WooCommerce-native data.

WooCommerce is the canonical commerce layer.

---

# 2. Product Retrieval

CoffeePOS should retrieve products through WooCommerce APIs/query mechanisms.

The POS product projection may expose:

```text
id
name
type
price
regular_price
sale_price
stock_status
stock_quantity
image
categories
variation_summary
```

The exact response DTO belongs to the API/UI layer.

---

# 3. Variable Products

A variable product is identified using WooCommerce product type/data.

Variation data comes from WooCommerce.

The POS MUST NOT assume a fixed attribute structure.

Example:

```text
Size
Temperature
Milk
```

may all be product attributes.

The UI should render whatever required variation attributes are defined for the product.

---

# 4. Price

Display price may be loaded to the browser.

Trusted checkout price must be resolved/validated on the server.

The following MUST NOT be treated as authoritative:

```text
JavaScript unit price
JavaScript line total
JavaScript subtotal
JavaScript discount
JavaScript final total
```

---

# 5. Stock

WooCommerce stock is authoritative.

The POS may display:

```text
in stock
out of stock
low stock
```

according to project policy.

Before checkout, revalidate availability.

---

# 6. Customers

WooCommerce customer remains canonical.

Phase-08 lookup normalizes supported local/`+84` input and performs an exact
normalized comparison against WooCommerce billing phone. A narrowing metadata
query may be used, but the final match is never fuzzy.

Member creation uses WooCommerce customer CRUD with required display name and
billing phone plus optional email. CoffeePOS rechecks the normalized phone
under a hashed phone-scoped lock before creation.

If email is omitted, the gateway may use the documented non-routable internal
placeholder required by WooCommerce. The placeholder is marked by reserved
CoffeePOS customer meta and is excluded from Cashier/Customer Display email
projections.

Do not create duplicate customer rows in CoffeePOS.

---

# 7. Coupons

WooCommerce coupon functionality remains authoritative.

CoffeePOS should:

1. receive coupon input
2. validate server-side
3. apply the resulting discount according to WooCommerce rules
4. show the resulting cart/order totals

---

# 8. Orders

The POS must create standard WooCommerce orders.

Conceptually:

```text
Cart
 ↓
WooCommerce Order
 ↓
Order Items
 ↓
Payment
 ↓
Order Status
```

POS-specific metadata is attached to the order/item where necessary.

---

# 9. Order Items

Every purchased line must be represented using WooCommerce order items.

The order item should preserve:

- product
- variation
- quantity
- final line price
- POS-specific note/configuration where needed

---

# 10. Refunds

Refunds must use WooCommerce refund functionality.

Do not create a separate CoffeePOS refund ledger as the authoritative refund system.

Phase 10 uses `wc_create_refund`. Bounded `_coffeepos_refund_operations` metadata
provides request idempotency only; it is not a refund ledger. WooCommerce refund
objects remain authoritative.

---

# 11. Order Status

Do not create custom WooCommerce order statuses just to represent:

```text
KDS new
KDS preparing
KDS ready
```

These should remain CoffeePOS operational states unless the final workflow explicitly requires WooCommerce status changes.

---

# 12. Order Context

POS order metadata should be attached through WooCommerce CRUD APIs where possible.

Avoid raw postmeta writes when a supported WooCommerce API exists.

---

# 13. HPOS Compatibility

The implementation must remain compatible with WooCommerce's supported order storage architecture.

Do not assume orders are stored only in `wp_posts/wp_postmeta`.

Use WooCommerce order CRUD/APIs instead of direct order-table access where possible.

---

# 14. WooCommerce API Boundary

Controllers and UI code should not be responsible for low-level WooCommerce data manipulation.

Prefer:

```text
Application Service
      ↓
WooCommerce Repository/Adapter
      ↓
WooCommerce API
```

---

# 15. Data Conversion

When WooCommerce data enters the CoffeePOS application:

```text
WooCommerce object
      ↓
Adapter
      ↓
CoffeePOS DTO / domain representation
```

Do not pass large WooCommerce objects throughout the entire application without a reason.

---

# 16. Checkout Validation

Before order creation:

- verify products exist
- verify variations belong to products
- verify quantities
- verify stock
- verify customer context
- verify coupon
- resolve pricing
- validate order type/table
- validate payment context

---

# 17. Order Creation Failure

If WooCommerce order creation fails:

- return a stable error
- do not claim payment success
- do not reset the cashier cart as successful
- preserve enough context for safe retry where possible

---

# 18. Reorder

Quick reorder must use the historical WooCommerce order as the source.

Flow:

```text
Old Order
 ↓
Order Items
 ↓
Current WooCommerce Product Validation
 ↓
New Cart
```

Reorder must not blindly copy historical prices or availability.

`_coffeepos_reorder_operations` is a bounded idempotency pointer to a generated
POS cart session. It does not store cart contents or historical pricing.

---

# 19. Reporting

Where WooCommerce provides suitable queryable order/product data, reporting should use it.

Do not duplicate order totals into a separate report table without a measured performance/data-warehouse requirement.

Phase 11 queries bounded batches of WooCommerce `processing`, `completed`, and
`refunded` CoffeePOS orders by creation date. Net revenue subtracts canonical
WooCommerce refunds from the originating order period. Product quantity and
revenue subtract only item-attributed refund data; amount-only refunds remain
explicitly unallocated. Monetary aggregates are separated by order currency.

CSV and XLSX exports are generated from the same application ReportView as the
screen. They are transient response files and add no persistent report storage.

---

# 20. Phase-12 Notes and Staff Ownership

WordPress users remain canonical for staff identity and authentication.
CoffeePOS stores only the responsible WordPress user ID where the transaction
requires it; it does not duplicate usernames, password hashes, PINs, roles, or
authentication sessions.

The order-level note is CoffeePOS private WooCommerce order metadata. Item
quick-note selections and item free-form notes remain WooCommerce order-item
metadata. These fields are written through WooCommerce CRUD so HPOS and classic
storage compatibility are preserved. Receipt, KDS, Queue, and History
projections read the same canonical order and approved metadata rather than a
separate receipt or note table.
