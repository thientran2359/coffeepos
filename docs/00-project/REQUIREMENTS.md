# REQUIREMENTS.md

# CoffeePOS Requirements

## 1. Purpose

This document converts the feature baseline into system-level requirements.

Detailed implementation behavior belongs in architecture, domain, UI, API, database, and phase documents.

---

# 2. Cashier / POS Requirements

## 2.1 Category and Search

The cashier MUST provide:

- category-section navigation
- an "All" action that returns to the start of the catalog
- product search by name
- live search suggestions that locate a product in the catalog
- product stock/availability indication

All POS-visible products MUST remain grouped in category sections. Selecting a
category scrolls to its section; selecting a search result scrolls to and
highlights the matching product. Neither action replaces the full catalog.

## 2.2 Product Types

The cashier MUST support:

- simple products
- variable products
- variation attributes such as size and other product attributes

A variable product MUST allow the cashier to configure required variation choices before adding it to the cart.

## 2.3 Product Configuration

A product configuration flow SHOULD support:

- variation selection
- configurable options/modifiers where available
- quantity
- quick notes
- free-form notes

The feature baseline explicitly requires quick notes. Phase 12 establishes the
initial administrator-configurable set as `Ít đường`, `Nhiều đường`, `Ít sữa`,
and `Ít đá`. Quick notes use stable IDs, may be combined, and remain distinct
from the item's free-form note.

Modifier definitions and selections MUST use stable IDs. Modifiers do not change
price; price-changing choices MUST be represented by WooCommerce variations.

## 2.4 Cart

The cart MUST support:

- add item
- increase quantity
- decrease quantity
- remove item
- clear cart with confirmation
- subtotal
- discount
- total

The active cart MUST be stored server-side in the WooCommerce session. Product
and variation prices MUST come from WooCommerce; browser-supplied prices are
never authoritative.

## 2.5 Order Type

The cashier MUST support:

- dine-in
- takeaway

Dine-in MUST allow table selection.

## 2.6 Customer

The cashier MUST support:

- guest customer
- member customer
- phone-based customer lookup
- customer identification before checkout
- automatic exact lookup after a valid phone is entered
- creating a WooCommerce-backed member when the phone does not exist
- filling allowed member data from an existing exact phone match
- returning an attached member cart to guest mode without clearing other cart
  context

The feature baseline describes automatic customer recognition through phone lookup, including customer name, history, and points where available.

WooCommerce remains canonical for customer identity. CoffeePOS MUST NOT create
an independent customer table solely for membership identity.

The initial membership workflow does not award points, free items, tiers, or
automatic member discounts. Buy-five-get-one and tier-specific coupons are
future capabilities whose earning, refund, redemption, and WooCommerce coupon
contracts must be defined before implementation.

---

# 3. Coupon Requirements

The cashier MUST support:

- listing applicable active coupons
- manually entering a coupon
- applying a coupon
- removing a coupon

Coupon validation must be performed server-side through the appropriate WooCommerce mechanisms.

---

# 4. Suspended Cart Requirements

The cashier MUST support:

- holding the current cart
- identifying the held cart
- storing time information
- listing held carts
- resuming a held cart
- deleting a held cart

The suspended cart mechanism must not interfere with WooCommerce order creation until checkout.

---

# 5. Stock Requirements

The POS MUST show stock availability.

Quick stock adjustment MUST support:

- increase
- decrease
- adjustment reason

All stock-changing operations MUST be validated server-side.

---

# 6. Checkout Requirements

## 6.1 Cash

Cash checkout MUST support:

- entered amount
- quick denomination buttons
- exact amount
- change calculation
- validation that received amount is sufficient

## 6.2 Bank Transfer

Bank transfer checkout MUST support:

- dynamic payment amount
- VietQR generation/display
- payment-pending state
- payment-success state

The exact bank/payment integration contract must be defined before implementation.

## 6.3 WooCommerce Order

Checkout MUST create a standard WooCommerce order containing the required:

- products
- variations
- quantities
- item configuration
- notes
- table/order type
- customer information
- payment information

The cart MUST also support one order-level note. An order note is separate from
all item notes, is private to staff by default, and is persisted on the final
WooCommerce order.

## 6.4 Receipt

The system MUST support:

- receipt printing after successful order
- receipt reprinting
- reprinting from order history
- reprinting from order queue

Browser printing MUST use one authoritative receipt projection and one
PHP-owned receipt template. Printing MUST be blocked until the projection is
loaded and populated; an empty receipt MUST never be sent to the browser print
dialog. The receipt includes trusted order, item, total, payment, service, and
cashier data. Showing the private order note is an administrator setting and is
disabled by default. Physical printer transport remains a later hardware
integration concern.

---

# 7. Customer Display Requirements

The customer display MUST:

- display a read-only product menu grouped by WooCommerce category
- display product names and WooCommerce-derived prices
- keep the menu visible beside the realtime cart on landscape displays
- synchronize with cashier state in real time
- display selected items
- display quantities
- display prices
- display totals
- display customer/member information where applicable
- distinguish guest from member
- display only a privacy-safe masked member phone, for example `0353***250`
- never render the member's full phone number or email
- display payment amount
- display VietQR during bank-transfer payment
- display thank-you state after successful payment

The baseline specification requires `BroadcastChannel` for cashier/customer-display synchronization.

Cashier and Customer Display MUST be scoped to the same `pos_session_id`, render
the same server-confirmed cart revision, recover an initial/full snapshot when
opened late, and ignore stale or foreign-session messages.

Both screens MUST consume one shared catalog projection while using independent
screen-specific PHP templates.

---

# 8. KDS Requirements

The KDS MUST:

- detect new orders
- refresh active orders
- provide audible new-order notification
- allow sound to be enabled/disabled
- distinguish preparation states
- show item details
- show variations
- show important notes
- display elapsed preparation time
- warn after 5 minutes
- show critical state after 10 minutes
- provide one-click preparation status changes

The baseline specifies periodic checking every 5 seconds.

---

# 9. Order Queue Requirements

The order queue MUST display:

- order number
- customer
- table/takeaway
- order time
- total

It MUST support:

- complete
- cancel
- reprint receipt

The queue must refresh periodically.

---

# 10. Order History Requirements

Order history MUST support:

- date range filtering
- status filtering
- order detail
- receipt reprinting
- quick reorder

Quick reorder MUST be able to reconstruct the products from a previous order into the cashier cart.

---

# 11. Shift Requirements

## Open Shift

The system MUST record:

- start time
- staff
- opening cash
- opening note

## Active Shift

The system MUST track:

- cash sales
- bank-transfer sales
- total sales
- expected cash

## Close Shift

The system MUST record:

- actual cash
- closing note

It MUST calculate:

- expected cash
- actual cash
- variance

## Shift History

The system MUST provide historical closed shifts.

---

# 12. Reporting Requirements

Reports MUST support:

- today
- yesterday
- last 7 days
- current month
- custom date range

Core KPIs:

- total revenue
- total orders
- AOV
- products sold

Additional reports:

- cash vs bank-transfer revenue
- top products by revenue
- top products by quantity
- peak hours

Export:

- CSV
- Excel

---

# 13. Technical Requirements

The plugin MUST provide:

- full-screen POS UI
- touch-friendly interaction
- dedicated POS routes
- WordPress-account authentication and capability checks
- nonce verification
- secure server-side validation

CoffeePOS MUST NOT implement PIN authentication or store a separate staff
password. Staff authenticate with WordPress accounts through the WordPress
authentication API, cookie, and installed authentication hooks. CoffeePOS roles
are convenience bundles only; granular capabilities remain authoritative.

Baseline routes are:

- `/pos/` (staff login or first authorized landing page)
- `/pos/cashier`
- `/pos/kds`
- `/pos/order-queue`
- `/pos/order-history`
- `/pos/reports`
- `/pos/shifts`
- `/pos/customer`

Exact routing implementation is an architecture concern.

Every staff screen MUST expose a shared, capability-aware navigation component.
The Customer Display is a paired public projection and MUST NOT expose staff
navigation. Hiding a link is never authorization; protected routes and actions
MUST enforce their capability server-side.

---

# 14. Cross-Cutting Requirements

## Security

All privileged operations MUST verify authorization.

## Data Integrity

Business-critical values MUST be calculated/validated server-side.

## WooCommerce Compatibility

The plugin MUST use supported WooCommerce APIs where practical.

## Maintainability

Feature code MUST be organized by responsibility and domain.

Shared data/projection contracts MUST remain independent from screen-specific
markup. Adding or changing one screen must not require duplicating catalog,
pricing, cart, or synchronization business rules.

## UI Consistency

Shared UI components MUST use documented selectors and behavior.

## Backward Stability

A phase MUST NOT break completed functionality from previous phases.

---

# 15. Requirements Not Yet Fully Defined

The feature baseline does not fully specify:

- exact custom database tables
- exact REST/AJAX endpoints
- exact payment-provider integration
- receipt-printer implementation
- exact membership/points implementation
- exact plugin directory architecture

These MUST be resolved in the corresponding architecture/database/API/phase documents before implementation.
