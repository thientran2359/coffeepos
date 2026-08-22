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

## 2.1 Server-rendered HTML

PHP templates are the authoritative HTML structure.

The frontend MUST NOT rebuild the whole screen from JavaScript strings.

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
Product Grid
Product Configuration Modal
Customer Lookup
Customer Summary
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
```

Do not overload a single attribute with multiple meanings.

---

# 7. Event Strategy

Prefer event delegation for dynamic lists.

Example conceptual flow:

```text
Product Grid
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
│   │   ├── Categories
│   │   └── Product Grid
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
├── Header / Brand
├── Welcome Area
├── Customer Area
├── Cart Projection
├── Total
├── Payment Projection
└── Thank You State
```

Customer Display should prioritize readability from several meters away.

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

---

# 19. DOM Update Strategy

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

---

# 20. UI Contract Rule

When a component or selector becomes part of a completed phase, it becomes a project contract.

Changing it requires:

1. impact analysis
2. documentation update
3. consumer update
4. verification

