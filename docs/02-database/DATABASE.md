# DATABASE.md

# CoffeePOS Database Architecture

## Phase-07 operational order metadata (2026-08-23)

KDS state remains WooCommerce order metadata written through WooCommerce CRUD:

```text
_coffeepos_kds_state              new|preparing|ready|completed|cancelled
_coffeepos_kds_revision           non-negative monotonic integer
_coffeepos_kds_received_at        UTC ISO-8601
_coffeepos_kds_started_at         UTC ISO-8601
_coffeepos_kds_ready_at           UTC ISO-8601
_coffeepos_kds_completed_at       UTC ISO-8601
_coffeepos_kds_cancelled_at       UTC ISO-8601
_coffeepos_kds_operations         JSON, last eight idempotency entries
```

The operations ledger contains only operation ID, request fingerprint, target
state, resulting revision, and completion timestamp. It is not an order/event
store. Order Queue is queried from WooCommerce and has no persistence table.

Phase-07 configuration uses Options API keys:

```text
coffeepos_kds_poll_interval_ms
coffeepos_order_queue_poll_interval_ms
```

Both default to `5000` and are clamped to `3000..60000` milliseconds.

## Approved manual bank confirmation audit (2026-08-23)

Pre-order VietQR preview is derived from the WooCommerce session cart and is
not persisted. A manually confirmed bank-transfer order records these approved,
HPOS-compatible WooCommerce order meta keys through CRUD APIs:

```text
_coffeepos_bank_confirmed_by
_coffeepos_bank_confirmed_at
```

The values are the authorized cashier user ID and UTC confirmation timestamp.

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

## WooCommerce session data

The active POS cart is temporary server-side state stored through the
WooCommerce session API. It is not stored in a CoffeePOS table, WordPress
options, browser local storage, or a draft WooCommerce order.

The CoffeePOS session payload is keyed by an opaque `pos_session_id` and
contains at minimum:

```text
revision
currency
items
customer context
order type
table context
coupon context
updated_at
```

`pos_session_id` is a logical cart identifier, not the WooCommerce session token
and not an authorization credential. Multiple logical carts may coexist in one
WooCommerce session only when explicitly addressed by different IDs.

Active cart lifetime follows the WooCommerce session. A cart that must survive
session expiry is explicitly suspended and moved to Suspended Cart storage.

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

## `_coffeepos_order_note`

Purpose:

Stores the cashier-entered note for the whole order, distinct from every
line-item note.

Storage:

WooCommerce private order meta written through WooCommerce CRUD.

Rules:

- sanitized plain text, maximum 2000 characters
- staff-private and excluded from Customer Display projections
- displayed on KDS, Order Queue detail, and Order History detail
- printed only when `coffeepos_receipt_print_order_note` is enabled
- not copied by quick reorder

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

Phase-12 write representation:

JSON array of objects containing the stable ID and the label captured at order
creation time.

Example:

```json
[
  {"id": "less_ice", "label": "Ít đá"},
  {"id": "less_sugar", "label": "Ít đường"}
]
```

Readers MUST also accept the legacy JSON array of string IDs. Quick reorder
extracts stable IDs from either representation, validates them against the
current enabled/applicable configuration, and never treats a historical label
as authoritative current configuration.

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
          "label": "Oat"
        }
      ]
    }
  ]
}
```

This is the stable initial schema. Modifier selections do not carry price
adjustments. Price-changing choices are represented by WooCommerce variations.

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

Phase 08 uses WooCommerce customers as member identities and does not create an
independent customer or loyalty table. Name, billing phone, and optional email
are written through WooCommerce customer CRUD.

Reserved WooCommerce customer meta used only for safe phone-only creation and
idempotent retries:

```text
_coffeepos_member_create_operation_id   client creation operation identifier
_coffeepos_member_create_fingerprint    normalized creation request hash
_coffeepos_placeholder_email            yes when WooCommerce-required email is internal
```

When email is omitted, the WooCommerce gateway creates a non-routable internal
`example.invalid` address because the WooCommerce customer API requires email.
That placeholder is never exposed as member email or copied to billing email.

Do not create an independent loyalty database or duplicate WooCommerce customer
records. Buy-five-get-one progress, points, tiers, and tier coupons have no
storage in Phase 08.

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

Phase 09 makes this association mandatory for new POS checkout orders. An
authenticated cashier may have at most one `open` shift; service-level MySQL
locking serializes open/close mutations for that cashier.

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

# 17. Modifier and Quick Notes Storage

Modifier and quick-note definitions are configuration rather than transaction
data.

Initial decision:

- store definitions in plugin settings/options
- use `coffeepos_modifier_groups` for modifier-group definitions
- use `coffeepos_quick_notes` for quick-note definitions
- give modifier groups, modifier options, and quick notes stable IDs
- store labels, selection rules, enabled state, sort order, and optional
  product/category applicability
- modifiers and quick notes do not carry price adjustments
- store selected stable IDs and captured display labels on order item metadata

The default `coffeepos_quick_notes` value established by Phase 12 is:

| ID | Label |
|---|---|
| `less_sugar` | Ít đường |
| `extra_sugar` | Nhiều đường |
| `less_milk` | Ít sữa |
| `less_ice` | Ít đá |

Each entry also stores `enabled`, `sort_order`, and optional product/category
applicability. Configuration changes do not rewrite historical order metadata.

Do not create a custom table until there is a requirement for a full admin CRUD system with larger relational data.

---

# 18. POS Settings

Plugin configuration should use the WordPress Options API or an appropriate WooCommerce settings mechanism.

Phase-04 service-table selection uses the `coffeepos_service_tables` option.
Each entry contains a stable positive integer `id`, display `label`, `enabled`
state, and `sort_order`. This is selection configuration only; it is not table
occupancy, reservation, or floor-plan persistence.

The frontend `/pos/settings/` textarea serializes each non-empty line into one
enabled entry. Unchanged labels preserve their existing IDs, new labels receive
IDs above the current configured maximum, and line order is stored in increments
of 10.
The textarea is presentation only; the option remains the canonical structured
array consumed by `SettingsTableProvider`.

Examples:

```text
POS page
Customer display page
KDS polling interval
Order queue polling interval
Enable/disable customer display
Modifier definitions
Quick note definitions
Receipt settings
VietQR settings
POS capabilities/configuration
```

Do not put operational transaction data in options.

Phase 12 adds the boolean option
`coffeepos_receipt_print_order_note`, default `false`. It controls only receipt
projection/rendering and does not change storage or staff-screen visibility of
the order note.

The frontend Appearance card owns these CoffeePOS-only options:

| Option | Type | Default | Contract |
|---|---|---|---|
| `coffeepos_brand_color` | six-digit hex string | `#12715b` | Overrides the POS primary and derived dark-primary CSS variables |
| `coffeepos_nav_default_collapsed` | boolean | `true` | Determines the server-rendered initial staff-navigation state |
| `coffeepos_interface_density` | enum | `normal` | `normal` or `compact` |
| `coffeepos_show_product_images` | boolean | `true` | Controls Cashier product visuals without changing catalog data |
| `coffeepos_custom_css` | string | empty | Maximum 20 KB, loaded only on POS routes after owned styles |

Custom CSS rejects HTML delimiters, all `url()` references, `@import`,
`expression`, `behavior`, `-moz-binding`, and JavaScript URL syntax. It is
presentation configuration only and must never contain business or permission
rules.

The reduced frontend Settings model also owns these options (all through the
WordPress Options API; none alter WordPress or WooCommerce store settings):

| Group | Options | Contract/defaults |
|---|---|---|
| General | `coffeepos_store_name`, `coffeepos_branch_name`, `coffeepos_logo_id`, `coffeepos_store_address`, `coffeepos_store_phone` | POS-owned identity; name defaults to `CoffeePOS`; logo is an attachment ID |
| General | `coffeepos_timezone`, `coffeepos_date_format`, `coffeepos_time_format` | Defaults `Asia/Ho_Chi_Minh`, `d/m/Y`, `H:i`; controls POS operational dates and receipt presentation |
| Sales | `coffeepos_default_order_type`, `coffeepos_require_dine_in_table`, `coffeepos_require_open_shift` | Defaults `takeaway`, `true`, `true`; enforced again by server services |
| Payments | `coffeepos_cash_enabled`, `coffeepos_bank_transfer_enabled`, `coffeepos_vietqr_reference_prefix` | Both methods default enabled; at least one remains enabled; prefix defaults `POS` |
| Payments | `coffeepos_vietqr_bank_id`, `coffeepos_vietqr_account_number`, `coffeepos_vietqr_account_name`, `coffeepos_vietqr_template` | Existing VietQR configuration; template is `qronly`, `compact`, or `compact2` |
| Receipt | `coffeepos_receipt_paper_width`, `coffeepos_receipt_auto_print`, `coffeepos_receipt_footer` | Defaults `80`, `false`, `Thank you!`; width is `58` or `80` |
| Membership | `coffeepos_membership_enabled`, `coffeepos_member_create_enabled`, `coffeepos_member_required_fields` | Defaults enabled/enabled and phone+name; phone is always required |
| Operations | `coffeepos_kds_poll_interval_ms`, `coffeepos_order_queue_poll_interval_ms`, `coffeepos_kds_sound_enabled` | Polling remains bounded to 3000–60000 ms; sound defaults enabled |
| Advanced | `coffeepos_pos_base_slug`, `coffeepos_uninstall_delete_data` | Base defaults `pos`; uninstall deletion defaults false; internal page IDs are not importable/exported settings |

Settings export is a versioned JSON projection of importable option values.
Import accepts only the documented allowlist and is capability/nonce protected.
Diagnostics is read-only runtime information and is not persisted.

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

Phase 12 explicitly continues to use WordPress users, roles, auth cookies, and
capabilities. It creates no CoffeePOS staff, PIN, password, or login-session
table. CoffeePOS role/capability registration is configuration managed through
WordPress APIs, not a second identity store.

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

Codex MUST NOT invent persistent storage without this information.

---

# 27. Phase-05 Checkout and VietQR Storage

Phase-05 reserves these WooCommerce order metadata keys. All writes use
WooCommerce order CRUD methods and remain HPOS-compatible:

```text
_coffeepos_operation_id
_coffeepos_operation_fingerprint
_coffeepos_pos_session_id
_coffeepos_cash_received
_coffeepos_cash_change
_coffeepos_next_pos_session_id
```

The operation ID is scoped by cashier and POS session. The fingerprint is a
one-way hash of the session, cart revision, payment method, and normalized
payment input. Checkout queries WooCommerce orders by operation ID before
creation and serializes the lookup/create section with a short database
advisory lock. Cash received/change are fixed decimal strings validated or
calculated by the server. The source/next session IDs support response-loss
recovery and are not authorization credentials.

VietQR beneficiary display configuration uses Options API keys:

```text
coffeepos_vietqr_bank_id
coffeepos_vietqr_account_number
coffeepos_vietqr_account_name
coffeepos_vietqr_template
```

These options contain no verification credentials. Incomplete configuration
keeps a bank-transfer order pending with no QR image. No provider secret,
payment ledger, or custom Phase-05 table is introduced.

---

# 28. Phase-06 Customer Display Persistence

Customer Display state and sync events are not persisted. Payment success stays
visible until Cashier sends `display.reset`; no thank-you timeout option or
client timer owns this transition.
