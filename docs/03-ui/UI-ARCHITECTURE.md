# UI-ARCHITECTURE.md

# CoffeePOS UI Architecture

## 1. Purpose

This document defines the shared UI architecture for CoffeePOS.

The major UI surfaces are:

```text
Cashier
Customer Display
KDS
Order Queue
Order History
Shifts
Reports
Admin
```

Each screen is an independent application surface.

Shared UI components must have stable contracts.

---

# 2. UI Principles

## 2.1 PHP-owned templates

PHP templates are the authoritative HTML structure. PHP renders the static
screen shell and emits native `<template>` blueprints for dynamic components.

AJAX/REST responses contain JSON projections. The shared `TemplateRenderer`
clones the relevant blueprint and safely binds that JSON into targeted DOM
regions.

The frontend MUST NOT rebuild screens or components from JavaScript strings.

## 2.2 Vanilla JavaScript

Use modular Vanilla JS.

JavaScript owns:

- event handling
- transient state
- client-side interaction
- API calls
- DOM updates
- synchronization

JavaScript does NOT own:

- authoritative price
- authoritative payment success
- stock truth
- authorization
- persistent business rules

## 2.3 Touch-first

The POS should be usable with:

- touchscreen
- mouse
- keyboard

Controls must have sufficient hit area and clear visual states.

## 2.4 Stable selectors

Each interactive component should expose stable behavior hooks.

Preferred patterns:

```html
data-action="checkout"
data-product-id="123"
data-component="cart-item"
```

Do not make JS depend on fragile CSS structure.

---

# 3. Screen Layout Architecture

Every application screen should conceptually contain:

```text
Screen
├── Screen Header
├── Screen Toolbar / Filters
├── Main Content
├── Overlay Layer
│   ├── Modal
│   ├── Drawer
│   └── Confirm Dialog
└── Notification Layer
```

Not every screen needs every region.

---

# 4. UI State Categories

Common UI states:

```text
idle
loading
success
empty
error
disabled
selected
active
pending
```

Every data-driven component should define how it behaves in at least:

- loading
- empty
- error
- normal

---

# 5. Shared Components

Core components:

```text
Button
Icon Button
Input
Search Input
Select
Tabs
Badge
Product Card
Cart Item
Quantity Control
Modal
Drawer
Confirm Dialog
Toast
Money Display
Loading Indicator
Empty State
Error State
Pagination
Date Filter
Status Badge
```

Specialized POS components:

```text
Category Navigation
Catalog Category Section
Product Card
Product Search Results
Product Configuration Modal
Customer Lookup
Customer Summary
Member Creation
Order Type Selector
Table Selector
Coupon Selector
Cart Summary
Cash Payment Panel
Bank Transfer Payment Panel
QR Display
Order Card
KDS Card
Shift Summary
```

Detailed contracts belong in `COMPONENTS.md`.

Phase-08 Customer Lookup automatically starts after 400 ms of stable valid phone
input, ignores stale requests, and keeps lookup separate from authoritative cart
attachment. A not-found state exposes the PHP-owned create-member form.

---

# 6. Shared Data Attributes

Recommended conventions:

```text
data-component
data-action
data-id
data-product-id
data-variation-id
data-cart-item-key
data-order-id
data-state
data-field
data-attr
data-key
```

Do not overload a single attribute with multiple meanings.

---

# 7. Event Strategy

Prefer event delegation for dynamic lists.

Example conceptual flow:

```text
Catalog Category Section
    ↓ click
data-action="select-product"
    ↓
Cashier Controller
    ↓
Product Modal
```

Interactive elements should emit semantic actions rather than relying on internal DOM traversal.

---

# 8. Modal Architecture

Modal should support:

```text
open
close
confirm
cancel
loading
error
```

A modal is responsible for presentation and interaction.

Business operations remain in application/API layers.

---

# 9. Cashier Screen Structure

```text
Cashier Screen
├── Header
│   ├── Brand
│   ├── Shift Status
│   ├── Cashier
│   └── Utility Actions
│
├── Main
│   ├── Menu Panel
│   │   ├── Search
│   │   ├── Sticky Category Navigation
│   │   └── Catalog Scroll Region
│   │       └── Category Section[]
│   │           └── Product Card[]
│   │
│   └── Cart Panel
│       ├── Order Type
│       ├── Customer
│       ├── Cart Items
│       ├── Coupon
│       ├── Subtotal
│       ├── Discount
│       ├── Total
│       └── Checkout
│
└── Overlay Layer
    ├── Product Modal
    ├── Customer Lookup
    ├── Coupon Selector
    ├── Table Selector
    ├── Hold Cart
    ├── Held Carts
    ├── Checkout
    └── Confirmation
```

---

# 10. Customer Display Structure

```text
Customer Display
├── Menu Region
│   ├── Header / Brand
│   ├── Category Jump Navigation
│   └── Category Section Grid
│       └── Read-only Product Row[]
└── Realtime Cart Region
    ├── Customer Area
    ├── Cart Projection
    ├── Total
    ├── Payment Projection
    └── Thank You State
```

The Customer Area renders `Guest` or `Member`. Member presentation may include
display name and server-provided masked phone such as `0353***250`; it never
renders full phone, email, or customer ID.

On landscape displays, the menu occupies approximately 68–72% and the cart
occupies approximately 28–32%. The menu remains visible while cart/payment state
changes. Customer Display should prioritize readability from several meters
away.

Cashier and Customer Display catalog details are defined in `CATALOG-UI.md`.

---

# 11. KDS Structure

```text
KDS
├── Header
│   ├── Screen Name
│   ├── Sound Toggle
│   └── Refresh Status
│
├── Status Filters
│
└── Order Grid
    └── KDS Card[]
```

---

# 12. Navigation

Major screen routes are independent surfaces.

Baseline routes:

```text
/pos/cashier
/pos/customer
/pos/kds
/pos/order-queue
/pos/order-history
/pos/shifts
/pos/reports
```

Routing implementation must follow architecture/API documentation.

---

# 13. Responsive Strategy

Cashier should support:

- desktop POS
- tablet landscape
- tablet portrait where practical

Customer Display should support:

- large landscape display

KDS should support:

- large screen
- tablet

The layout should degrade gracefully.

---

# 14. Notifications

Use a consistent toast/notification system.

Categories:

```text
success
info
warning
error
```

Notifications should be actionable where needed.

Do not use browser `alert()` for normal application UX.

---

# 15. Loading and Error UX

Never silently wait.

Examples:

```text
Loading products
Searching
Loading customer
Applying coupon
Creating order
Processing payment
Refreshing KDS
```

Each should expose appropriate pending state.

Buttons performing a destructive or non-idempotent operation should prevent accidental double submission.

---

# 16. Accessibility

Basic requirements:

- keyboard reachable controls
- visible focus
- labels for inputs
- accessible modal behavior
- semantic buttons
- sufficient contrast
- status announcements where appropriate

Touch optimization must not remove keyboard accessibility.

---

# 17. CSS Ownership

Components own their visual styles.

Screen-level layout owns:

- grid
- columns
- regions
- screen-specific spacing

Do not let individual components rely on unrelated screen selectors.

---

# 18. Template Ownership

Recommended:

```text
templates/
├── cashier/
├── customer/
├── kds/
├── order-queue/
├── order-history/
├── shifts/
├── reports/
└── components/
```

A reusable structure should live in a reusable component template where practical.

For an AJAX-driven component, its PHP template emits one native `<template>`
blueprint. Do not maintain parallel PHP-fragment and JavaScript-string versions
of the same markup.

---

# 19. Client Template Renderer

CoffeePOS uses one project-owned renderer at the conceptual path:

```text
assets/js/ui/template-renderer.js
```

Minimum responsibilities:

```text
render(templateId, data) -> DocumentFragment
renderList(templateId, items, target)
```

Binding contract:

| Marker | Purpose | Rule |
|---|---|---|
| `data-field="name"` | Text binding | Assign through `textContent` |
| `data-attr="data-product-id:id"` | Attribute/property binding | Only documented targets are allowed |
| `data-key="id"` | Stable list identity | Resolve from the JSON item before insertion |

`data-attr` may contain semicolon-separated mappings. Boolean DOM properties
such as `disabled`, `hidden`, `checked`, and `selected` must use boolean
semantics; false removes or disables the corresponding state.

Binding paths may read only own properties from the supplied projection.
Prototype keys such as `__proto__`, `prototype`, and `constructor` are invalid.

The default attribute allowlist is `data-*`, `aria-*`, `title`, `value`, and
`alt`, plus the documented boolean properties. URL-bearing attributes such as
`src` or `href` require an explicitly registered URL validator. Event handler
attributes (`on*`), `style`, `srcdoc`, and arbitrary attribute names are
forbidden.

The owning controller supplies a fixed template ID and target element. Neither
the template ID, target selector, nor binding declarations may come from AJAX
JSON.

Missing values render as empty text or remove the optional attribute. The
renderer must reject an unknown template, malformed binding, unsafe attribute,
or non-object data item with a predictable error.

The renderer does not support raw HTML fields, arbitrary expressions, function
execution, business rules, or API calls. Display-ready values such as formatted
money and translated labels come from PHP/static template text or the server
projection.

Nested lists are rendered explicitly by the owning component controller using
another named template and target region. Do not add hidden loop or conditional
syntax to the renderer.

---

# 20. DOM Update Strategy

Prefer targeted updates:

```text
Cart Item changed
→ update Cart Item
→ update totals
```

rather than:

```text
Every click
→ replace entire screen HTML
```

Full region replacement may be used where it is simpler and safe, but must preserve documented selectors and event behavior.

For JSON-driven regions, full or keyed replacement must still use the shared
`TemplateRenderer`; it must not interpolate JSON into `innerHTML`.

---

# 21. UI Contract Rule

When a component or selector becomes part of a completed phase, it becomes a project contract.

Changing it requires:

1. impact analysis
2. documentation update
3. consumer update
4. verification
## Phase 09 Shift Management

`/pos/shifts/` owns the open, active/reconciliation, and history states. The
Cashier header consumes only the current ShiftView status and links to that
screen. Shift totals are server projections and are never calculated by UI.
## Phase 10 Order History

`/pos/order-history/` owns filter/list, detail, refund, confirm, and print
surfaces. Repeated rows remain PHP-template-owned. Allowed actions come from
the server; successful Quick Reorder navigates to Cashier with the returned
server-side cart session.

## Phase 11 Reports

`/pos/reports/` is a manager-only, independently scrollable application surface.
It owns date presets/custom range, KPI cards, payment composition, product
rankings, peak hours, data-quality warnings, and CSV/XLSX export states. Server
ReportView values are rendered through PHP-owned native templates and the shared
TemplateRenderer; the browser does not recalculate financial totals.
