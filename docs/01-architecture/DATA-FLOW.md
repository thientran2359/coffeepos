# DATA-FLOW.md

# CoffeePOS Data Flow

## 1. Purpose

This document defines how data moves through CoffeePOS.

The objective is to make ownership and responsibility explicit across:

- Cashier
- Customer Display
- WooCommerce
- Payment
- KDS
- Order Queue
- Shifts
- Reports

---

# 2. High-Level Flow

```text
WooCommerce Catalog
       ↓
Product/Category API
       ↓
Cashier UI
       ↓
Cart
       ↓
Customer / Order Type / Coupon
       ↓
Checkout Application
       ↓
Payment
       ↓
WooCommerce Order
       ├──→ Customer Display
       ├──→ KDS
       ├──→ Order Queue
       ├──→ Order History
       ├──→ Shift
       └──→ Reports
```

---

# 3. Catalog Loading

```text
Cashier / Customer Display
  ↓
Catalog API
  ↓
WooCommerce
  ↓
CatalogView grouped by category
  ↓
TemplateRenderer
  ├──→ Cashier category sections + product cards
  └──→ Customer Display category menu + product rows
```

Both screens use the same projection and separate PHP-owned templates. Category
navigation and Cashier search operate against the loaded CatalogView; they do
not refetch or replace the full catalog.

Catalog display data may be cached where safe.

Stock and price must be revalidated server-side before checkout.

---

# 4. Product Selection

```text
User clicks product
        ↓
Cashier UI
        ↓
Load product configuration
        ↓
Select variation/options
        ↓
Configure quantity
        ↓
Add quick/custom note
        ↓
Cart Service
        ↓
Cart State
```

The server explicitly creates the active cart, then loads it from the
WooCommerce session using an opaque `pos_session_id`. An unknown or expired ID
fails rather than silently creating a replacement. Product/variation IDs and
configuration are accepted as input, but the server resolves WooCommerce price
and returns the canonical cart projection.

---

# 5. Cart Update

```text
UI Action
   ↓
Cart API + expected_revision
   ↓
CartService
   ↓
WooCommerce session cart
   ↓
Updated Cart Projection + revision
   ├──→ Cashier Cart UI
   └──→ Customer Display Sync
```

Cashier stores only the latest projection. After a successful mutation it
broadcasts that same server-confirmed projection to Customer Display. Neither
screen reconstructs prices or totals independently.

---

# 6. Customer Lookup

```text
Cashier
   ↓
Phone Lookup
   ↓
Customer Application Service
   ↓
WooCommerce Customer
   ↓
Customer Projection
   ↓
Cashier
   ↓
Customer Display
```

Membership/points information may come from a separate integration if required.

---

# 7. Coupon Flow

```text
Cashier
   ↓
Coupon Request
   ↓
Server
   ↓
WooCommerce Coupon Validation
   ↓
Updated Cart/Checkout Data
   ↓
Cashier UI
```

Client-side coupon assumptions are not authoritative.

---

# 8. Checkout Flow

```text
Cashier
   ↓
Checkout Request
   ↓
Authorization
   ↓
Load WooCommerce session cart
   ↓
Verify pos_session_id + expected_revision
   ↓
Cart Validation
   ↓
Product/Variation Validation
   ↓
Stock Validation
   ↓
Coupon Validation
   ↓
Customer Validation
   ↓
Order Preparation
   ↓
WooCommerce Order
```

The order should contain all required POS context.

---

# 9. Cash Payment Flow

```text
Cart Total
   ↓
Cash Payment UI
   ↓
Cash Received
   ↓
Server Validation
   ↓
Change Due
   ↓
Payment Completion
   ↓
WooCommerce Order
```

The browser may display change immediately, but the server must validate the amount when completing the operation.

---

# 10. Bank Transfer Flow

```text
Checkout
   ↓
Payment Method = Bank Transfer
   ↓
Create/prepare order/payment context
   ↓
Generate dynamic VietQR
   ↓
Customer Display
   ↓
Payment Pending
   ↓
Payment Verification
   ↓
Payment Success
   ↓
Order Completion
```

The feature source requires dynamic VietQR display for the order amount.

The exact bank/payment verification mechanism is not fully specified and must be finalized before payment implementation.

---

# 11. Customer Display Flow

```text
Cashier
  ↓
Server-confirmed State Change
  ↓
BroadcastChannel(coffeepos:<pos_session_id>)
  ↓
Customer Display
  ↓
Render Projection
```

On startup or reconnect, Customer Display requests a full snapshot. Cashier
responds with its latest server-confirmed projection. Every state message
contains the matching `pos_session_id` and monotonic `revision`; stale or
foreign-session messages are ignored.

Examples of synchronization events:

```text
cart.updated
customer.updated
payment.started
payment.updated
sale.completed
display.reset
```

Broadcast messages are transport messages, not database state.

---

# 12. Order → KDS

```text
Order Created/Confirmed
      ↓
KDS Detection
      ↓
KDS Order Projection
      ↓
NEW
      ↓
PREPARING
      ↓
READY
      ↓
COMPLETED
```

The baseline feature specifies periodic detection of new orders.

---

# 13. Order → Queue

```text
WooCommerce Orders
      ↓
Order Queue Query
      ↓
Active Order Projection
      ↓
Order Queue UI
```

The queue should query the canonical order system rather than maintain a duplicate active-order database unless performance requirements later justify a documented projection.

---

# 14. Order → History

```text
WooCommerce Orders
      ↓
Filtered Query
      ↓
Order History UI
```

Quick reorder:

```text
Historical Order
      ↓
Order Items
      ↓
Cart Reconstruction
      ↓
Current Cart
```

The reconstructed cart must be revalidated before checkout.

---

# 15. Order → Shift

For each eligible sale:

```text
Completed Payment
      ↓
Shift Transaction Projection
      ↓
Active Shift Totals
```

Expected cash:

```text
Opening Cash
+ Cash Sales
- Cash Refunds
= Expected Cash
```

Close:

```text
Expected Cash
+
Actual Cash
↓
Variance
```

---

# 16. Order → Reports

Reports should query canonical transactional data.

Conceptually:

```text
Orders
Order Items
Payments
Shifts
      ↓
Reporting Query Layer
      ↓
Aggregations
      ↓
KPI / Charts / Tables / Export
```

Reports MUST NOT become an independent transaction store.

---

# 17. Responsibility Matrix

| Operation | UI | Application | Domain | WooCommerce/Infrastructure |
|---|---|---|---|---|
| Display product | Yes | Data preparation | No | Yes |
| Add cart item | Request | Yes | Yes | Validate product |
| Calculate trusted totals | Display only | Yes | Yes | WooCommerce pricing |
| Validate coupon | No | Yes | No | Yes |
| Create order | No | Yes | Context | Yes |
| Confirm payment | No | Yes | Payment state | Integration/WooCommerce |
| Open shift | Request | Yes | Yes | Persistence |
| Close shift | Request | Yes | Yes | Persistence |
| KDS status change | Request | Yes | Yes | Order/infrastructure |
| Report query | Display | Yes | No | Query source |

---

# 18. Failure Handling

When a downstream operation fails:

```text
UI
 ↓
Application
 ↓
Validation/Integration Failure
 ↓
Stable Error Response
 ↓
UI Error State
```

Do not partially report success.

Examples:

- Failed order creation must not show "Payment successful" merely because the UI received a click.
- Failed stock validation must not leave a confirmed order without a documented recovery path.
- Failed refund must not appear as refunded.

---

# 19. Idempotency / Repeated Requests

Operations that can be accidentally repeated should be designed to avoid duplicate side effects.

High-risk operations include:

- checkout
- payment completion
- order creation
- refund
- shift close

The exact idempotency strategy belongs to the API implementation.

---

# 20. Data Freshness

Use local UI state for responsiveness.

Refresh authoritative data when necessary:

```text
Before checkout
Before payment completion
Before stock-sensitive operation
Before refund
Before shift close
```

Do not assume a product state loaded five minutes ago is still authoritative for checkout.

Checkout loads the cart from the WooCommerce session and revalidates its
WooCommerce prices, stock, coupon, and customer context. It does not accept a
browser-owned cart as the order source.

WooCommerce repricing uses an isolated scratch `WC_Cart`. The integration must
complete normal storefront-cart session loading before constructing that cart,
then populate it only from the addressed CoffeePOS cart. A shopper cart in the
same WooCommerce session must not affect POS totals and must not be changed by
POS repricing.

---

# 21. Source-of-Truth Summary

```text
Catalog → WooCommerce
Customer → WooCommerce / approved membership integration
Coupon → WooCommerce
Order → WooCommerce
Payment result → Server/payment integration
Shift → CoffeePOS
KDS projection → CoffeePOS + WooCommerce order
Customer Display → synchronized projection
Order Queue → query/projection
Order History → WooCommerce
Reports → query/projection
```

## Phase-08 Member Identity Flow

```text
Cashier phone input
→ shared server phone normalization
→ exact WooCommerce customer lookup
→ existing CustomerView OR controlled not-found
→ WooCommerce customer creation under hashed phone lock when requested
→ explicit revisioned cart attachment
→ Cashier CartView
→ centralized Customer Display sanitizer
→ mode + name + masked phone BroadcastChannel projection
```

Customer creation and cart attachment are separate operations. If attachment
conflicts after creation, the customer remains valid and the Cashier reconciles
the cart before retrying attachment.
