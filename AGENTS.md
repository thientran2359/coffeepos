# CoffeePOS Codex Project Instructions

## Scope

This file applies to the entire CoffeePOS repository. Codex loads it
automatically when working in this project. A nested `AGENTS.md` or
`AGENTS.override.md` may add or override rules for its directory subtree.

## 1. Project Identity

CoffeePOS is a WooCommerce-based point-of-sale plugin for a coffee shop.

The project uses:

- WordPress
- WooCommerce
- PHP 7.4+
- Vanilla JavaScript
- CSS/SCSS
- PHP-owned HTML templates

`featured.txt` is the baseline feature source. Architecture, API, UI, database,
and phase specifications in `docs/` define how those features must be
implemented.

## 2. Working Method

For every implementation task, Codex must:

1. Identify the requested scope and active phase.
2. Read the relevant project documents and phase specification.
3. Inspect the existing implementation and repository status.
4. Preserve user changes already present in the worktree.
5. Search for dependencies and all affected consumers.
6. Implement only the requested scope.
7. Run the checks required by the active phase and affected code.
8. Verify the applicable acceptance criteria.
9. Report changed files, verification performed, and unresolved issues.

Do not silently redesign the architecture. If implementation conflicts with a
documented contract and the task does not authorize changing that contract,
report the conflict before making the architectural change.

Do not assume that the newest numbered phase in `docs/05-phases/` is active.
Use the phase named by the user or the task. When no phase is named, infer scope
from the requested behavior and existing implementation; ask only when that
ambiguity would materially change the result.

## 3. Source of Truth

When sources conflict, use this priority:

1. The user's current request and explicitly approved decisions
2. The active phase specification
3. `docs/01-architecture/ARCHITECTURE.md`
4. `docs/01-architecture/DOMAIN-MODEL.md`
5. `docs/02-database/DATABASE.md`
6. Other architecture, API, UI, and security specifications
7. `docs/00-project/REQUIREMENTS.md`
8. `featured.txt`
9. Existing implementation

Existing code is not automatically correct. A feature may be implemented only
when its behavior is defined by the active specification or an approved project
decision.

## 4. Documentation Routing

Read only the documents relevant to the task, plus their direct dependencies:

- Project scope and sequence: `docs/00-project/`
- Architecture, domain, state, security, and standards: `docs/01-architecture/`
- Persistence and WooCommerce ownership: `docs/02-database/`
- UI contracts: `docs/03-ui/`
- REST and synchronization contracts: `docs/04-api/`
- Phase scope and acceptance criteria: `docs/05-phases/`

When adding or changing an entity, table, endpoint, event, state, public
selector, capability, setting key, or storage rule, update the corresponding
document in the same change.

## 5. Technology Constraints

### Backend

Use WordPress and WooCommerce APIs, PHP, WordPress capabilities, and WordPress
nonce/security APIs. Never modify WooCommerce core.

### Frontend

Use Vanilla JavaScript, PHP-owned HTML templates, the project-owned
`TemplateRenderer`, and CSS/SCSS.

Do not introduce React, Vue, Alpine, a jQuery-dependent application
architecture, or another frontend framework unless explicitly approved in the
project documentation. Do not add a production dependency without explicit
approval.

### Responsibility Boundaries

HTML structure belongs in PHP templates. Static screen shells are rendered by
PHP. Repeated or AJAX-driven UI is defined by native `<template>` elements
emitted from PHP templates, then cloned and populated from JSON by the shared
`TemplateRenderer`.

Do not build application markup using JavaScript template strings. Do not add a
second client template engine such as Handlebars while the project-owned
renderer is the documented architecture.

JavaScript is responsible for:

- UI behavior and event handling
- client-side state where appropriate
- API communication
- binding JSON projections into PHP-owned templates
- targeted DOM updates
- synchronization

PHP domain/application services are responsible for:

- business rules
- WooCommerce integration
- authorization
- trusted validation
- persistence

## 6. Scope and Worktree Discipline

- Do not refactor unrelated code.
- Do not revert, overwrite, or delete user changes unrelated to the task.
- Do not use destructive Git commands unless the user explicitly requests them.
- Do not replace a working implementation for stylistic preference.
- Do not rename public selectors without checking all consumers.
- Do not change API response structures without updating their contracts and
  consumers.
- Do not introduce a storage mechanism or custom database table without
  updating the database documentation.
- Do not create arbitrary order meta keys.

If a refactor is necessary for the requested work, keep it minimal and explain
why it is required.

## 7. UI Contract

Treat UI selectors as internal API contracts. Before changing IDs, CSS classes,
`data-*` attributes, template names, or DOM structure, search all consumers in:

- JavaScript
- PHP
- CSS/SCSS
- tests
- REST/AJAX payload handling
- other templates

Use stable `data-component` and `data-action` hooks for behavior. Template data
binding uses the documented `data-field`, `data-attr`, and `data-key` contract.
Keep visual classes separate from JavaScript behavior when practical.

Cashier and Customer Display consume the shared `CatalogView` contract in
`docs/03-ui/CATALOG-UI.md` but own separate PHP templates. Do not duplicate
catalog fetching/grouping/pricing logic or couple one screen to the other's DOM.
Cashier category/search controls navigate the existing catalog sections; they
must not filter and replace the primary catalog.

## 8. Domain and WooCommerce Rules

Do not duplicate business logic across Cashier, Customer Display, KDS, Order
Queue, Order History, or Reports. Shared rules belong in domain/application
services or the appropriate WooCommerce integration layer.

The UI is never authoritative for prices, totals, stock, permissions, or
payment state.

WooCommerce remains the canonical system for products, variations, prices,
stock, customers, coupons, orders, order items, refunds, and payment-related
order state. Prefer WooCommerce CRUD and APIs. Do not duplicate WooCommerce
entities unless the architecture explicitly defines a CoffeePOS representation.

The active cart is authoritative server-side state stored in the WooCommerce
session and scoped by an opaque `pos_session_id`. Browser state is a projection
of that session cart. Cart mutations must use the documented revision contract.

Product and variation prices always come from WooCommerce. CoffeePOS modifiers
in the current architecture do not change price; any customer choice that
changes price must be represented by a WooCommerce variation or another
explicitly approved WooCommerce pricing mechanism.

## 9. Security

Every server-side request must use the protections appropriate to its route:

- capability checks
- nonce verification where applicable
- authentication and authorization
- input validation and sanitization
- output escaping
- server-side validation of business-critical values

Never trust client-provided prices, totals, stock status, payment success, or
permissions.

## 10. Phase Rules

Each phase defines its objective, prerequisites, scope, exclusions, contracts,
acceptance criteria, tests, and definition of done.

Do not implement later-phase functionality unless the active phase explicitly
requires a foundation for it. Stop at the active phase boundary and do not
continue automatically to the next phase.

State transitions must follow `docs/01-architecture/STATE-MACHINES.md`. Do not
invent a state. If a necessary state is missing, identify the gap and update the
contract only after approval.

## 11. Customer Display Synchronization

Customer Display is a separate UI surface. Its synchronization contract must be
stable and documented.

The current feature specification requires real-time two-way synchronization
through `BroadcastChannel`. Do not replace or remove that mechanism without an
explicit architecture decision. Define message types and payloads centrally and
reuse them across consumers.

Cashier and Customer Display must share the same `pos_session_id`. Use a
session-scoped channel, monotonic cart revisions, and the documented
ready/request/snapshot handshake so a display opened late receives current
state and cannot accept another terminal's cart.

## 12. Verification

Scale verification to the change. At minimum, consider:

- PHP syntax for each changed PHP file: `php -l <file>`
- JavaScript syntax for each changed JS file: `node --check <file>`
- Phase 01 scenarios: `php tests/Phase01/core_scenarios.php`
- Phase 02 shell contract: `php tests/Phase02/cashier_shell_contract.php`
- tests defined by the active phase, when present
- affected WordPress/WooCommerce integration points
- required selectors and API responses
- normal, loading, empty, error, and out-of-stock UI states as applicable

For UI behavior, verify in a real WordPress/WooCommerce browser environment when
available. Never claim a test or browser flow passed unless it was actually run.
If an environment dependency prevents a check, report it as blocked rather than
passed.

## 13. Forbidden Behavior

Codex must not:

- rewrite the whole plugin during a scoped task
- perform unrelated cleanup
- introduce a new frontend framework without approval
- move application HTML into large JavaScript template strings
- introduce a second client-side template engine
- invent undocumented data structures or persistence
- bypass WooCommerce APIs without documented justification
- trust client-side totals, stock, permissions, or payment state
- modify WooCommerce core
- change public selectors without impact analysis
- silently change architecture
- silently skip acceptance criteria
- claim verification that was not performed
