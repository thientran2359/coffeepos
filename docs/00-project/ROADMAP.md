# ROADMAP.md

# CoffeePOS Development Roadmap

## 1. Roadmap Philosophy

CoffeePOS will be developed incrementally.

Each phase must leave the plugin in a working state.

A later phase may depend on earlier contracts, but it must not silently redefine them.

The order is designed to establish stable foundations before implementing dependent workflows.

---

# Phase 00 — Specification Baseline

## Goal

Establish the documentation and development rules.

## Work

- define project scope
- define architecture
- define domain model
- define state machines
- define database model
- define UI architecture
- define API contracts
- define coding standards
- define Codex project instructions

## No feature implementation

This phase should not implement cashier, checkout, KDS, or reporting functionality.

## Exit Criteria

- architecture documentation exists
- domain terminology is defined
- state transitions are defined
- data ownership is defined
- Codex can determine what it is allowed to implement

---

# Phase 01 — Plugin Foundation

## Goal

Create a stable plugin foundation.

## Work

- plugin bootstrap
- plugin constants
- autoloading
- service/container foundation if required
- WordPress hooks
- WooCommerce dependency detection
- capabilities
- nonce foundation
- settings foundation
- POS page/routing foundation
- asset loading
- base template system
- base JS/CSS structure

## Exit Criteria

The plugin activates cleanly and provides the infrastructure required by later phases.

No cashier business logic should be implemented here.

---

# Phase 02 — POS Shell & Cashier UI

## Goal

Build the cashier screen structure.

## Work

- full-screen POS shell
- header
- category navigation
- search area
- catalog category sections
- cart area
- order type UI
- customer area
- totals area
- checkout entry point
- loading/error/empty states

## Important Constraint

This phase focuses on UI structure and interaction foundation.

Product configuration and complete cart business logic belong primarily to Phase 03.

## Exit Criteria

The cashier screen is visually and structurally complete enough for Phase 03 to attach product/cart behavior.

---

# Phase 03 — Products, Variations & Cart

## Goal

Implement the core order-entry engine.

## Work

- product loading
- category-section rendering
- category scroll navigation and scroll-spy
- live search
- search-to-product navigation
- stock display
- simple product handling
- variable product handling
- product configuration modal
- variation selection
- modifiers/options where defined
- quantity selection
- quick notes
- custom note
- add to cart
- WooCommerce session-backed active cart
- cart revision/concurrency contract
- cart item rendering
- cart item editing
- quantity changes
- remove item
- clear cart
- subtotal
- discount foundation
- total calculation
- WooCommerce-owned product/variation pricing

## Core Flow

```text
Product
→ Product Modal
→ Variation
→ Options/Modifiers
→ Quantity
→ Quick Note
→ Custom Note
→ Add to Cart
```

## Exit Criteria

A cashier can construct a complete cart without creating a WooCommerce order.

---

# Phase 04 — Customer Context & Order Type Foundation

## Goal

Add customer and service context to the cart.

## Work

- guest mode
- existing-customer mode
- phone lookup
- customer selection
- optional membership projection
- dine-in
- table selection
- takeaway
- cart/customer synchronization

## Exit Criteria

The cart contains all required customer and service context for checkout.

---

# Phase 05 — Checkout, Payment & WooCommerce Order

## Goal

Turn a POS cart into a WooCommerce order.

## Work

- checkout validation
- coupon application/removal
- cash payment
- quick cash denomination controls
- change calculation
- bank-transfer flow
- VietQR display
- payment state
- WooCommerce order creation
- order metadata
- receipt flow
- success modal

## Exit Criteria

A valid POS cart can produce a valid WooCommerce order and complete the supported payment flows.

---

# Phase 06 — Customer Display

## Goal

Provide the customer-facing display.

## Work

- customer display route
- display shell
- read-only product menu grouped by category
- shared CatalogView rendering
- cashier synchronization
- BroadcastChannel message contract
- `pos_session_id` channel isolation
- revision ordering
- ready/request/snapshot recovery
- cart display
- customer display
- payment display
- VietQR display
- payment success
- thank-you state
- reset state

## Exit Criteria

The customer can see the cashier's current cart and payment state in real time.

---

# Phase 07 — KDS & Order Queue

## Goal

Provide operational order processing.

## Work

### KDS

- new orders
- polling
- sound notification
- preparation state
- item details
- notes
- timer
- 5-minute warning
- 10-minute critical state
- one-click state transitions

### Order Queue

- active order list
- filtering/display
- complete
- cancel
- reprint
- polling

## Exit Criteria

A newly created POS order reaches operational screens correctly.

---

# Phase 08 — Membership & Customer Identity

## Goal

Complete the cashier and Customer Display membership workflow on top of the
Phase-04 customer-context foundation.

## Work

- guest/member identification
- automatic exact phone lookup
- member detail autofill
- member creation through WooCommerce customer APIs
- duplicate-phone protection
- attach/remove member on the active cart
- member identity on Customer Display
- masked customer phone on Customer Display
- cashier/customer-display synchronization
- membership extension boundary for later loyalty benefits

## Exit Criteria

The cashier can identify or create a member by phone, use that member on the
active cart/order, and show a privacy-safe guest/member projection on Customer
Display.

---

# Phase 09 — Shift Management

## Goal

Track cashier sessions and cash reconciliation.

## Work

- open shift
- opening cash
- active shift
- shift statistics
- expected cash
- close shift
- actual cash
- variance
- shift notes
- shift history

## Exit Criteria

A complete shift can be opened, operated, closed, and reconciled.

---

# Phase 10 — Order History, Refund & Quick Reorder

## Goal

Provide historical order operations.

## Work

- date filtering
- status filtering
- order search/listing
- order detail
- receipt reprint
- quick reorder
- cancel
- refund where supported

## Exit Criteria

Historical POS orders can be inspected and common follow-up operations can be performed safely.

---

# Phase 11 — Reports, Analytics & Hardening

## Goal

Complete reporting and production hardening.

## Work

- revenue reports
- order reports
- AOV
- products sold
- payment composition
- best sellers
- peak hours
- CSV export
- Excel export
- security audit
- performance audit
- error handling audit
- permission audit
- WooCommerce compatibility review

## Exit Criteria

The plugin satisfies the documented requirements and can be evaluated as a production-ready POS foundation.

---

# 2. Dependency Map

```text
Phase 00
   ↓
Phase 01
   ↓
Phase 02
   ↓
Phase 03
   ↓
Phase 04
   ↓
Phase 05
   ├────────→ Phase 06 ────────→ Phase 08 ─┐
   └────────→ Phase 07 ────────────────────┤
                                           ↓
                                        Phase 09
                                           ↓
                                        Phase 10
                                           ↓
                                        Phase 11
```

The dependency map is conceptual.

A phase may use infrastructure prepared by another phase, but it must not bypass the documented domain/API contracts.

---

# 3. Phase Completion Rule

A phase is complete only when:

1. Implementation exists.
2. Acceptance criteria pass.
3. Relevant tests/checks pass.
4. No known previous-phase functionality is broken.
5. Documentation matches the implementation.

---

# 4. Change Control

If implementation reveals that a requirement cannot be satisfied using the current architecture:

1. Stop the affected implementation.
2. Document the conflict.
3. Propose the smallest architectural change.
4. Update the relevant architecture document.
5. Update affected phase documentation.
6. Then continue implementation.

Do not silently redesign the project.
