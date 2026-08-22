# PHASE-02.md

# CoffeePOS Phase 02 — POS Shell & Cashier UI Foundation

## 1. Objective

Build the first complete Cashier/POS user interface shell on top of the completed Phase 00 infrastructure and Phase 01 domain/application core.

This phase establishes the stable frontend structure that later phases will use for:

```text
Product
Variation
Modifier
Cart
Customer
Order Type
Coupon
Checkout
```

The primary objective is:

```text
PHP Templates
      ↓
Stable POS DOM
      ↓
Vanilla JS Screen Controller
      ↓
Phase-01 Application/Core Contracts
```

At the end of this phase:

- the Cashier screen has a complete full-screen POS layout
- the layout is touch-friendly
- all major regions exist
- shared UI components are implemented according to `COMPONENTS.md`
- PHP templates own the HTML structure
- JavaScript controls UI behavior
- selectors/data attributes are stable and documented
- screen-level UI state is separated from business/domain state
- loading/empty/error/disabled states exist
- the shell can render with mock/static foundation data
- Phase 03 can attach real Product/Variation/Cart behavior without redesigning the screen

This phase MUST NOT implement complete product/cart business logic.

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

docs/05-phases/PHASE-00.md
docs/05-phases/PHASE-01.md
```

Junie MUST inspect the actual Phase 00 and Phase 01 implementation before modifying UI code.

Do not assume class names or template APIs from the documents if the existing implementation already established a different but compatible convention.

---

# 3. Scope

## In Scope

```text
Cashier screen shell
Full-screen POS layout
Header
POS navigation foundation
Search UI
Category navigation UI
Product grid shell
Product card shell
Cart panel shell
Cart item shell
Order type selector shell
Customer section shell
Coupon section shell
Cart summary shell
Checkout button shell
Shared UI primitives
Modal foundation
Drawer foundation where needed
Toast/notification foundation
Loading states
Empty states
Error states
Disabled states
Responsive/touch layout
Keyboard-accessible controls
Cashier screen JavaScript controller
Screen UI state
Stable DOM selectors
Data attributes
PHP component templates
Cashier-specific CSS/SCSS
```

## Out of Scope

Do NOT implement:

```text
Real product querying
Live product search
WooCommerce product loading
Real category filtering
Variation selection logic
Modifier selection logic
Quick-note business logic
Cart mutation
Cart persistence
Cart total calculation
Coupon validation
Customer lookup
Membership lookup
Table lookup
Checkout
Payment
WooCommerce order creation
Receipt printing
Customer Display synchronization
KDS
Order Queue
Order History
Shift logic
Reports
Quick stock adjustment workflow
Suspended cart persistence
```

A visually interactive shell may simulate state transitions for UI verification, but must not implement the real business operations listed above.

---

# 4. Core Principle

Phase 02 is a PRESENTATION phase.

The implementation must establish:

```text
PHP
→ HTML structure

JavaScript
→ UI behavior / presentation state

Phase-01 Application Core
→ business/domain behavior
```

Do NOT move business rules into JavaScript just because the UI needs to display something.

Do NOT create a second Cart/Product domain model in JavaScript.

---

# 5. Screen Layout

The Cashier screen MUST contain these primary regions:

```text
CASHIER
│
├── HEADER
│   ├── Brand
│   ├── Current Shift
│   ├── Cashier
│   └── Utility Actions
│
├── MAIN
│   │
│   ├── MENU PANEL
│   │   ├── Search
│   │   ├── Categories
│   │   └── Product Grid
│   │
│   └── CART PANEL
│       ├── Order Type
│       ├── Customer
│       ├── Cart Items
│       ├── Coupon
│       ├── Subtotal
│       ├── Discount
│       ├── Total
│       └── Checkout
│
└── OVERLAY LAYER
    ├── Modal foundation
    ├── Drawer foundation
    ├── Confirm dialog foundation
    └── Toast/notification layer
```

The final CSS layout should prioritize a large, persistent cart area and a highly usable product-selection area.

---

# 6. Header

## Required Elements

```text
Brand
Current shift status
Current cashier
```

The header may expose placeholders for future utility actions:

```text
Held carts
Customer display
Shift
Settings
```

Do not implement the underlying future features.

---

# 7. Header State

Possible states:

```text
no_shift_context
shift_open
loading
error
```

Phase 02 may display static/mock shift context.

It must not implement Shift business logic.

---

# 8. Menu Panel

The menu panel contains:

```text
Search
Category Navigation
Product Grid
```

It should remain usable independently of the cart panel.

---

# 9. Search UI

The search area must provide:

```text
search input
clear action where appropriate
loading state
empty state
```

The search UI must be ready for Phase 03 to attach the real product search API.

## Current Phase Behavior

Phase 02 does NOT perform real product search.

The UI may:

- update local search text
- display a mock result state
- display a placeholder empty state

Do not query WooCommerce merely to make the shell look functional.

---

# 10. Search Contract

Suggested selector:

```html
<input
    data-component="product-search"
    data-action="search-products"
>
```

JavaScript should own:

```text
input value
focus
clear
loading presentation
```

Phase 03 will own:

```text
debounced API query
product results
search/filter behavior
```

---

# 11. Category Navigation

Category navigation must provide:

```text
All
Category buttons
Active category state
```

The actual categories must NOT be hard-coded as permanent business data.

Phase 02 may use placeholder/mock category labels only for visual verification.

Phase 03 will connect the component to WooCommerce category data.

---

# 12. Category UI States

```text
loading
normal
active
disabled
empty
error
```

The active category must be visually obvious.

---

# 13. Product Grid

The product grid is a presentation shell.

It must support:

```text
normal
loading
empty
error
```

It must be able to render multiple Product Card components.

Phase 02 does NOT load real products.

---

# 14. Product Card

Product Card shell must support:

```text
image
name
price
stock state
variation indicator
```

It must expose a stable product identifier hook for Phase 03.

Recommended conceptual structure:

```html
<article
    data-component="product-card"
    data-product-id=""
>
```

Do not create product business objects in the browser.

---

# 15. Product Card States

```text
normal
hover
pressed
selected
out_of_stock
disabled
loading
```

The out-of-stock state must be visually distinct.

Phase 02 only represents the state visually.

It does not perform stock validation.

---

# 16. Cart Panel

The cart panel is always visible in the primary Cashier layout.

It contains:

```text
Order Type
Customer
Cart Items
Coupon
Cart Summary
Checkout
```

---

# 17. Cart Header

Display:

```text
Cart title
item count
clear button
```

The clear button should exist visually, but Phase 02 does not implement real cart clearing.

If clicked in this phase, it may demonstrate a confirmation UI only.

---

# 18. Cart Item Shell

Cart Item should visually support:

```text
product name
variation summary
modifier summary
note
quantity
unit price
line total
```

Actions:

```text
increase
decrease
edit
remove
```

Phase 02 does not perform these domain operations.

Buttons should be visually and semantically ready for Phase 03.

---

# 19. Empty Cart

When there are no cart items, display an intentional empty state.

Example:

```text
Your cart is empty
Select a product to start an order.
```

Do not use a blank container.

---

# 20. Order Type Selector

Display:

```text
Dine-in
Takeaway
```

The UI should provide a clear selected/unselected state.

Phase 02 does not connect the selector to Phase-01 OrderType yet.

---

# 21. Table Placeholder

When Dine-in is visually selected, the shell may show:

```text
Select table
```

The actual table lookup/selection belongs to a later phase.

Do not implement table data fetching here.

---

# 22. Customer Section

Display:

```text
Guest
Member / Customer
Find customer
```

The UI should provide a clear empty/selected state.

Phase 02 does not perform real customer lookup.

---

# 23. Customer Empty State

Example:

```text
Guest customer
```

Optional secondary action:

```text
Find customer
```

The action should only open foundation UI or demonstrate intended interaction.

---

# 24. Coupon Section

Display:

```text
Coupon
Add coupon
Applied coupon placeholder
Remove action placeholder
```

Phase 02 does not validate coupons.

---

# 25. Cart Summary

Display:

```text
Subtotal
Discount
Total
```

Money values may be placeholder data.

The shell must not calculate authoritative totals in JavaScript.

---

# 26. Checkout Button

The checkout button must be visually prominent.

States:

```text
disabled
enabled
loading
```

In Phase 02:

- it may be disabled by default
- clicking it must NOT create an order
- it must NOT initiate payment

The actual checkout workflow begins in a later phase.

---

# 27. Overlay Layer

Provide a shared overlay root for:

```text
Modal
Drawer
Confirm Dialog
Toast
```

Suggested structure:

```html
<div data-component="overlay-root"></div>
```

Do not scatter overlay DOM across unrelated screen regions.

---

# 28. Modal Foundation

Implement reusable modal behavior:

```text
open
close
cancel
confirm
loading
error
```

The modal must support:

- focus management
- Escape close where appropriate
- click-outside behavior according to configuration
- keyboard accessibility

Phase 02 does not implement Product Modal business logic.

---

# 29. Confirm Dialog Foundation

Provide a reusable confirmation component.

Example future use:

```text
Clear cart
Delete suspended cart
Cancel order
Refund order
```

Phase 02 only establishes the component.

---

# 30. Toast / Notification

Implement consistent notification presentation:

```text
success
info
warning
error
```

Do not use browser `alert()` for normal application UI.

---

# 31. Loading Component

Create a reusable loading representation.

It must support at least:

```text
inline
component
panel
screen
```

Loading must not make the entire screen unusable unless the operation genuinely requires it.

---

# 32. Error Component

Reusable error state:

```text
message
retry action where applicable
```

Do not expose raw server exceptions.

---

# 33. Empty Component

Reusable empty-state component:

```text
icon/visual optional
title
description
optional action
```

---

# 34. JavaScript Architecture

Phase 02 MUST NOT create a new monolithic `pos.js`.

Use modular responsibility.

Suggested:

```text
assets/js/
├── core/
│   ├── app.js
│   └── events.js
│
├── ui/
│   ├── modal.js
│   ├── toast.js
│   ├── loading.js
│   └── empty-state.js
│
├── components/
│   ├── product-card.js
│   ├── category-nav.js
│   ├── cart-panel.js
│   ├── cart-item.js
│   ├── order-type.js
│   └── customer-summary.js
│
└── screens/
    └── cashier.js
```

The exact split may differ if the existing Phase 00 structure warrants it.

Do not split modules merely to create more files.

---

# 35. Cashier Screen Controller

Create one screen-level controller responsible for:

```text
screen initialization
component initialization
UI event wiring
screen UI state
```

It must NOT become a business/service layer.

It should coordinate presentation components.

---

# 36. UI State

Phase 02 may use a lightweight UI state object.

Potential properties:

```text
activeCategory
searchTerm
isSearching
productGridState
cartPanelState
orderTypeDisplayState
customerDisplayState
modalState
toastState
```

Do not duplicate Phase-01 domain state here.

---

# 37. Event Handling

Prefer semantic `data-action` hooks.

Examples:

```text
search-products
clear-search
select-category
select-product
open-customer
select-order-type
open-coupon
clear-cart
increase-quantity
decrease-quantity
edit-cart-item
remove-cart-item
checkout
```

Phase 02 may bind these actions without implementing their future business operation.

Handlers for unsupported functionality should be explicit rather than silently failing.

---

# 38. Selector Contract

The following selectors/data attributes should be stable after Phase 02:

```text
data-screen="cashier"
data-component="product-search"
data-component="category-nav"
data-component="product-grid"
data-component="product-card"
data-component="cart-panel"
data-component="cart-item"
data-component="order-type"
data-component="customer-summary"
data-component="coupon"
data-component="cart-summary"
data-component="checkout"
data-component="overlay-root"
```

Use IDs only where a globally unique DOM target is genuinely required.

Avoid coupling JavaScript to styling classes.

---

# 39. PHP Template Structure

Recommended:

```text
templates/
├── cashier/
│   ├── index.php
│   ├── header.php
│   ├── menu-panel.php
│   ├── cart-panel.php
│   └── overlay-root.php
│
└── components/
    ├── button.php
    ├── product-card.php
    ├── cart-item.php
    ├── modal.php
    ├── toast.php
    ├── loading.php
    └── empty-state.php
```

Only create component templates that are actually used.

Do not create empty placeholder templates merely to fill the directory.

---

# 40. PHP Template Responsibilities

Templates may:

```text
render HTML
escape output
render prepared data
expose selectors
render default UI state
```

Templates MUST NOT:

```text
query WooCommerce directly
call application services directly for mutation
perform database writes
perform checkout
calculate authoritative totals
```

---

# 41. Cashier Template Composition

The preferred structure is:

```text
cashier/index.php
    ↓
header.php
menu-panel.php
    ├── search
    ├── category-nav
    └── product-grid
cart-panel.php
    ├── order-type
    ├── customer
    ├── cart-items
    ├── coupon
    ├── summary
    └── checkout
overlay-root.php
```

Keep the page template readable.

Do not place hundreds of lines of markup into `cashier/index.php`.

---

# 42. Data Passed to Templates

Phase 02 may pass foundation data:

```text
screen configuration
placeholder categories
placeholder product cards
empty cart
placeholder cashier/shift display
```

Do not query WooCommerce from the template.

---

# 43. CSS / SCSS Architecture

The UI should use component-oriented styling.

Conceptual:

```text
assets/css/
├── base/
├── layout/
├── components/
└── screens/
    └── cashier/
```

If the existing build system uses SCSS source files, follow that project convention.

The final generated asset path must remain compatible with AssetLoader.

---

# 44. Touch UX

Primary POS controls should be designed for touch.

Requirements:

```text
large tap targets
clear pressed states
clear active states
minimal accidental overlap
persistent cart visibility
```

Avoid hover-only interactions for critical operations.

---

# 45. Responsive Layout

The Cashier shell must support:

```text
desktop POS
tablet landscape
tablet portrait where practical
```

The primary desktop/tablet layout should keep the cart visible without requiring navigation to another screen.

---

# 46. Accessibility

Required:

- semantic buttons
- labels for form controls
- keyboard navigation
- visible focus
- modal focus management
- Escape handling where appropriate
- accessible status messaging where appropriate

Do not use clickable `<div>` elements for primary actions.

---

# 47. Screen Initialization

Cashier initialization flow:

```text
POS route
   ↓
PHP renders cashier shell
   ↓
assets loaded
   ↓
cashier controller initializes
   ↓
components bind
   ↓
initial UI state rendered
```

Do not make an initial API call for real products in Phase 02.

---

# 48. Mock/Foundation Data

Mock data may be used solely to verify layout/component behavior.

Mock data MUST be:

- clearly separated
- easy to remove
- not persisted
- not treated as real business data

Do not insert demo products into WooCommerce.

---

# 49. Interaction Matrix

| Component | Phase 02 behavior | Phase 03+ |
|---|---|---|
| Search | input/clear UI | real product search |
| Category | select visual state | real filtering |
| Product Card | select visual state | product configuration |
| Cart Item | display shell | real cart mutation |
| Order Type | visual selection | domain state |
| Customer | open foundation UI | real lookup |
| Coupon | visual shell | WooCommerce validation |
| Checkout | disabled/foundation | real checkout |
| Modal | working foundation | product/customer/etc. |
| Toast | working | application notifications |

---

# 50. Acceptance Criteria

Phase 02 is complete when:

## Screen

1. `/pos/cashier` renders a complete POS shell.
2. Header is visible.
3. Menu panel is visible.
4. Cart panel is visible.
5. Overlay root is available.

## Menu

6. Search input exists.
7. Search loading/empty states exist.
8. Category navigation exists.
9. Active category state works.
10. Product grid exists.
11. Product card shell renders.
12. Out-of-stock visual state is supported.

## Cart

13. Cart panel is visible.
14. Empty cart state is polished.
15. Cart item shell is available.
16. Quantity controls exist visually.
17. Edit/remove actions exist as UI hooks.
18. Order type selector exists.
19. Customer summary exists.
20. Coupon section exists.
21. Subtotal/discount/total exist.
22. Checkout control exists.

## Shared UI

23. Modal foundation works.
24. Confirm dialog foundation works.
25. Toast works.
26. Loading component works.
27. Empty state works.
28. Error state works.

## JavaScript

29. Cashier screen has a dedicated controller.
30. No monolithic business-logic `pos.js` is introduced.
31. UI state is separated from domain state.
32. Stable `data-component`/`data-action` hooks are used.
33. No product/cart business logic is implemented in JS.

## Templates

34. HTML structure is defined in PHP templates.
35. Templates do not query the database.
36. Templates do not perform business mutations.
37. Components are reusable where appropriate.

## UX

38. Interface is touch-friendly.
39. Keyboard navigation works for primary controls.
40. Responsive layout works for the supported POS sizes.

---

# 51. Required Test Cases

## Route

```text
TC-01 Cashier route renders.
TC-02 Existing unrelated WordPress route remains unaffected.
```

## Layout

```text
TC-03 Header renders.
TC-04 Menu panel renders.
TC-05 Cart panel renders.
TC-06 Overlay root renders.
```

## Components

```text
TC-07 Search input works locally.
TC-08 Category active state changes.
TC-09 Product card selected state works.
TC-10 Modal opens/closes.
TC-11 Confirm dialog opens/cancels/confirms.
TC-12 Toast displays.
TC-13 Loading state displays.
TC-14 Empty state displays.
TC-15 Error state displays.
```

## Responsive

```text
TC-16 Desktop layout.
TC-17 Tablet landscape.
TC-18 Tablet portrait where supported.
```

## Accessibility

```text
TC-19 Keyboard focus.
TC-20 Modal Escape behavior.
TC-21 Semantic buttons/labels.
```

## Scope

```text
TC-22 No WooCommerce order created.
TC-23 No cart persistence created.
TC-24 No payment triggered.
TC-25 No Customer Display/KDS logic triggered.
```

---

# 52. Performance Rules

Phase 02 should avoid:

```text
large inline HTML strings in JavaScript
repeated full-screen DOM replacement
unnecessary polling
unnecessary WooCommerce requests
```

Do not add product polling/search APIs yet.

---

# 53. Security Rules

Even though Phase 02 is mostly UI:

- do not embed secrets in frontend code
- do not expose unnecessary server settings
- do not treat UI capability checks as authorization
- escape PHP template output
- use nonce/API foundations when future actions require them

---

# 54. Database Rules

No database schema changes are allowed in Phase 02.

Do not:

```text
create tables
create cart persistence
create product caches
create customer caches
create order records
```

---

# 55. API Rules

Do not add the full POS API in Phase 02.

Only consume existing infrastructure or use local foundation data.

The real API integration begins when the corresponding feature phase requires it.

---

# 56. Files Expected

A coherent implementation may create files similar to:

```text
templates/cashier/
├── index.php
├── header.php
├── menu-panel.php
├── cart-panel.php
└── overlay-root.php

templates/components/
├── product-card.php
├── cart-item.php
├── modal.php
├── confirm-dialog.php
├── toast.php
├── loading.php
└── empty-state.php

assets/js/
├── ui/
├── components/
└── screens/cashier.js

assets/css/
└── screen/component styles
```

The final list should reflect actual responsibilities.

Do not create unused files.

---

# 57. Existing Phase-00 Compatibility

Junie MUST reuse:

```text
TemplateLoader
AssetLoader
POS Router
REST foundation
Settings foundation where configuration is needed
```

Do not replace these systems.

If a compatibility issue exists, make the smallest possible change and report it.

---

# 58. Phase-01 Compatibility

Cashier UI must consume Phase-01 contracts rather than duplicate them.

Examples:

```text
OrderType
CartView
ProductView
VariationView
CustomerView
```

Phase 02 may use mock instances/data to prove rendering.

Do not recreate these concepts in JS with incompatible shapes.

---

# 59. Forbidden Changes

During Phase 02, Junie MUST NOT:

- implement ProductService logic again in JS
- implement CartService logic again in JS
- query WooCommerce directly from templates
- create a second cart model
- create a second product model
- implement checkout
- create WooCommerce orders
- implement payments
- implement Customer Display
- implement KDS
- implement Order Queue
- implement Shift
- implement Reports
- introduce React/Vue/another frontend framework
- put large application HTML into JS template strings
- replace the Phase-00 template loader
- replace the Phase-00 asset loader
- perform unrelated refactoring

---

# 60. Definition of Done

Phase 02 is complete only when:

```text
Cashier route
    ↓
complete POS shell
    ↓
stable PHP DOM
    ↓
stable component hooks
    ↓
Vanilla JS screen controller
    ↓
shared UI components
    ↓
responsive/touch-ready layout
    ↓
no business feature leakage
```

The implementation must be ready for Phase 03 to connect:

```text
Product API
Variation configuration
CartService
CartValidationService
```

without redesigning the Cashier screen.

---

# 61. Completion Report

Junie MUST report:

## Changed

All created/modified files.

## Templates

List Cashier templates/components.

## JavaScript

List screen/component modules.

## CSS

List styles created.

## Selectors

List stable `data-component` and `data-action` contracts introduced.

## UI States

List loading/empty/error/disabled states.

## Verification

Report:

```text
PHP syntax
JavaScript syntax/build
Cashier route
component rendering
responsive checks
accessibility checks
scope checks
```

Use:

```text
PASS
FAIL
BLOCKED
```

Do not claim browser verification if it was not actually performed.

## Scope

Explicitly confirm:

```text
no product API
no cart business logic
no checkout
no order creation
no payment
no customer lookup
no KDS
no Customer Display
no Shift
no Reports
```

## Issues

List any remaining UI/architecture problems.

---

# 62. Final Phase 02 Rule

When Phase 02 is complete:

STOP.

Do not automatically start Phase 03.

The next implementation must be explicitly started using:

```text
PHASE-03.md
```
