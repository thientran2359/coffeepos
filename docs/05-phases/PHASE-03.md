# PHASE-03.md

# CoffeePOS Phase 03 — Product, Variation & Cart

## 1. Objective

Connect the Phase-02 Cashier UI with the Phase-01 Application/Domain Core and implement the first complete cashier ordering workflow.

This is the first phase where the cashier can perform a real product-selection and cart-building flow.

Primary workflow:

```text
Product Grid
    ↓
Select Product
    ↓
Product Configuration Modal
    ↓
Variation
    ↓
Modifiers / Options
    ↓
Quantity
    ↓
Quick Notes
    ↓
Custom Note
    ↓
Add to Cart
    ↓
Cart Item
    ↓
Edit / Quantity / Remove
    ↓
Cart Summary
```

At the end of this phase:

- real WooCommerce products can be shown in the Cashier
- categories can filter products
- live product search works
- simple products can be added
- variable products can be configured through a modal
- variations are resolved using the full WooCommerce attribute set
- modifiers/options can be selected according to the available configuration
- quick notes can be selected
- custom item notes can be entered
- quantity can be selected/changed
- configured products can be added to the cart
- existing cart items can be edited
- cart item quantities can be changed
- cart items can be removed
- cart can be cleared with confirmation
- cart totals are displayed from the application/cart projection
- Cashier and Customer Display are NOT yet fully synchronized
- checkout/payment/order creation are NOT implemented

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
docs/04-api/ORDER-API.md
docs/04-api/SYNC-API.md

docs/05-phases/PHASE-00.md
docs/05-phases/PHASE-01.md
docs/05-phases/PHASE-02.md
```

Junie MUST inspect the actual implementation of:

```text
Phase-00 infrastructure
Phase-01 domain/application core
Phase-02 Cashier UI
```

before changing any existing code.

Do not recreate contracts that already exist.

---

# 3. Scope

## In Scope

```text
Real WooCommerce product loading
Real category loading
Live product search
Product availability display
Product Card real data
Simple product selection
Variable product selection
Product Configuration Modal
Variation attribute selection
Variation resolution
Modifier selection foundation
Quick note selection
Custom item note
Quantity selection
Add item to Cart
Edit Cart Item
Increase quantity
Decrease quantity
Remove item
Clear Cart
Cart projection
Cart totals display
Cart loading/error/empty states
Product loading/error/empty states
Variation loading/error states
Cart synchronization within Cashier screen
```

## Out of Scope

Do NOT implement:

```text
Customer lookup
Membership
Dine-in/table selection
Coupon application
Checkout
Cash payment
Bank transfer
VietQR
WooCommerce order creation
Receipt printing
Customer Display synchronization
KDS
Order Queue
Order History
Quick Reorder
Shift logic
Reports
Refunds
Quick stock adjustment
Suspended cart persistence
```

Order type UI may remain in its Phase-02 shell state, but no business integration is required.

---

# 4. Core Architecture

The flow must be:

```text
Cashier UI
    ↓
API / Application boundary
    ↓
Phase-01 Application Services
    ↓
Domain
    ↓
WooCommerce Integration
```

The browser MUST NOT directly access WooCommerce objects.

The browser MUST NOT reimplement Phase-01 business rules.

---

# 5. Product Data Flow

Product list:

```text
Cashier
 ↓
Product API
 ↓
ProductService
 ↓
ProductGatewayInterface
 ↓
WooCommerce
 ↓
ProductView[]
 ↓
Cashier Product Grid
```

Product detail:

```text
Product Card
 ↓
Product detail request
 ↓
ProductService / VariationService
 ↓
ProductView / VariationView / configuration
 ↓
Product Modal
```

---

# 6. Product API

Use the API contract already established in `POS-API.md`.

Relevant endpoints:

```text
GET /coffeepos/v1/products
GET /coffeepos/v1/products/{id}
GET /coffeepos/v1/categories
```

Do not invent a second product API contract.

If the Phase-01 Application Services are not yet exposed through REST, create only the minimum REST controller/endpoint required for Phase 03.

REST controllers MUST remain thin.

---

# 7. Product List

The product grid must load real WooCommerce products.

Each product should expose the data needed by the Product Card:

```text
id
name
type
price
image
stock_status
stock_quantity where allowed
has_variations
```

Use `ProductView`.

Do not expose raw WooCommerce product objects.

---

# 8. Category Loading

Load product categories through the documented category endpoint/service.

Category UI should support:

```text
All
WooCommerce categories
```

Flow:

```text
Category Click
 ↓
Update active category
 ↓
Product query
 ↓
Replace product grid
```

The active category must remain visually selected.

---

# 9. Product Search

Search must be live.

Recommended flow:

```text
User types
 ↓
Debounce
 ↓
Product API request
 ↓
Loading state
 ↓
Product results
```

Do not request on every keypress without debounce.

Recommended initial debounce range:

```text
250–350ms
```

The exact value may be chosen by implementation.

---

# 10. Search + Category Interaction

The implementation must define a deterministic rule when both are active.

Recommended:

```text
search term
+
selected category
→
server/product query
```

Clearing search should return to the current selected category rather than blindly resetting the entire menu.

If the API contract cannot support combined filtering, document the limitation rather than inventing client-side filtering of an incomplete product set.

---

# 11. Product Loading States

The grid must support:

```text
initial loading
search loading
category loading
empty result
error
normal
```

Avoid visually destroying the entire grid for a small refresh when targeted updates are possible.

---

# 12. Stock Display

Product Card must show stock availability using the product projection.

At minimum:

```text
in stock
out of stock
```

Possible:

```text
low stock
```

only if the application projection provides reliable information.

Client-side stock is informational.

---

# 13. Product Selection Decision

When a product is clicked:

```text
Product selected
      ↓
Is product available?
      ├── No → show unavailable/error state
      │
      └── Yes
          ↓
       Is variable?
          ├── No
          │    ↓
          │  determine configuration
          │    ↓
          │  add to cart
          │
          └── Yes
               ↓
          open Product Modal
```

A simple product may still require the Product Modal when it has configured modifiers/options.

---

# 14. Product Configuration Modal

The Product Modal must support:

```text
mode = add
mode = edit
```

Input for `add`:

```text
product
```

Input for `edit`:

```text
product
existing cart item
```

Structure:

```text
┌─────────────────────────────────────┐
│ Product Name                     X  │
│ Base / Current Price                 │
├─────────────────────────────────────┤
│ VARIATIONS                           │
│                                     │
│ Size                                │
│ [ S ] [ M ] [ L ]                   │
│                                     │
│ Temperature                         │
│ [ Hot ] [ Cold ]                    │
├─────────────────────────────────────┤
│ OPTIONS / MODIFIERS                  │
│                                     │
│ Milk                                │
│ [ Regular ] [ Oat ] [ Soy ]         │
├─────────────────────────────────────┤
│ QUICK NOTES                         │
│ [ Ít đá ] [ Không đá ]              │
│ [ Ít ngọt ] [ Không đường ]         │
├─────────────────────────────────────┤
│ NOTE                                │
│ [_______________________________]   │
├─────────────────────────────────────┤
│ QUANTITY        [ - ] 1 [ + ]       │
├─────────────────────────────────────┤
│             Cancel   Add / Update   │
└─────────────────────────────────────┘
```

The exact visual styling is not mandated.

The semantic sections are.

---

# 15. Variation Attributes

Variation selection must be dynamically generated from WooCommerce data.

Do NOT hard-code:

```text
Size
Temperature
Milk
```

as domain attributes.

Example data:

```text
attributes:
  pa_size:
    options: [s, m, l]

  pa_temperature:
    options: [hot, cold]

  pa_milk:
    options: [regular, oat, soy]
```

The UI must render what the product actually provides.

---

# 16. Variation Selection State

Each attribute must support:

```text
unselected
selected
disabled
unavailable
```

A selected attribute should have a clear visual state.

---

# 17. Variation Resolution

When selected attributes change:

```text
Selected Attribute Set
       ↓
VariationService
       ↓
Resolve matching variation
       ↓
VariationView
```

The matching logic MUST consider the full attribute set.

Example:

```text
size = m
temperature = cold
milk = oat
```

must resolve against all three attributes.

NEVER use:

```php
Object.values($attributes)[0]
```

or equivalent first-attribute-only logic.

---

# 18. Invalid Variation

Possible states:

```text
no matching variation
variation unavailable
variation out of stock
```

In these cases:

```text
Add / Update button
→ disabled
```

And display an actionable message.

---

# 19. Variation Price

When the selected variation changes:

```text
variation selected
 ↓
resolved VariationView
 ↓
update displayed price
 ↓
update availability
```

The displayed price should come from the VariationView/application response.

Do not manually calculate variation price in JavaScript.

---

# 20. Simple Product Flow

For a purchasable simple product with no required configuration:

```text
Product Card
 ↓
Select
 ↓
Create Cart Item
 ↓
CartService
 ↓
CartView
 ↓
Update Cart UI
```

Do not create a fake variation.

---

# 21. Modifier Groups

If product configuration exposes modifiers:

```text
Modifier Group
    ↓
Modifier Options
```

Example:

```text
Milk
├── Regular
├── Oat +10k
└── Soy +10k
```

The UI must follow the actual group rules provided by the application.

Possible rules:

```text
required
optional
single-select
multi-select
minimum
maximum
```

Do not hard-code those rules into the component.

---

# 22. Modifier Selection

Selection changes should update:

```text
selected modifier state
display price projection where available
validation state
```

The final authoritative value must come from the application/domain layer.

The browser must not invent modifier pricing.

---

# 23. Quick Notes

The baseline feature requires quick notes such as:

```text
Ít đá
Không đá
Ít ngọt
Không đường
Nhiều sữa
Mang về
```

Quick notes must have stable IDs.

Example:

```json
{
  "id": "less_ice",
  "label": "Ít đá"
}
```

The selected IDs are stored in the CartItem domain representation.

---

# 24. Quick Note Rules

The UI must support:

```text
unselected
selected
```

If the configured quick-note system allows multiple selections, multiple notes may be selected.

Do not hard-code labels in the JavaScript application logic.

---

# 25. Custom Note

Product Modal must support free-form item notes.

Example:

```text
Không cho ống hút
Làm riêng
```

Input:

```html
<textarea>
```

The note belongs to the CartItem.

It must not become an order-level note.

---

# 26. Quantity

Default quantity:

```text
1
```

Controls:

```text
-
quantity
+
```

Rules:

```text
quantity >= 1
```

Use Phase-01 Cart/CartItem domain rules.

Do not allow quantity 0 as an active cart item.

To remove an item, use the remove action.

---

# 27. Add to Cart

Before adding:

```text
product valid
variation valid if applicable
required modifiers valid
required configuration valid
quantity valid
stock/availability valid
```

Flow:

```text
Add
 ↓
construct configured CartItem input
 ↓
CartService
 ↓
updated CartView
 ↓
close modal
 ↓
render cart
```

The CartService remains authoritative for cart behavior.

---

# 28. Add Error Handling

If adding fails:

```text
Keep Product Modal open
Show actionable error
Preserve user selections
Do not silently discard configuration
```

Example:

```text
Product is no longer available.
```

or:

```text
Selected variation is no longer in stock.
```

---

# 29. Cart Rendering

Cart UI should render from `CartView` / `CartItemView`.

Do not calculate cart totals by walking arbitrary DOM nodes.

Flow:

```text
CartView
 ↓
Cart Panel renderer
 ↓
CartItemView[]
 ↓
Totals
```

---

# 30. Cart Item

Cart item must display:

```text
product
variation
modifier summary
quick note summary
custom note summary where useful
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

---

# 31. Cart Item Editing

Flow:

```text
Cart Item
 ↓
Edit
 ↓
Product Modal(mode=edit)
 ↓
Load existing configuration
 ↓
User modifies
 ↓
Update
 ↓
CartService.updateItem()
 ↓
Updated CartView
 ↓
Render Cart
```

The modal MUST restore all existing item state:

```text
product
variation
modifiers
quick notes
custom note
quantity
```

---

# 32. Cart Item Identity

Use the identity generated by Phase-01 domain logic.

Do not recalculate cart item identity independently in JavaScript.

The browser should treat the returned cart item key as authoritative.

---

# 33. Same Item Behavior

If the cashier adds the same configuration twice, the Cart domain determines whether the item should merge.

The UI must not implement its own merge logic.

---

# 34. Different Configuration Behavior

These should be treated as distinct cart items where their configuration differs:

```text
Latte / M / Oat
Latte / M / Soy
Latte / M / Oat + Less Ice
```

The final decision is made by Cart domain identity.

---

# 35. Quantity Change

Increase:

```text
Cart item
 ↓
CartService
 ↓
updated CartView
 ↓
render item + totals
```

Decrease:

```text
Cart item
 ↓
CartService
 ↓
updated CartView
```

If quantity would become zero, use the documented remove behavior rather than creating a zero-quantity item.

---

# 36. Remove Item

Flow:

```text
Remove
 ↓
CartService.removeItem()
 ↓
updated CartView
 ↓
render
```

No confirmation is required for individual item removal unless UX policy explicitly adds one.

---

# 37. Clear Cart

Flow:

```text
Clear
 ↓
Confirm Dialog
 ↓
Confirm
 ↓
CartService.clear()
 ↓
empty CartView
 ↓
render empty state
```

The feature baseline specifically requires confirmation before clearing the entire cart.

---

# 38. Cart Summary

Render from the CartView projection:

```text
Subtotal
Discount
Total
```

Do not calculate the authoritative total from the DOM.

Phase 03 may display the application's cart projection.

Final checkout recalculation remains a later phase.

---

# 39. Cart Empty State

After clearing or removing the last item:

```text
No items in cart
```

The checkout button must be disabled.

---

# 40. Cart Error Handling

If cart operation fails:

```text
Do not discard current valid cart state
Show error
Refresh/reconcile state if required
```

Examples:

```text
Item unavailable
Invalid configuration
Invalid quantity
```

---

# 41. Product Modal Lifecycle

Modal lifecycle:

```text
CLOSED
 ↓
LOADING
 ↓
READY
 ↓
VALIDATING
 ↓
SUCCESS
 ↓
CLOSED
```

Error branch:

```text
READY
 ↓
ERROR
 ↓
READY
```

---

# 42. Product Modal Data Loading

Opening a variable product may require:

```text
product detail request
```

The modal must display a loading state while data is being prepared.

Avoid showing stale data from the previous product.

When closing the modal, reset temporary modal state.

---

# 43. Modal Add/Edit Separation

The component must know whether it is:

```text
add mode
edit mode
```

But business operations remain:

```text
CartService.addItem()
CartService.updateItem()
```

Do not create two separate configuration engines.

---

# 44. JavaScript Modules

Phase 03 should extend the Phase-02 module structure rather than create a new monolithic POS script.

Suggested additions:

```text
assets/js/
├── api/
│   ├── products.js
│   ├── categories.js
│   └── cart.js
│
├── components/
│   ├── product-card.js
│   ├── product-grid.js
│   ├── category-nav.js
│   ├── product-modal.js
│   ├── variation-selector.js
│   ├── modifier-selector.js
│   ├── quick-notes.js
│   ├── quantity-control.js
│   ├── cart-panel.js
│   └── cart-item.js
│
├── state/
│   └── cashier-store.js
│
└── screens/
    └── cashier.js
```

The exact split may follow the Phase-02 implementation.

Do not duplicate an existing module.

---

# 45. Cashier Store / Client State

The client may maintain a projection of:

```text
products
categories
current search
active category
product modal state
cart view
```

The cart domain state is still authoritative through the application layer.

The frontend store MUST NOT invent alternate business rules.

---

# 46. API Client

Create a small API abstraction rather than scattering `fetch()` calls throughout components.

Example responsibilities:

```text
product API
category API
cart API
```

The API client should:

- handle request creation
- parse common response envelope
- map common API errors
- return application-friendly results

Components should not know raw endpoint URLs.

---

# 47. REST Response Contract

Use the existing API contract:

```json
{
  "success": true,
  "data": {}
}
```

Errors:

```json
{
  "success": false,
  "error": {
    "code": "invalid_cart",
    "message": "The cart is no longer valid.",
    "details": {}
  }
}
```

Do not introduce a new frontend response shape.

---

# 48. API Endpoints Used

At minimum:

```text
GET /coffeepos/v1/categories
GET /coffeepos/v1/products
GET /coffeepos/v1/products/{id}
```

Cart operations may use an existing endpoint if implemented during Phase 03.

If additional endpoints are required, document them before implementation.

Recommended conceptual operations:

```text
POST /coffeepos/v1/cart/validate
POST /coffeepos/v1/cart/items
PATCH /coffeepos/v1/cart/items/{key}
DELETE /coffeepos/v1/cart/items/{key}
DELETE /coffeepos/v1/cart
```

The final endpoints may use a different REST shape if it follows `API-ARCHITECTURE.md`, but the operations must remain equivalent.

---

# 49. Cart API Rule

The frontend must not send trusted:

```text
final total
discount amount
authoritative price
payment state
```

The API may accept cart configuration/product IDs/quantities and return the server/application projection.

---

# 50. PHP Templates

Phase 03 should extend the Phase-02 templates.

Expected conceptual additions:

```text
templates/cashier/
├── product-grid.php
├── product-modal.php
├── cart-items.php
└── ...
```

And reusable components:

```text
templates/components/
├── product-card.php
├── cart-item.php
├── variation-selector.php
├── modifier-group.php
├── quick-notes.php
└── quantity-control.php
```

Only create templates actually used.

Do not move product/card HTML into large JavaScript strings.

---

# 51. Product Card Rendering

Preferred:

```text
PHP template
    ↓
ProductView data
    ↓
Product Card
```

For subsequent dynamic search/category updates, the implementation may:

- re-render a PHP fragment
- render a tightly scoped client-side view from a documented data structure

PHP templates remain the preferred HTML structure where server fragments are practical.

Do not introduce a second templating engine.

---

# 52. Product Modal HTML

Product Modal structure must be server-defined.

JavaScript may update:

```text
selected state
price display
disabled state
quantity display
note selection
error/loading states
```

Do not build the whole modal as a JavaScript template literal.

---

# 53. Stable Selectors

The following should remain stable:

```text
data-component="product-grid"
data-component="product-card"
data-component="product-modal"
data-component="variation-selector"
data-component="modifier-group"
data-component="quick-notes"
data-component="quantity-control"
data-component="cart-panel"
data-component="cart-item"
```

Actions:

```text
select-product
select-variation
select-modifier
toggle-quick-note
increase-quantity
decrease-quantity
add-to-cart
update-cart-item
edit-cart-item
remove-cart-item
clear-cart
```

Do not use CSS styling classes as the primary JS contract.

---

# 54. Loading / Error / Empty UX

## Product Grid

```text
loading
empty
error
```

## Product Modal

```text
loading
ready
invalid
error
```

## Cart

```text
empty
updating
error
normal
```

## Add/Update

```text
idle
submitting
success
error
```

Buttons performing non-idempotent operations must prevent accidental double submission.

---

# 55. Out-of-Stock Handling

Product Card:

```text
out_of_stock
```

must not directly add the item.

Variable product:

```text
variation unavailable
```

must prevent Add to Cart.

Do not rely only on the initial product-grid stock state.

The application must validate availability when necessary.

---

# 56. Race Conditions

The implementation must handle these cases safely:

```text
User searches "Latte"
User quickly searches "Mocha"
```

Older search result must not overwrite the newer request.

Similarly:

```text
User clicks product
User quickly closes/reopens modal
```

stale product data must not overwrite current modal state.

Use request sequencing/abort mechanisms where practical.

---

# 57. Double Submission

Prevent duplicate:

```text
add
update
remove
clear
```

requests from rapid clicks.

Use:

```text
pending state
button disabling
request identity where appropriate
```

Do not create duplicate cart mutations.

---

# 58. Performance

Requirements:

- debounce search
- avoid full-screen DOM replacement
- use targeted updates
- avoid unnecessary product detail requests
- do not fetch full product details for every product in the initial grid
- load variation detail only when required

---

# 59. Security

All server operations remain protected.

The frontend may provide nonce/API authentication data through the established Phase-00/API foundation.

Do not:

- trust client prices
- trust client stock
- trust client coupon result
- expose server secrets
- bypass capability checks

---

# 60. Database Rules

No new database tables may be created in Phase 03.

Do not persist the active Cart to a custom table.

Suspended Cart belongs to a later phase.

---

# 61. WooCommerce Rules

Use WooCommerce APIs/CRUD through the Phase-01 gateways.

Do not:

```text
query wp_posts directly for products
query wp_postmeta directly for variation data
create duplicate product records
```

unless an architecture-approved compatibility case exists.

---

# 62. Acceptance Criteria

Phase 03 is complete when:

## Product Menu

1. Real WooCommerce categories load.
2. Active category works.
3. Real WooCommerce products load.
4. Product cards show name/image/price.
5. Product availability is visible.
6. Live product search works.
7. Search is debounced.
8. Search empty state works.
9. Search error state works.
10. Category and search interaction is deterministic.

## Simple Product

11. Purchasable simple product can be added.
12. Out-of-stock simple product cannot be added.

## Variable Product

13. Variable product opens Product Modal.
14. Variation attributes are generated dynamically.
15. Multiple attributes are supported.
16. Invalid combination is rejected.
17. Variation not belonging to product is rejected.
18. Unavailable variation cannot be added.
19. Selected variation updates displayed price.
20. Product Modal supports add mode.

## Modifiers / Notes

21. Modifier groups render from configuration.
22. Modifier selection rules are respected.
23. Modifier price projection updates appropriately.
24. Quick notes can be selected.
25. Quick notes have stable IDs.
26. Custom note can be entered.
27. Notes belong to the CartItem.

## Quantity

28. Default quantity is 1.
29. Increase works.
30. Decrease works.
31. Quantity cannot become invalid.
32. Quantity is included in CartItem.

## Cart

33. Added item appears in cart.
34. Cart renders from CartView/CartItemView.
35. Same configuration follows domain merge behavior.
36. Different configuration can remain separate.
37. Cart item can be edited.
38. Edit restores full configuration.
39. Cart item can be removed.
40. Cart can be cleared after confirmation.
41. Empty cart state renders.
42. Subtotal/discount/total display updates from cart projection.
43. Checkout remains disabled/out of scope.

## Reliability

44. Duplicate rapid actions are prevented.
45. Stale search requests cannot overwrite newer results.
46. Modal stale data cannot overwrite active product state.
47. Errors preserve user work where possible.

---

# 63. Required Test Cases

## Product

```text
TC-01 load categories
TC-02 load product list
TC-03 category filter
TC-04 live search
TC-05 empty search result
TC-06 product load error
TC-07 out-of-stock product
```

## Variation

```text
TC-08 simple product
TC-09 variable product
TC-10 single attribute
TC-11 multiple attributes
TC-12 invalid combination
TC-13 product/variation mismatch
TC-14 unavailable variation
TC-15 variation price update
```

## Modifier

```text
TC-16 optional modifier
TC-17 required modifier
TC-18 single-select group
TC-19 multi-select group
TC-20 minimum/maximum validation
```

## Notes

```text
TC-21 quick note selection
TC-22 multiple quick notes
TC-23 custom note
TC-24 edit restores notes
```

## Cart

```text
TC-25 add simple product
TC-26 add variable product
TC-27 same configuration
TC-28 different configuration
TC-29 increase
TC-30 decrease
TC-31 edit item
TC-32 remove item
TC-33 clear cart
TC-34 empty cart
```

## Reliability

```text
TC-35 double add click
TC-36 stale search response
TC-37 modal stale request
TC-38 failed cart mutation preserves state
```

---

# 64. Browser Verification

Unlike Phase 01, Phase 03 is a UI-heavy phase.

The implementation must be manually/browser verified in a real WordPress environment.

Minimum browser checks:

```text
Cashier route opens
Category click
Product grid
Search
Simple product add
Variable product modal
Variation selection
Modifier selection
Quick notes
Custom note
Quantity
Add to cart
Edit cart item
Remove item
Clear cart
Error state
Loading state
Responsive POS layout
```

Do not claim these passed unless actually tested in a browser.

---

# 65. Completion Report

Junie MUST report:

## Changed

All created/modified files.

## Product

How products/categories/search were connected.

## Variation

How full attribute-set variation resolution is used.

## Modifiers

How modifier configuration is represented.

## Notes

Quick notes/custom note behavior.

## Cart

How CartService/CartView is integrated.

## API

Endpoints created/used.

## UI

New templates/components/modules.

## Selectors

New stable `data-component`/`data-action` contracts.

## Tests

For each relevant test:

```text
PASS
FAIL
BLOCKED
```

## Browser Verification

List actual browser-tested flows.

## Scope

Explicitly confirm that these were NOT implemented:

```text
customer lookup
membership
dine-in/table business logic
coupons
checkout
payment
order creation
Customer Display
KDS
Order Queue
Order History
Shift
Reports
Refund
Suspended Cart
```

## Issues

List remaining technical/architecture issues.

---

# 66. Definition of Done

Phase 03 is complete only when:

```text
Real WooCommerce products
        ↓
Cashier Product Grid
        ↓
Product Configuration Modal
        ↓
Variation / Modifier / Notes
        ↓
Phase-01 CartService
        ↓
CartView
        ↓
Cashier Cart Panel
```

works end-to-end for the defined feature scope.

The implementation must remain compatible with the Phase-01 domain/application contracts.

---

# 67. Final Phase 03 Rule

When Phase 03 is complete:

STOP.

Do not automatically implement Phase 04.

Phase 04 will explicitly introduce:

```text
Customer
Membership
Order Type
Dine-in
Table
Takeaway
```

and must build on the cart/product functionality completed here.
