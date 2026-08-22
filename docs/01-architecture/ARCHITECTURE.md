# ARCHITECTURE.md

# CoffeePOS Architecture

## 1. Purpose

This document defines the mandatory high-level architecture for CoffeePOS.

It exists to prevent feature implementations from creating inconsistent layers, duplicated business rules, arbitrary storage, or tightly coupled UI code.

CoffeePOS is a WooCommerce-native POS plugin.

The architecture MUST preserve WooCommerce as the canonical commerce system while allowing CoffeePOS to provide POS-specific workflows and operational features.

---

# 2. Architecture Principles

## 2.1 WooCommerce remains the commerce source of truth

WooCommerce owns canonical commerce entities:

- Products
- Product variations
- Prices
- Stock
- Customers
- Coupons
- Orders
- Order items
- Refunds
- WooCommerce order status/payment state where applicable

CoffeePOS MUST NOT create parallel versions of these entities unless a documented requirement explicitly requires a POS-specific projection/cache.

---

## 2.2 UI is not the business layer

Cashier, Customer Display, KDS, Order Queue, Order History, and Reports are UI/application surfaces.

They MUST NOT independently implement business rules for:

- pricing
- discounts
- stock authority
- payment success
- order totals
- authorization
- refund amounts
- shift reconciliation

Business-critical decisions MUST be validated server-side.

---

## 2.3 Separate presentation, application, domain and infrastructure concerns

The preferred dependency direction is:

```text
Presentation
     ↓
Application
     ↓
Domain
     ↓
Infrastructure / Integrations
     ↓
WordPress / WooCommerce
```

Dependencies MUST NOT point upward.

For example:

- UI must not instantiate WooCommerce internals directly when an application service exists.
- Domain code must not render HTML.
- Templates must not perform database queries.
- JavaScript must not contain authoritative pricing logic.

---

# 3. Logical Layers

## 3.1 Presentation Layer

Responsible for:

- PHP templates
- HTML structure
- UI components
- CSS/SCSS
- Vanilla JavaScript
- project-owned TemplateRenderer
- user interaction
- local UI state
- rendering API/service results

Typical locations:

```text
templates/
assets/js/
assets/css/
```

The Presentation layer may call application endpoints/services indirectly through approved AJAX/REST contracts.

---

## 3.2 Application Layer

Responsible for use cases.

Examples:

```text
AddCartItem
LoadCatalog
UpdateCartItem
RemoveCartItem
ApplyCoupon
IdentifyCustomer
CreateOrder
ProcessCashPayment
StartBankTransferPayment
OpenShift
CloseShift
StartKDSOrder
CompleteKDSOrder
Reorder
RefundOrder
```

An application use case coordinates:

- validation
- domain operations
- WooCommerce integrations
- persistence
- events
- response DTO/data

Application services MUST NOT contain HTML rendering.

---

## 3.3 Domain Layer

Responsible for CoffeePOS concepts and business rules that do not belong to a UI or integration.

Examples:

```text
Cart
CartItem
OrderContext
PaymentContext
Shift
KDSState
SuspendedCart
OrderType
PaymentState
ShiftState
```

Domain objects SHOULD remain independent from:

- WordPress rendering
- REST controllers
- JavaScript
- template files

Where WooCommerce data is required, adapters/integrations translate WooCommerce data into domain-safe representations.

---

## 3.4 Infrastructure Layer

Responsible for integration with external/system concerns:

- WooCommerce
- WordPress
- database
- options
- custom tables
- filesystem
- REST transport
- AJAX transport
- printer integration
- payment-provider integration
- event/synchronization infrastructure

Infrastructure code MUST NOT redefine domain rules.

---

## 3.5 Integration Layer

CoffeePOS will have dedicated integration boundaries for:

```text
WooCommerce
WordPress
Payment Provider
Receipt Printer
Customer Display Sync
```

An integration adapter isolates vendor/system-specific APIs from the application/domain layer.

---

# 4. Recommended Code Organization

The exact final directory structure is defined during Phase 00, but the architecture SHOULD map to responsibilities similar to:

```text
includes/
├── Core/
├── Domain/
├── Application/
├── Infrastructure/
├── Integration/
├── POS/
├── REST/
├── Admin/
└── Support/

templates/
├── cashier/
├── customer/
├── kds/
├── order-queue/
├── order-history/
├── shifts/
├── reports/
└── components/

assets/
├── js/
│   └── ui/
│       └── template-renderer.js
├── css/
└── images/
```

Do not create a directory merely to satisfy this diagram. Each namespace/directory must have a defined responsibility.

---

# 5. Dependency Rules

Allowed:

```text
Template
→ Presentation helper / application output

JS Controller
→ REST/AJAX endpoint
→ JSON projection
→ TemplateRenderer
→ targeted DOM region

REST Controller
→ Application Service

Application Service
→ Domain
→ Infrastructure interfaces

Infrastructure
→ WordPress/WooCommerce
```

Forbidden:

```text
Template
→ Database

Template
→ WooCommerce mutation

JavaScript
→ Direct database

JavaScript
→ Authoritative price calculation

Domain
→ HTML

Domain
→ WordPress global state

CSS
→ Business logic
```

---

# 6. UI Architecture

Each major screen is a separate application surface:

```text
Cashier
Customer Display
KDS
Order Queue
Order History
Shifts
Reports
```

A screen MAY use shared components.

A screen MUST NOT directly depend on internal DOM structure of another screen.

Cashier and Customer Display share `CatalogView` data but use separate
PHP-owned templates and screen controllers. A layout change in one screen must
not require changing the other screen's markup or duplicating catalog logic.

Shared component contracts belong in `COMPONENTS.md`.

Shared catalog projection, category-section navigation, search location, and
Customer Display menu contracts belong in `docs/03-ui/CATALOG-UI.md`.

Shared state contracts belong in `STATE-MACHINES.md` and `DATA-FLOW.md`.

---

# 7. PHP-Owned Templates and JSON Rendering

CoffeePOS uses a hybrid rendering model. PHP owns all HTML structure, including
the native `<template>` elements used for dynamic components. AJAX/REST
endpoints return JSON projections rather than rendered HTML. A single
project-owned Vanilla JavaScript library, `TemplateRenderer`, clones the PHP
template and binds the JSON data into a `DocumentFragment`.

```text
PHP template
    ↓ emits
native <template> blueprint
    ↑                 ↓ cloned by
AJAX/REST JSON → TemplateRenderer
                      ↓
              targeted DOM region
```

Rules:

- Static screen shells and dynamic component blueprints belong in PHP templates.
- Each dynamic component has one authoritative PHP-owned markup definition.
- API responses expose DTO/view JSON and do not include HTML fragments for
  standard UI updates.
- `TemplateRenderer` is the only client-side template renderer.
- The renderer binds text with `textContent`; it does not interpolate raw HTML.
- `data-field` binds text, `data-attr` binds approved attributes/properties, and
  `data-key` declares stable list identity.
- Controllers coordinate nested collections and component state; the renderer
  does not contain business rules or an expression language.
- Event delegation uses stable `data-action` hooks after fragments are inserted.
- Server-side PHP component rendering remains valid for initial or server-only
  output, but it must not create a second markup definition for an AJAX-driven
  component.

Do not use JavaScript HTML/template strings, direct JSON-to-`innerHTML`
interpolation, or a second template engine such as Handlebars.

---

# 8. JavaScript Architecture

JavaScript uses Vanilla JS.

The frontend should be organized by responsibility rather than one monolithic `pos.js`.

Recommended conceptual modules:

```text
core/
state/
api/
ui/
ui/template-renderer
components/
screens/
sync/
utils/
```

Example:

```text
screens/cashier/
components/cart/
components/product/
components/modal/
api/
sync/
```

Do not split files merely for file count. A module should represent a coherent responsibility.

---

# 9. State Ownership

State MUST have one authoritative owner.

Examples:

```text
Cart editing state
→ CartService + WooCommerce session, scoped by pos_session_id

Customer display presentation state
→ Customer Display, synchronized from cashier

KDS operational state
→ Server/WooCommerce order state + KDS application state

Shift state
→ Server-side shift record

Payment state
→ Server/payment integration
```

The browser may maintain temporary UI state and the latest cart projection, but
it MUST NOT be the authoritative source for cart contents, prices, totals, or
other server-critical state.

Each successful cart mutation increments a monotonic `revision` stored with the
session cart. Mutations that provide an outdated `expected_revision` must fail
with a conflict and return or allow retrieval of the latest projection.

---

# 10. Realtime / Synchronization

The current feature specification requires `BroadcastChannel` for two-way cashier/customer-display synchronization.

Therefore:

```text
Cashier
   ↕
BroadcastChannel
   ↕
Customer Display
```

The synchronization layer MUST use documented event types and payload contracts.

The channel is scoped by the opaque `pos_session_id`. Cashier broadcasts only
server-confirmed projections. Customer Display may request a snapshot and
acknowledge readiness, but it cannot mutate cart, price, payment, or order
state through `BroadcastChannel`.

Do not allow individual screens to invent their own message structures.

The detailed contract belongs in `SYNC-API.md` and `DATA-FLOW.md`.

---

# 11. API Architecture

Transport may use:

- WordPress REST API
- WordPress AJAX

The choice should be made per use case.

Rules:

- business operations should have a single application service
- REST and AJAX should be thin adapters
- authorization must occur server-side
- request payloads must be validated
- responses must use stable documented structures
- transport-specific details must not leak into domain code

---

# 12. WooCommerce Integration

WooCommerce integration should be centralized.

Do not scatter direct WooCommerce operations throughout controllers/templates.

Examples of integration responsibilities:

```text
ProductRepository
VariationRepository
CustomerRepository
CouponService
CartSessionStore
OrderRepository
StockService
RefundService
```

WooCommerce product and variation prices are canonical. Cart item creation and
revalidation resolve price through the WooCommerce integration boundary; price
values supplied by the browser are ignored. Current CoffeePOS modifiers are
non-priced configuration. A price-changing choice must use a WooCommerce
variation or another explicitly approved WooCommerce pricing mechanism.

The Application layer depends on a `CartSessionStoreInterface`; the
Integration/Infrastructure implementation uses the WooCommerce session API.
Controllers and domain objects must not call `WC()->session` directly. The
session adapter owns cart serialization, hydration, namespaced keys, expiry,
revision checks, and persistence.

These names are conceptual. Phase 00 should choose the final abstractions after checking the installed WooCommerce version and project constraints.

---

# 13. Error Handling

Errors must have consistent categories:

```text
ValidationError
AuthorizationError
NotFoundError
ConflictError
PaymentError
WooCommerceError
InfrastructureError
```

The exact implementation is defined in the coding/API documentation.

Client-facing messages should be safe and useful.

Internal errors should be logged through the project's logging strategy.

Do not expose sensitive internal data to the browser.

---

# 14. Events

The application may emit events for important cross-screen or cross-domain changes.

Examples:

```text
cart.updated
customer.identified
order.created
order.payment_started
order.payment_completed
order.cancelled
order.refunded
kds.order_created
kds.order_started
kds.order_ready
shift.opened
shift.closed
```

Events are coordination mechanisms, not replacements for business rules.

Event names and payloads must be documented before use.

---

# 15. Caching

Caching is optional and must never make stale data authoritative.

Potentially cacheable:

- category lists
- product display data
- static UI configuration

Must remain authoritative on the server:

- stock
- price
- coupon validity
- payment state
- order totals
- shift totals
- refund amounts

---

# 16. Performance

The POS is an operational application.

Prefer:

- minimal DOM updates
- focused API requests
- server-side query optimization
- pagination where appropriate
- debounced search
- efficient product rendering
- avoiding repeated expensive WooCommerce queries

Do not optimize by bypassing data integrity.

---

# 17. Extensibility

Future extensions should use documented extension points rather than modifying core application flow.

Potential extension points:

- payment methods
- modifiers
- receipt printers
- membership integrations
- customer display providers
- reporting providers

Do not build speculative abstractions until a concrete extension requirement exists.

---

# 18. Backward Compatibility

Completed phase behavior is considered a contract.

When modifying existing code:

1. identify affected consumers
2. update relevant documentation
3. update tests
4. preserve existing behavior where possible

Do not perform broad refactoring during feature implementation.

---

# 19. Architecture Decision Rule

When a new feature does not fit the architecture:

1. document the conflict
2. identify the smallest change
3. update architecture documentation
4. update affected phase/API/database documents
5. then implement

Codex MUST NOT silently introduce architectural exceptions.
