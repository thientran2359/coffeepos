# JUNIE.md

# CoffeePOS — Junie Development Rules

## 1. Project Identity

CoffeePOS is a WooCommerce-based Point of Sale plugin for a coffee shop.

The project uses:

- WordPress
- WooCommerce
- PHP
- Vanilla JavaScript
- CSS/SCSS
- PHP templates

The authoritative feature source is `featured.txt`.

Architecture and implementation rules in the `docs/` directory define how those features must be implemented.

---

## 2. Primary Development Principle

Junie is an implementation agent, not the product architect.

Before implementing a task, Junie MUST:

1. Read this file.
2. Read the relevant project documentation.
3. Read the current phase specification.
4. Inspect the existing implementation.
5. Identify dependencies and affected consumers.
6. Implement only the requested scope.
7. Run the checks defined by the current phase.
8. Verify the acceptance criteria.
9. Report changed files and any unresolved issues.

Junie MUST NOT silently redesign the architecture while implementing a feature.

If the existing implementation conflicts with the documentation, stop and report the conflict unless the current phase explicitly authorizes resolving it.

---

## 3. Source of Truth Hierarchy

When sources conflict, use this priority:

1. Current phase specification
2. `ARCHITECTURE.md`
3. `DOMAIN-MODEL.md`
4. `DATABASE.md`
5. Other architecture/API/UI specifications
6. `REQUIREMENTS.md`
7. `featured.txt`
8. Existing implementation

Existing code is not automatically correct.

A feature may be implemented only when its required behavior is defined by the current specification or an approved project decision.

---

## 4. Technology Constraints

### Backend

Use:

- WordPress APIs
- WooCommerce APIs
- PHP
- WordPress capabilities
- WordPress nonce/security APIs

Do not modify WooCommerce core.

### Frontend

Use:

- Vanilla JavaScript
- PHP-rendered HTML templates
- CSS/SCSS

Do NOT introduce React, Vue, Alpine, jQuery-dependent application architecture, or another frontend framework unless explicitly approved in project documentation.

### Templates

HTML structure belongs in PHP templates.

Do NOT build application HTML using large JavaScript template strings.

JavaScript is responsible for:

- UI behavior
- event handling
- client-side state where appropriate
- API communication
- DOM updates
- synchronization

PHP/domain services are responsible for:

- business rules
- WooCommerce integration
- authorization
- validation that must be trusted
- persistence

---

## 5. Scope Discipline

For every task:

- Do not refactor unrelated code.
- Do not rename existing public selectors without checking all consumers.
- Do not change existing IDs/classes casually.
- Do not change API response structures without updating the relevant contract.
- Do not introduce a new storage mechanism without documenting it.
- Do not create arbitrary order meta keys.
- Do not add custom database tables without updating database documentation.
- Do not replace a working implementation merely for stylistic preference.

If a refactor is necessary to complete the current phase, document why it is required.

---

## 6. UI Contract

UI selectors are part of the internal contract.

Before changing:

- IDs
- CSS classes
- `data-*` attributes
- template names
- DOM structure

Junie MUST search for all consumers.

Consumers include:

- JavaScript
- PHP
- CSS/SCSS
- tests
- AJAX/REST payload handling
- other templates

A selector change is an API change inside the frontend.

---

## 7. Domain Rules

Business logic MUST NOT be duplicated across:

- Cashier
- Customer Display
- KDS
- Order Queue
- Order History
- Reports

Shared business rules belong in domain/application services or the appropriate WooCommerce integration layer.

The UI must not be treated as the authoritative source of prices, totals, stock, permissions, or payment status.

---

## 8. WooCommerce Rules

WooCommerce remains the commerce/order system.

When an operation concerns:

- products
- variations
- prices
- stock
- customers
- coupons
- orders
- order items
- refunds
- payment

prefer WooCommerce APIs and data structures.

Do not duplicate WooCommerce entities unless the architecture explicitly defines a CoffeePOS-specific representation.

---

## 9. Security

Every server-side request MUST be protected appropriately.

Requirements include:

- capability checks
- nonce verification where applicable
- input validation
- sanitization
- authorization
- output escaping
- server-side validation of all business-critical values

Never trust:

- client-side prices
- client-side totals
- client-side stock status
- client-side payment success
- client-provided permissions

---

## 10. Phase Rules

Each phase has:

- objective
- prerequisites
- scope
- out-of-scope items
- files
- domain requirements
- UI requirements
- API requirements
- validation rules
- acceptance criteria
- test cases
- definition of done

Junie MUST NOT implement later-phase functionality unless the current phase explicitly requires a foundation for it.

Junie MUST NOT automatically continue to the next phase after completing the current phase.

---

## 11. State Management

State transitions must follow the documented state machines.

Do not invent new states during implementation.

If a required state is missing:

1. identify the gap,
2. report it,
3. update the relevant documentation only after approval,
4. then implement it.

---

## 12. Customer Display Synchronization

The Customer Display is a separate UI surface.

Its synchronization contract must be documented and stable.

The current feature specification requires real-time two-way synchronization using `BroadcastChannel`.

Do not replace or remove that mechanism without an explicit architecture decision.

Message types and payload structures must be defined centrally and reused by all consumers.

---

## 13. Testing

At minimum, verify:

- PHP syntax
- JavaScript syntax/build
- WordPress/WooCommerce integration points
- required selectors
- required API responses
- acceptance criteria
- relevant error states

For UI features, test both:

- normal flow
- failure/empty/loading/out-of-stock states

---

## 14. Completion Report

After implementation, Junie should report:

### Changed

List files created/modified.

### Implemented

Summarize completed requirements.

### Verified

List checks/tests performed.

### Not implemented

List anything intentionally left outside the phase.

### Risks / Issues

List unresolved issues or architecture conflicts.

---

## 15. Forbidden Behavior

Junie MUST NOT:

- rewrite the whole plugin during a feature task
- perform unrelated cleanup
- introduce a new framework
- move HTML into JavaScript template strings
- invent undocumented data structures
- bypass WooCommerce APIs without justification
- trust client-side totals or payment state
- modify WooCommerce core
- change public selectors without impact analysis
- silently change architecture
- silently skip acceptance criteria
