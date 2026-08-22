# CODING-STANDARDS.md

# CoffeePOS Coding Standards

## 1. General Rule

Prefer code that is explicit, testable, discoverable, and consistent with WordPress/WooCommerce conventions.

Do not optimize for abstraction count.

---

# 2. PHP

Use:

- strict responsibility boundaries
- namespaces where the project structure supports them
- small focused classes
- dependency injection where useful
- WordPress/WooCommerce APIs
- explicit validation
- early returns for invalid conditions

Avoid:

- huge god classes
- hidden global state
- business logic in templates
- duplicated WooCommerce queries
- arbitrary static helpers for everything

---

# 3. Naming

Use descriptive names.

Examples:

```text
CashierController
CartService
CheckoutService
OrderService
ShiftService
KdsService
ProductRepository
CustomerRepository
WooCommerceOrderAdapter
```

Avoid vague names:

```text
Helper
Manager
Utility
Data
Handler
Thing
```

unless their responsibility is genuinely clear.

---

# 4. JavaScript

Use modern Vanilla JavaScript.

Prefer:

- modules
- classes/functions with single responsibility
- `const` / `let`
- event delegation where appropriate
- explicit state boundaries
- `async/await`
- small API clients
- reusable DOM utilities
- the shared `TemplateRenderer` for JSON-driven component markup

Avoid:

- one global monolithic object
- scattered globals
- inline event handlers
- duplicated selectors
- duplicated API request logic
- HTML strings or direct JSON interpolation through `innerHTML`
- component-specific template engines

---

# 5. JavaScript State

Keep application state in a documented state owner.

Do not create multiple independent cart objects across modules.

Prefer:

```text
CartStore
    ↓
Cashier UI
    ↓
Customer Display sync
```

rather than several modules independently calculating cart state.

---

# 6. HTML / PHP Templates

Templates should primarily:

- receive prepared data
- render markup
- emit native `<template>` blueprints for AJAX-driven components
- escape output
- expose stable selectors

Dynamic template bindings use:

```text
data-field
data-attr
data-key
```

JavaScript must populate these templates through the shared `TemplateRenderer`.
Do not maintain a duplicate JavaScript markup definition for the same component.

When multiple screens consume the same projection, share the API/projection and
pure navigation utilities, not the screen markup. Cashier and Customer Display
must use separate PHP templates over the shared `CatalogView` so either layout
can evolve without conditional branches for the other screen.

Templates should NOT:

- query the database directly
- perform checkout
- perform stock mutations
- calculate authoritative totals

---

# 7. CSS / SCSS

Use consistent naming and component boundaries.

Prefer BEM-like naming where appropriate:

```text
dd-pos
dd-pos__header
dd-pos__catalog
dd-pos__category-section
dd-cart-item
dd-product-modal
```

Do not couple JavaScript behavior to purely visual classes when a dedicated `data-*` hook is clearer.

When behavior depends on a selector, document it.

---

# 8. Selectors

Prefer explicit hooks:

```html
<button
    class="dd-button dd-button--primary"
    data-action="checkout"
>
```

Avoid using fragile selectors based on visual structure.

Changing selectors requires impact analysis.

---

# 9. API Responses

Use predictable structures.

Success example:

```json
{
  "success": true,
  "data": {}
}
```

Error example:

```json
{
  "success": false,
  "error": {
    "code": "invalid_cart",
    "message": "The cart is no longer valid."
  }
}
```

The final response contract belongs in API documentation.

---

# 10. Error Codes

Error codes should be stable and machine-readable.

Examples:

```text
invalid_product
invalid_variation
out_of_stock
invalid_coupon
invalid_customer
invalid_order_type
invalid_table
payment_failed
unauthorized
shift_not_open
shift_already_closed
```

---

# 11. Documentation

When adding:

- new entity
- new table
- new endpoint
- new event
- new state
- new public selector
- new capability
- new settings key

update the corresponding documentation.

---

# 12. Refactoring

Refactoring is allowed only when:

- required by the current phase
- required to fix a verified bug
- explicitly requested

Do not combine unrelated refactoring with feature implementation.

---

# 13. Compatibility

Do not assume:

- a specific WooCommerce version beyond project requirements
- custom theme markup
- specific server configuration
- specific payment provider

Use documented capability/version checks where necessary.

---

# 14. Comments

Comments should explain why, not restate what code does.

Avoid comments such as:

```php
// Increment quantity
$quantity++;
```

Prefer comments explaining non-obvious WooCommerce compatibility or business rules.

---

# 15. Commits / Change Sets

A logical change should remain understandable from its changed files.

Do not mix:

- feature implementation
- large formatting changes
- unrelated refactors
- dependency changes

in one phase task.

---

# 16. Definition of Good Code

Good CoffeePOS code should be:

- easy to locate
- easy to reason about
- aligned with the documented architecture
- testable
- safe against client manipulation
- WooCommerce-aware
- minimally coupled
- explicit about state transitions
