# COMPONENTS.md

# CoffeePOS Component Contracts

## 1. Purpose

This document defines reusable UI components and behavior hooks.

Components should be reused rather than independently recreated on each screen.

## Dynamic rendering contract

Repeated components are defined by native `<template>` elements emitted from
PHP and populated from AJAX/REST JSON by the shared `TemplateRenderer`.

| Component | Template ID | Target | Stable key |
|---|---|---|---|
| Category button | `coffeepos-category-button-template` | `data-component="category-list"` | `id` |
| Catalog category | `coffeepos-catalog-category-template` | `data-component="catalog-section-list"` | `id` |
| Product card | `coffeepos-product-card-template` | `data-component="catalog-category-products"` | `occurrence_key` |
| Search result | `coffeepos-product-search-result-template` | `data-component="product-search-results"` | `occurrence_key` |
| Customer category | `coffeepos-customer-category-template` | `data-component="customer-catalog-sections"` | `id` |
| Customer product row | `coffeepos-customer-product-row-template` | `data-component="customer-category-products"` | `occurrence_key` |
| Cart item | `coffeepos-cart-item-template` | `data-component="cart-item-list"` | `item_id` |
| Variation group | `coffeepos-variation-group-template` | `data-component="variation-selector"` | `name` |
| Variation option | `coffeepos-variation-option-template` | `data-component="variation-option-list"` | `value` |

Component controllers may manage state and nested rendering, but they must not
duplicate these structures in JavaScript strings. The JSON fields consumed by a
template are part of that component's projection contract.

---

# 2. Product Card

## Purpose

Represent one sellable WooCommerce product in a Cashier category section.

## Required display

Potential data:

```text
image
name
price
stock state
variation indicator
```

## Actions

```text
select product
```

## Hooks

Conceptual:

```html
<article
    data-component="product-card"
    data-product-id="123"
>
```

## States

```text
normal
hover
selected
out_of_stock
loading
```

Clicking an out-of-stock product must not add it directly to the cart.

---

# 3. Category Navigation

## Purpose

Navigate to product category sections in the loaded catalog.

Baseline categories include:

```text
All
Drinks
Food
Coffee
...
```

The implementation must load actual WooCommerce categories rather than hard-code all categories.

Clicking a category scrolls the catalog region to its section. Manual scrolling
updates active state through scroll-spy. Navigation must not filter, hide,
refetch, or rebuild the catalog.

## State

```text
active
inactive
loading
empty
```

---

# 4. Search Input

## Purpose

Live product search.

Behavior:

- search the loaded CatalogView index
- show matching suggestions
- support empty results without clearing the catalog
- scroll to and highlight the selected product occurrence
- support keyboard navigation

Search suggestions use product/category occurrence identity. Search does not
send a catalog request for every keystroke and never adds a product directly.

---

# 5. Product Configuration Modal

## Purpose

Configure a product before adding/updating a cart item.

## Sections

```text
Header
Product information
Variation attributes
Modifier groups
Quantity
Quick notes
Custom note
Footer
```

## Modes

```text
add
edit
```

## Actions

```text
cancel
add_to_cart
update_cart_item
```

## Validation

Required variation selections must be complete.

---

# 6. Variation Selector

## Purpose

Choose WooCommerce variation attributes.

The UI must be generated from actual product attributes.

Do not assume a fixed attribute such as `size`.

## State

```text
unselected
selected
disabled
unavailable
```

---

# 7. Modifier Group

## Purpose

Represent an optional/configurable group.

Example:

```text
Milk
  Regular
  Oat
  Soy
```

The exact modifier source and rules are defined by the database/domain documentation.

Modifier options do not display or apply a CoffeePOS price adjustment. A
price-changing choice belongs in the Variation Selector and uses WooCommerce
variation pricing.

---

# 8. Quantity Control

Required controls:

```text
decrease
quantity display
increase
```

Optional direct input may be added later.

Rules:

- quantity must remain positive for a cart item
- prevent accidental rapid double updates where necessary

---

# 9. Quick Notes

Phase-12 default administrator-configurable chips:

```text
Ít đường
Nhiều đường
Ít sữa
Ít đá
```

Quick notes must have stable IDs.

Labels may be localized.

Multiple enabled/applicable chips may be selected. A selected chip has visible
and accessible pressed state. The selected stable IDs are structured item
configuration; the UI MUST NOT concatenate chip labels into the free-text
textarea. Edit mode restores both selections and free text independently.

---

# 10. Custom Note

A textarea for free-form item notes.

Rules:

- sanitize server-side
- display safely in cart/KDS/receipt where appropriate
- keep it attached to the order item

---

# 11. Cart Item

## Display

```text
product name
variation summary
modifier summary
note
unit price
quantity
line total
```

## Actions

```text
increase
decrease
edit
remove
```

---

# 12. Cart Summary

Display:

```text
subtotal
discount
total
```

Optional:

```text
coupon
tax
fees
```

The exact total breakdown depends on WooCommerce configuration.

The component renders the latest server-confirmed WooCommerce session cart
projection. The browser does not calculate authoritative totals.

---

# 13. Order Type Selector

Values:

```text
Dine-in
Takeaway
```

Dine-in opens/selects table context.

---

# 14. Customer Lookup

## Input

Phone number.

## States

```text
idle
typing
searching
found
not_found
creating
attaching
attached
conflict
error
```

## Results

Customer summary may include:

```text
name
phone
membership status
points if supported
```

After a valid phone is entered, lookup is automatically debounced. A found
member fills the preview but is attached only through the authoritative cart
mutation. A not-found result may open the Phase-08 create-member form.

Customer Display uses a separate safe summary:

```text
Guest

or

Member
name
masked phone, for example 0353***250
```

Customer Display must not render or receive the full phone or email.

---

# 15. Table Selector

## Purpose

Select table for dine-in orders.

The initial scope requires selection of a service table but does not define a full table-management application.

---

# 16. Coupon Selector

Supports:

```text
select available coupon
enter coupon code
apply
remove
```

Coupon validation is server-side.

---

# 17. Hold Cart Dialog

Allows:

```text
label/name
confirm hold
cancel
```

The current cart becomes suspended only after successful server persistence.

---

# 18. Held Cart List

Displays:

```text
label
created time
cashier/user
```

Actions:

```text
resume
delete
```

---

# 19. Checkout Modal

Conceptual flow:

```text
Order Summary
→ Payment Method
→ Payment Details
→ Confirmation
```

Supported baseline methods:

```text
cash
bank transfer
```

---

# 20. Cash Payment Panel

Displays:

```text
total
amount received
change
```

Quick values:

```text
+10k
+20k
+50k
+100k
+200k
+500k
Exact
```

The actual change calculation must match server validation.

---

# 21. Bank Transfer Payment Panel

Displays:

```text
amount
VietQR
payment status
```

The QR must represent the current order/payment amount.

---

# 22. Payment Success Dialog

Should show:

```text
order number
total
payment method
change if applicable
```

Actions may include:

```text
print receipt
new order
```

---

# 23. KDS Card

Phase-07 behavior hooks use `data-component="kds-order-card"` and
`data-action="transition-kds-order"`. Exactly one primary action is shown for
the current active state: Start, Ready, or Complete. Pending disables only the
affected card. Timer severity is derived presentation and is never submitted as
business state.

Displays:

```text
order number
elapsed time
items
quantities
variations
important notes
status
```

Quick actions:

```text
start
ready
complete
```

Visual elapsed-time severity:

```text
normal
warning
critical
```

---

# 24. Order Card

On Order Queue, server-projected allowed actions control presentation: Complete
only for `ready`, Cancel only for `new|preparing`, and Reprint when a receipt is
available. Cancel uses the shared accessible confirmation dialog. UI visibility
is not authorization; every action is revalidated by the server.

Shared operational card for order queue/history where appropriate.

Display:

```text
order number
customer
table/order type
time
total
status
```

Available actions depend on screen.

---

# 25. Shift Summary

Displays:

```text
opening cash
cash sales
bank sales
total sales
expected cash
```

At close:

```text
actual cash
variance
```

---

# 26. Modal Contract

Every modal should define:

```text
id
trigger
open behavior
close behavior
confirm behavior
loading state
error state
focus behavior
```

Do not make individual modals invent incompatible lifecycle behavior.

---

# 27. Empty State

Every list/data region should define an empty state.

Example:

```text
No products found.
No held carts.
No active orders.
No order history.
No shifts.
```

---

# 28. Error State

Error states should include:

```text
message
retry action where possible
```

Do not expose stack traces or raw server exceptions.

## Shared Operations Header

Order Queue defines the visual contract for staff management screens. Shifts,
Order History, Reports, and Settings reuse `coffeepos-operations`,
`coffeepos-operations__header`, `coffeepos-operations__tools`, and the shared
button variants. The contract includes the dark green header, gold lower border,
light eyebrow text, white title, right-aligned tools, pale operations background,
and consistent normal/primary/danger buttons. Screen-specific data and actions
remain owned by each screen.

## Phase 10 Order History Components

The History filter bar owns date/status/type/search input. Order cards and
detail items use native PHP templates. Order Detail exposes only server-allowed
print, reorder, refund, and cancel actions. Refund and destructive confirmation
dialogs retain input and expose recoverable error states.

## Phase 11 Report Components

The Report filter bar owns presets, custom inclusive dates, and product ranking
limit. Currency sections contain KPI cards, payment rows, product rows, and all
24 hourly buckets. Multi-currency and unallocated-refund warnings remain visible.
Export controls expose pending and recoverable error states and never bypass the
manager-only server permission check.

## Phase 12 Staff Navigation

The shared Staff Navigation component contains:

```text
CoffeePOS/home entry
capability-permitted screen links
current-screen state
staff display name
optional current-shift status
WordPress logout action
```

It is present on every staff application screen as a persistent left sidebar
and absent from Login and Customer Display. Desktop layouts show the icon and
label for each permitted destination. The server-rendered default is the compact
icon rail so the first paint never exposes labels before JavaScript initializes.
Staff may expand the sidebar for the current page, but every new page load starts
compact. This presentation state is not authorization or application state. The
rail keeps an accessible label
and title for every destination. Navigation links may scroll vertically while
staff identity and logout remain available at the bottom. The toggle exposes
its expanded state and the responsive form must preserve logical focus order,
visible focus, and access to logout.

## Phase 12 Order Note

The Order Note component uses a compact cart trigger/summary plus a PHP-owned
dialog containing the cart-level textarea and save/clear state. It is not inside
an item modal and never changes any item's note. It binds to the revisioned
`CartView.order_note`, supports a maximum of 2000 characters, safely retains
text after a recoverable error, and exposes saving/saved/error feedback. The
dialog uses the shared Escape, focus-trap, backdrop-close, and focus-restoration
lifecycle.

## Phase 12 Receipt

The shared Receipt component renders store identity, order/time/cashier,
customer-safe identity, service context, items and configuration, item notes,
subtotal, discount/refund/total, payment method, and cash received/change when
present. The private order note appears only when enabled by the receipt
setting. Loading, unavailable, and error states block printing. The print action
is enabled only after the complete ReceiptView has been bound.
