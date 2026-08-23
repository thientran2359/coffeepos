# PHASE-06.md

# CoffeePOS Phase 06 — Customer Display

> **Approved checkout-display revision (2026-08-23):** Customer Display opens a
> payment overlay when Cashier opens checkout. Cash selection shows the trusted
> cart total and a cash instruction. Bank selection shows the server-generated
> pre-order VietQR preview. Paid success appears only after manual-confirmation
> checkout creates a paid order and remains visible until Cashier starts a new
> order and sends `display.reset`. There is no automatic thank-you timeout. This
> supersedes conflicting timer/provider-pending language below.

## 1. Objective

Provide a separate customer-facing screen that renders the shared WooCommerce
catalog and follows the Cashier's latest server-confirmed cart, checkout, and
payment presentation state in real time.

Primary workflow:

```text
Cashier Cart Session
    ↓
Open / Pair Customer Display
    ↓
/pos/customer?pos_session_id={opaque-id}
    ↓
Shared CatalogView + Independent Customer Templates
    ↓
BroadcastChannel(coffeepos:<pos_session_id>)
    ↓
display.ready / state.snapshot Handshake
    ↓
Cart → Checkout → Payment → Success → Thank You → Idle
```

At the end of this phase:

- `/pos/customer` is a dedicated Customer Display application surface
- the display is explicitly paired with one Cashier `pos_session_id`
- the display renders a read-only menu from the shared `CatalogView`
- the display uses PHP-owned templates independent from Cashier markup
- cashier cart/customer/service changes appear without cart polling
- a display opened late receives the complete current snapshot
- stale, duplicate, malformed, and foreign-session messages are ignored
- revision gaps trigger snapshot recovery
- checkout amount, bank-transfer VietQR, and payment state are synchronized
- payment success appears only from a trusted Phase-05 result
- sale completion transitions through success and thank-you states
- reset/session rollover safely pairs the display with the next POS cart
- BroadcastChannel failure never changes server-side cart/order/payment state
- KDS, Order Queue, shifts, history, reports, and cross-device realtime transport
  remain out of scope

---

# 2. Prerequisites

Codex MUST read:

```text
AGENTS.md

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
docs/03-ui/CATALOG-UI.md
docs/03-ui/CASHIER-UI.md
docs/03-ui/CUSTOMER-DISPLAY-UI.md

docs/04-api/API-ARCHITECTURE.md
docs/04-api/POS-API.md
docs/04-api/ORDER-API.md
docs/04-api/SYNC-API.md

docs/05-phases/PHASE-01.md
docs/05-phases/PHASE-02.md
docs/05-phases/PHASE-03.md
docs/05-phases/PHASE-04.md
docs/05-phases/PHASE-05.md
```

Codex MUST inspect and preserve the completed Cashier, `CatalogView`, cart
session/revision, customer/service context, checkout, payment, order, and fresh
cart lifecycle contracts.

Do not create a second catalog service, cart store, payment authority, or order
projection only for Customer Display.

---

# 3. Scope

## In Scope

```text
Customer Display route
Customer Display shell
Explicit Cashier/display pairing
Opaque pos_session_id URL correlation
Read-only shared CatalogView menu
Customer-specific PHP catalog templates
Landscape menu/cart layout
Independent menu and cart scrolling
Customer-safe cart projection
Customer/member presentation
Order type/table presentation where appropriate
Checkout presentation
Cash payment amount/success presentation
Bank-transfer pending presentation
VietQR presentation
Payment success
Thank-you state
Display reset
Fresh POS session/channel rollover
BroadcastChannel transport
Central message-envelope factory and validator
Session-scoped channel naming
Source instance identity
Ready/request/snapshot handshake
Cart revision ordering
Non-cart workflow ordering contract
Duplicate-message protection
Revision-gap recovery
Reconnect and REST snapshot fallback
Loading, empty, unpaired, reconnecting, and unsupported-browser states
Cashier synchronization integration
```

## Out of Scope

Do NOT implement:

```text
Cart mutation from Customer Display
Product selection or add-to-cart controls
Coupon entry/removal from Customer Display
Customer lookup from Customer Display
Order-type/table selection from Customer Display
Payment confirmation from Customer Display
Manual "mark paid"
Order creation from Customer Display
Independent price/discount/change calculation
Independent customer/order/payment storage
WebSocket, SSE, long polling, or service-worker realtime transport
Cross-device or cross-browser-profile synchronization
Remote pairing codes/accounts
KDS
Order Queue
Shift management
Order History
Refunds
Reports
Hardware display drivers
Receipt printing
Touchscreen customer ordering
Advertising/media campaign management
New custom database tables
```

---

# 4. Architecture

Customer Display is a presentation surface:

```text
WooCommerce Catalog
    ↓
CatalogService
    ↓
CatalogView
    ├──→ Cashier PHP Templates
    └──→ Customer Display PHP Templates

Cashier Application State
    ↓
Server-confirmed Cart/Order/Payment Projections
    ↓
Sync Publisher
    ↓
BroadcastChannel(coffeepos:<pos_session_id>)
    ↓
Sync Validator / Customer Display Store
    ↓
Customer Display Renderer
```

Rules:

- Customer Display is not a second cart or order store
- BroadcastChannel is transport, not persistence or authority
- Cashier broadcasts only server-confirmed projections
- Customer Display renders projections and never runs business mutations
- Cashier and Customer Display share data contracts, not DOM structure
- the two screens own separate PHP templates and screen controllers
- the sync envelope and event types are centralized and reused by both screens
- catalog loading is independent from realtime cart synchronization
- failure in one region must not erase a valid projection in the other

---

# 5. Customer Display Route

Use the existing major-screen route:

```text
/pos/customer
```

Pairing uses the opaque logical cart ID:

```text
/pos/customer?pos_session_id={id}
```

Rules:

- `pos_session_id` may appear in the Customer Display URL
- it must match the same strict format accepted by the Cart API
- it is a correlation identifier, not an authentication credential
- it must never contain the WooCommerce session token, auth cookie, or REST nonce
- the route continues to require the documented CoffeePOS access capability
- invalid or missing pairing input renders an unpaired state, not a PHP fatal
- the route must not create a new cart merely because pairing input is absent
- URL output is escaped and no sensitive data is written into the page markup

The Cashier should expose an explicit action to open the Customer Display URL for
the current cart in a new window/tab. Do not require staff to copy internal IDs
manually.

---

# 6. Pairing and Browser Topology

Supported topology:

```text
same origin
same browser storage partition/profile
Cashier tab/window
Customer Display tab/window on second monitor
```

`BroadcastChannel` does not synchronize separate devices, browsers, browser
profiles, or origins.

Phase 06 must communicate this limitation through an actionable unpaired or
unsupported state. Do not silently add a server polling transport to hide the
limitation.

Each screen instance owns a locally generated `source_instance_id` so a sender
can ignore its own messages and diagnostics can distinguish windows.

---

# 7. Channel Contract

Required channel name:

```text
coffeepos:<pos_session_id>
```

Rules:

- never use a global unscoped `coffeepos` channel
- open a channel only after validating `pos_session_id`
- close the previous channel before switching sessions or destroying the screen
- ignore messages whose `pos_session_id` does not match the active channel
- channel identity is not authorization
- one session's state must never appear on another terminal/display

The channel name builder belongs in one shared sync module and must be used by
Cashier and Customer Display.

---

# 8. Message Envelope

Use the documented versioned envelope:

```json
{
  "version": 1,
  "type": "cart.updated",
  "message_id": "uuid",
  "timestamp": "2026-08-23T12:00:00Z",
  "pos_session_id": "01J...",
  "revision": 8,
  "source_instance_id": "cashier-window-uuid",
  "source": "cashier",
  "target": "customer",
  "payload": {}
}
```

Required fields:

```text
version
type
message_id
timestamp
pos_session_id
revision
source_instance_id
source
target
payload
```

Do not allow individual components to construct ad-hoc message shapes.

The shared sync module owns:

```text
channel naming
message ID creation
envelope creation
version/type allow-list
field and payload validation
source/target validation
duplicate tracking
ordering checks
channel lifecycle
```

---

# 9. Message Types

Baseline types are exactly:

```text
display.ready
state.requested
state.snapshot
cart.updated
customer.updated
checkout.started
payment.started
payment.updated
sale.completed
display.reset
```

Do not implement undocumented aliases such as:

```text
cartChanged
paymentSuccess
newOrder
resetScreen
```

If implementation requires `payment.failed`, `cart.cleared`, a session rollover
event, or another semantic type, update `SYNC-API.md` before using it.

---

# 10. Session Handshake

Customer Display must not wait for the next mutation.

Startup flow:

```text
Validate pairing ID
    ↓
Open session-scoped BroadcastChannel
    ↓
Register listener
    ↓
Send display.ready(last_known_revision)
    ↓
Cashier validates request
    ↓
Cashier sends full state.snapshot
    ↓
Display validates and renders snapshot
```

`display.ready` includes the sender's latest known cart revision and does not
mutate application state.

Cashier sends a snapshot when:

```text
display opens with no revision
display reports an older revision
display explicitly sends state.requested
Cashier changes to the paired session/channel
```

The listener must be installed before sending `display.ready` to avoid missing a
fast response.

---

# 11. Full Snapshot

`state.snapshot` contains the full latest customer-facing presentation state:

```json
{
  "screen_state": "cart",
  "cart": {},
  "customer": null,
  "payment": null,
  "order": null
}
```

The final field set must be documented consistently in `SYNC-API.md` before
implementation.

Rules:

- cart data comes from the latest canonical `CartView`
- payment/order fields come from the latest trusted Phase-05 result
- snapshot contains only customer-safe fields
- snapshot does not contain Cashier controls, nonces, cookies, capabilities, or
  provider secrets
- snapshot can hydrate IDLE, CART, CHECKOUT, PAYMENT_PENDING, PAYMENT_SUCCESS,
  or THANK_YOU presentation
- a valid snapshot replaces the complete local presentation projection

---

# 12. Snapshot REST Recovery

Customer Display may recover current cart state with:

```text
GET /coffeepos/v1/cart?pos_session_id={id}
```

REST recovery is used for initial/failure recovery, not continuous cart polling.

Rules:

- the request requires existing WordPress authentication/capability/nonce policy
- unknown or expired session renders a controlled unpaired/expired state
- a REST cart projection cannot invent current payment/order state
- after REST cart recovery, the display sends `state.requested` to obtain the
  complete Cashier presentation state
- if Cashier is absent, the display may retain the recovered cart with a neutral
  reconnecting indicator

Do not add unauthenticated cart access merely because `pos_session_id` is known.

---

# 13. Cart Synchronization

After every successful customer-facing cart mutation, Cashier broadcasts the
same accepted `CartView` it rendered.

Mutations include:

```text
add item
edit item
quantity change
remove item
clear cart
apply/remove coupon
attach/remove customer
order-type/table change
```

Flow:

```text
Cashier mutation request
    ↓
Server validates/persists
    ↓
Canonical CartView + revision
    ↓
Cashier applyCart
    ↓
cart.updated / customer.updated as documented
    ↓
Customer Display renders accepted projection
```

Never broadcast optimistic totals, customer context, or cart contents before the
server accepts the mutation.

Cart synchronization uses events and snapshots, not interval polling.

---

# 14. Customer Projection

Customer Display may show:

```text
display name
membership status/tier when available
points/balance presentation when explicitly supplied
```

Do not show:

```text
email
billing/shipping address
order history
internal customer metadata
staff-only notes
provider errors
```

Guest state uses a neutral customer-facing presentation. The display must work
when membership data is `null` or partially absent.

---

# 15. Service Context Projection

The cart region may show:

```text
Takeaway
Dine-in — {safe table label}
```

The Customer Display receives trusted presentation fields and does not validate
or mutate the table selection.

Table/customer strings render as text, not HTML.

---

# 16. Checkout Synchronization

When the cashier opens/reviews checkout, send:

```text
checkout.started
```

Customer Display moves from CART to CHECKOUT and displays:

```text
current order summary
authoritative amount due
```

Closing checkout or a recoverable checkout/payment failure must return the
display to the latest valid CART or CHECKOUT state according to the documented
state machine. If an additional event is needed for this transition, add it to
`SYNC-API.md` before implementation.

Customer Display must not infer that checkout succeeded from the modal state or
button click alone.

---

# 17. Payment Synchronization

Cashier sends payment presentation only after receiving a Phase-05 result.

Bank-transfer flow:

```text
Server checkout result = pending
    ↓
payment.started
    ↓
Customer Display PAYMENT_PENDING
    ↓
trusted status response
    ↓
payment.updated
    ↓
PAYMENT_SUCCESS only when state = paid
```

Cash flow:

```text
Server checkout result = paid
    ↓
payment.updated / sale.completed as documented
    ↓
PAYMENT_SUCCESS
```

Rules:

- Customer Display never sends payment completion
- QR load/scan is not payment success
- pending, failed, and paid remain distinct
- `paid` is rendered only from a trusted Cashier application result
- out-of-order pending events must not regress an accepted paid state
- payment amount and reference are safe server projections

---

# 18. VietQR Projection

During bank-transfer pending state, display:

```text
authoritative amount
VietQR image/payload
safe transfer content/reference
payment instruction
pending indicator
```

Rules:

- QR corresponds to the current order/payment context
- QR is not reconstructed or modified by Customer Display
- QR URL/payload must pass the shared safe-URL/data policy
- do not expose beneficiary secrets or provider credentials
- missing/unavailable QR shows a controlled provider-unavailable state
- stale QR must be cleared when payment/session context changes

---

# 19. Sale Completion, Thank You, and Reset

Trusted paid flow:

```text
sale.completed
    ↓
PAYMENT_SUCCESS
    ↓
THANK_YOU
    ↓
display.reset
    ↓
IDLE / next paired cart
```

Payment success may show:

```text
success message
paid amount/order total
safe order reference
cash change when appropriate
```

Thank-you duration must be a documented UI setting/default and must not affect
order/payment state.

Reset may occur through:

```text
explicit Cashier new-order action
documented post-success timeout
valid display.reset event
```

A local timer may change presentation from success to thank-you, but it must not
invent a new cart/session or claim a new payment result.

---

# 20. POS Session Rollover

Phase 05 creates a fresh `pos_session_id` after a successful paid checkout. A
Customer Display listening only on the completed cart's channel cannot discover
the next cart without an explicit handoff.

Before implementation, `SYNC-API.md` MUST define the rollover contract. The
recommended flow is:

```text
Cashier completes sale on old session
    ↓
sale.completed on old channel
    ↓
Cashier obtains fresh next_pos_session_id
    ↓
authenticated/validated session handoff on old channel
    ↓
Customer Display closes old channel
    ↓
Customer Display validates and opens new channel
    ↓
display.ready(revision = 0)
    ↓
state.snapshot for fresh cart
```

The handoff may extend `display.reset` or introduce one documented semantic event.
It must include no credential and must be sent only by the paired Cashier source.

Rules:

- never switch channel from an unvalidated arbitrary message
- close the old channel before accepting new incremental state
- reset revision and deduplication state for the new session
- preserve success/thank-you presentation for the documented duration
- stale messages from the completed session cannot overwrite the new session
- a failed handoff leaves a visible reconnect/pairing state

Do not bind Customer Display to one global terminal channel as a shortcut; the
documented architecture requires session-scoped channels.

---

# 21. Ordering Contract

Cart ordering is determined by canonical cart `revision`, not wall-clock time.

Cart rules:

```text
incoming revision < rendered revision  → ignore
incoming revision = rendered revision  → duplicate unless allowed hydration
incoming revision = rendered + 1       → accept
incoming revision > rendered + 1       → request full snapshot
```

`state.snapshot` with the same revision may be accepted during initial hydration
or explicit recovery.

Timestamp is diagnostic only.

---

# 22. Non-Cart Workflow Ordering Gate

Checkout/payment/sale events can change presentation without incrementing the
cart revision. Therefore cart `revision` alone cannot safely order:

```text
checkout.started
payment.started
payment.updated
sale.completed
display.reset
```

Before implementation, update `SYNC-API.md` with one stable ordering rule for
non-cart workflow events. An acceptable contract may add a monotonic
session-scoped `sequence`/`sync_revision` while retaining `revision` as the
canonical cart revision, or define another equally deterministic server-confirmed
ordering field.

The chosen contract must:

- distinguish cart revision from presentation workflow ordering
- prevent pending/failed events from regressing paid/completed state
- support duplicate rejection and snapshot recovery
- reset safely on `pos_session_id` rollover
- not mutate the cart merely to sequence payment presentation
- be documented and reused by both screens

Do not silently treat timestamps or message arrival order as authority.

---

# 23. Duplicate Messages

Use `message_id` and ordering fields to reject duplicates.

Rules:

- maintain a bounded local deduplication cache per active session
- receiving a duplicate has no visible side effect
- clear the cache on validated session rollover
- do not allow unbounded message IDs to accumulate
- duplicate `display.ready` may receive another safe snapshot
- duplicate paid/sale events must not restart timers indefinitely

---

# 24. Message Validation

Customer Display validates in this order:

```text
message is a plain object
    ↓
supported version
    ↓
expected pos_session_id
    ↓
allowed source and target
    ↓
known type
    ↓
required envelope fields
    ↓
message_id duplicate check
    ↓
revision/workflow ordering
    ↓
type-specific payload schema
    ↓
render projection
```

Malformed input is ignored safely and may be logged without sensitive payloads.

`target` must be `customer` or `all`. Valid target alone does not bypass session
or source validation.

---

# 25. Customer Display State Machine

Use the documented presentation states:

```text
IDLE
  ↓
CART
  ↓
CHECKOUT
  ↓
PAYMENT_PENDING
  ↓
PAYMENT_SUCCESS
  ↓
THANK_YOU
  ↓
IDLE
```

Failure branch:

```text
PAYMENT_PENDING
  ↓
PAYMENT_FAILED
  ↓
CHECKOUT
```

Connection/UI states such as `unpaired`, `loading`, `reconnecting`, and
`unsupported` wrap the presentation state; they are not new payment/order states.

Do not invent business states in JavaScript.

---

# 26. Read-Only Catalog Menu

Both screens consume:

```text
GET /coffeepos/v1/catalog
```

Customer Display renders the same category/product projection through its own
read-only PHP templates.

Product rows show:

```text
product name
optional projection-provided badge
WooCommerce-formatted price
```

Rules:

- categories preserve deterministic WooCommerce/menu order
- products preserve projection order and occurrence identity
- products not purchasable or not in stock are hidden on Customer Display
- categories empty after that presentation rule are omitted
- menu has no product/select/cart action
- Customer Display does not regroup products or calculate/format canonical price
- optional badges come from `CatalogView`, not hard-coded JavaScript
- catalog is loaded once at startup and may use documented refresh behavior

---

# 27. Catalog and Sync Independence

Startup loads independently:

```text
GET /catalog             → read-only menu
sync handshake/Cart GET  → realtime cart/payment region
```

Rules:

- run independent startup requests in parallel where practical
- catalog failure does not stop cart/payment synchronization
- sync failure does not erase a valid catalog
- a successful catalog refresh swaps the complete projection atomically
- a stale menu is acceptable for viewing; server remains authoritative elsewhere

---

# 28. Layout

Landscape composition:

```text
Customer Display
├── Menu Region (approximately 68–72%)
│   ├── Brand/Header
│   ├── Category Jump Navigation
│   └── Category Section Grid (2–3 columns)
│       └── Compact Read-only Product Rows
└── Realtime Cart Region (approximately 28–32%)
    ├── Customer/Service Summary
    ├── Scrollable Cart Items
    ├── Totals
    └── Payment / Thank-you Region
```

Rules:

- menu and cart items scroll independently
- cart total/payment area remains visible
- readability is suitable from several meters away
- layout reduces columns gracefully on narrower screens
- realtime cart retains priority without overlapping menu content
- empty/loading states preserve stable dimensions
- no Cashier controls appear on Customer Display

---

# 29. Category Navigation

Customer Display may provide compact category jump navigation.

Category navigation:

- scrolls to an existing Customer Display category section
- does not filter or rebuild the menu
- does not issue another product/catalog request
- preserves product rows and cart state
- uses stable category IDs from `CatalogView`

Customer Display does not need Cashier product search or product configuration.

---

# 30. Cart Region

Cart state displays:

```text
item name
variation/configuration summary when customer-safe
quantity
unit price
line total
subtotal
discount
total
customer/member summary where applicable
order type/table where appropriate
```

The projection comes directly from accepted server-confirmed state.

Do not expose staff-only notes. Customer-visible note/modifier policy must follow
the projection contract; do not broadcast all order-item metadata by default.

---

# 31. Loading, Empty, and Failure States

Catalog states:

```text
loading
normal
empty
refreshing
error
```

Sync/display states:

```text
unpaired
connecting
hydrating
connected
reconnecting
expired
unsupported
error
```

Rules:

- keep the last valid projection during transient reconnect
- show a neutral connection indicator
- never clear paid/pending state because one message fails validation
- invalid session does not create a cart automatically
- unsupported BroadcastChannel shows an actionable topology message
- retry performs handshake/recovery, not a business mutation
- failures do not expose stack traces, nonces, or provider internals

---

# 32. PHP Templates

Create only templates actually used.

Expected conceptual structure:

```text
templates/customer/
├── content.php
├── menu.php
├── cart.php
├── payment.php
└── states.php

templates/components/
├── customer-display-category.php
├── customer-display-product-row.php
└── customer-display-cart-item.php
```

Required named native templates should include the documented equivalents of:

```text
coffeepos-customer-category-template
coffeepos-customer-product-row-template
coffeepos-customer-cart-item-template
```

Repeated/AJAX-driven rows use native `<template>` markup with:

```text
data-field
data-attr
data-key
```

JavaScript must not create application HTML through template strings.

---

# 33. Stable UI Hooks

Expected hooks:

```text
data-component="customer-display"
data-component="customer-menu"
data-component="customer-category-nav"
data-component="catalog-scroll"
data-component="catalog-category-section"
data-component="catalog-category-products"
data-component="customer-cart"
data-component="customer-cart-items"
data-component="customer-totals"
data-component="customer-payment"
data-component="customer-thank-you"
data-component="customer-connection"
data-category-id
data-product-id
data-occurrence-key
data-action="scroll-category"
data-action="retry-display-sync"
```

Before changing shared catalog hooks, search Cashier, Customer Display, CSS,
templates, and tests.

Read-only product/cart rows must not expose mutation hooks such as:

```text
data-action="add-to-cart"
data-action="increase-quantity"
data-action="remove-cart-item"
```

---

# 34. JavaScript Modules

Suggested module graph:

```text
assets/js/sync/
├── protocol.js
└── channel.js

assets/js/components/
├── customer-catalog.js
├── customer-cart.js
└── customer-payment.js

assets/js/state/
└── customer-display-store.js

assets/js/screens/
└── customer.js
```

Cashier extends its existing controller with one sync publisher/bridge. Do not
create a second Cashier controller or duplicate cart mutation logic.

Shared sync modules contain no screen markup or business rules.

---

# 35. Customer Display Client State

The client may maintain:

```text
active pos_session_id
source_instance_id
channel lifecycle state
latest accepted cart revision
latest accepted workflow ordering value
bounded message-id cache
latest full presentation snapshot
current Customer Display UI state
catalog projection/status
connection/retry state
thank-you timer
```

The client must not maintain authoritative:

```text
cart contents
prices/totals
stock
coupon eligibility
customer identity
payment success
order status
session handoff without Cashier validation
```

Local browser state may cache presentation/recovery hints but remains disposable.

---

# 36. Cashier Publisher Integration

Cashier must publish from centralized accepted-state points.

Required integration points:

```text
initial cart load/recovery
applyCart after successful mutation
customer/service context update
checkout opened/validated
checkout result
payment status result
sale completion
new-order/session rollover
explicit display reset
```

Rules:

- publish the same projection Cashier accepted/rendered
- never patch a different customer-facing cart independently
- opening the channel does not create/mutate a cart
- one successful mutation produces one semantic accepted-state broadcast
- publisher responds to valid `display.ready` and `state.requested`
- publisher ignores foreign source/target/session messages
- publisher closes/rebinds channel when the active `pos_session_id` changes

---

# 37. Race Conditions

Handle at minimum:

```text
Customer Display opens before Cashier listener is ready
Cashier opens before Customer Display
display.ready sent repeatedly
cart mutation occurs during handshake
incremental event arrives before initial snapshot
snapshot and newer cart event cross in transit
two rapid cart mutations
payment pending and paid responses resolve out of order
duplicate sale.completed
thank-you timer fires after session rollover
old-channel message arrives after new-channel binding
display reloads during pending payment
Cashier reloads while display remains open
```

Use listener-before-ready setup, ordering fields, snapshot replacement, bounded
deduplication, generation/session tokens, and cancelable timers where practical.

---

# 38. Security

Customer Display route and REST recovery require documented authentication and
CoffeePOS capabilities.

Never broadcast:

```text
WordPress authentication cookies
WooCommerce session token
REST nonce
provider secret/key/signature
full customer record
customer email/address/order history
staff capability or admin data
internal stack/error details
```

Rules:

- `pos_session_id` is not treated as authorization
- every message validates session, source, target, version, type, and payload
- foreign-session data never renders briefly before rejection
- text fields render via `textContent`/safe TemplateRenderer bindings
- QR/image URLs use the approved URL validator
- Customer Display cannot invoke cart/payment mutation through sync messages
- REST endpoints retain capability, nonce, validation, and output rules

---

# 39. Database and Persistence Rules

No new custom database table is allowed in Phase 06.

Authoritative data remains:

```text
catalog/products/prices/stock → WooCommerce
active cart/revision          → WooCommerce session keyed by pos_session_id
order/payment                 → WooCommerce + approved payment integration
Customer Display             → disposable presentation projection
```

BroadcastChannel messages are not persisted as a business event log.

Do not create:

```text
customer display cart table
sync message table
payment mirror
browser-authoritative order/cart state
WordPress option containing active cart snapshots
```

If a new setting is required for display enablement, thank-you timeout, or
presentation behavior, document its exact key and ownership in `DATABASE.md`
before implementation.

---

# 40. Acceptance Criteria

Phase 06 is complete when:

## Route and Pairing

1. `/pos/customer` renders a dedicated Customer Display shell.
2. The route remains protected by the documented CoffeePOS capability policy.
3. Cashier can open a display paired to its current `pos_session_id`.
4. Missing or malformed pairing ID renders a controlled unpaired state.
5. Pairing input never exposes WooCommerce session/auth/nonce data.
6. The display never creates a new cart merely because pairing fails.
7. Unsupported cross-device/profile topology is communicated clearly.

## Shared Catalog

8. Customer Display loads `GET /catalog` independently.
9. It consumes the same `CatalogView` contract as Cashier.
10. Categories and products preserve deterministic projection order.
11. Customer Display owns separate PHP category/product templates.
12. Product rows show name, optional badge, and WooCommerce-formatted price.
13. Unavailable/non-purchasable products are hidden only in display presentation.
14. Categories empty after hiding unavailable products are omitted.
15. Product rows expose no cart mutation action.
16. Category navigation scrolls without refetching/filtering the menu.
17. Catalog failure does not stop realtime synchronization.

## Layout and Rendering

18. Landscape layout keeps menu and realtime cart visible side by side.
19. Menu and cart items have independent scroll regions.
20. Totals/payment presentation remains visible while cart items scroll.
21. Narrower layouts degrade without overlap or hidden critical totals.
22. Dynamic rows render from PHP-owned native templates.
23. Projection text/URLs are bound safely and never interpreted as arbitrary HTML.
24. No Cashier/operator controls appear on Customer Display.

## Channel and Protocol

25. Channel name is exactly scoped to `coffeepos:<pos_session_id>`.
26. Cashier and Customer Display use one shared protocol implementation.
27. Every message uses the documented versioned envelope.
28. Unknown versions/types and malformed payloads are ignored safely.
29. Foreign-session and wrong-target messages never update the UI.
30. Each window has a stable local `source_instance_id`.
31. Duplicate messages are idempotent and deduplication is bounded.
32. Old channels/listeners are closed during session change/destroy.

## Handshake and Recovery

33. Display listener is active before `display.ready` is sent.
34. A display opened after cart changes receives a full current snapshot.
35. Valid `state.requested` receives a full snapshot from Cashier.
36. Same-revision snapshot is accepted only for hydration/recovery.
37. Lower cart revisions are ignored.
38. A cart revision gap triggers snapshot recovery.
39. REST recovery can reload the canonical cart without continuous polling.
40. Transient failure keeps the last valid projection and shows reconnecting.
41. Cashier reload/display reload can recover through a new handshake.

## Cart, Customer, and Service State

42. Every accepted customer-facing cart mutation is broadcast immediately.
43. Customer Display renders the exact accepted Cashier cart revision.
44. Items, quantities, unit prices, line totals, subtotal, discount, and total sync.
45. Customer/member presentation updates with customer context.
46. Unnecessary customer personal data is absent.
47. Order type/table presentation updates from trusted cart context.
48. Customer Display never calculates or mutates price/cart/customer/service data.

## Checkout and Payment

49. Checkout state shows the authoritative amount due.
50. Pending bank transfer displays amount, safe reference, VietQR, and pending state.
51. QR corresponds to the current server-confirmed payment context.
52. QR display/scan alone never produces payment success.
53. Cash success is shown only from the trusted paid checkout result.
54. Bank success is shown only from trusted provider-verified paid state.
55. Out-of-order pending/failed events cannot regress a paid/completed display.
56. Customer Display cannot confirm payment or create an order.

## Completion and Rollover

57. `sale.completed` shows a safe order/payment success projection.
58. Payment success transitions to thank-you according to documented timing.
59. Valid reset returns the display to idle/fresh-cart state.
60. Duplicate completion/reset events do not restart state incorrectly.
61. Fresh Phase-05 `pos_session_id` is handed off through a documented contract.
62. Display closes old channel and handshakes on the new channel.
63. Old-session messages cannot overwrite the new session.
64. Failed rollover produces a recoverable pairing/reconnect state.

## Scope, Security, and Storage

65. Non-cart workflow ordering is documented separately from cart revision.
66. No timestamp/arrival-order rule decides authoritative state.
67. No credentials, provider secrets, or staff/admin data are broadcast.
68. Customer Display route/REST calls retain capability and nonce protections.
69. BroadcastChannel messages cannot invoke server-side business mutations.
70. No new custom database table or business-state mirror is created.
71. Customer Display uses no cart polling during normal connected operation.
72. KDS, Queue, shifts, history, refunds, and reports remain out of scope.

---

# 41. Required Test Cases

## Route and Pairing

```text
TC-01 authorized /pos/customer shell
TC-02 unauthorized route rejected
TC-03 valid pos_session_id pairing
TC-04 missing pairing ID
TC-05 malformed pairing ID
TC-06 unknown/expired paired cart
TC-07 pairing URL contains no auth/session/nonce credential
TC-08 unsupported BroadcastChannel/topology state
```

## Catalog and Templates

```text
TC-09 shared CatalogView loads on Customer Display
TC-10 deterministic category/product order
TC-11 unavailable products hidden from Customer Display only
TC-12 empty post-filter categories omitted
TC-13 product row exposes no cart mutation action
TC-14 category jump performs no catalog request
TC-15 catalog loading/empty/error/retry
TC-16 catalog error does not block sync
TC-17 customer category/product rows use PHP-owned templates
TC-18 markup-like names/badges render as text
TC-19 unsafe product/QR URL rejected
```

## Protocol and Isolation

```text
TC-20 exact session-scoped channel name
TC-21 valid envelope accepted
TC-22 unsupported version ignored
TC-23 unknown type ignored
TC-24 malformed envelope/payload ignored
TC-25 foreign pos_session_id ignored
TC-26 wrong target/source ignored
TC-27 own-source message ignored where applicable
TC-28 duplicate message ignored
TC-29 bounded deduplication cache
TC-30 old channel closed on rebind
```

## Handshake and Recovery

```text
TC-31 listener registered before display.ready
TC-32 late-open display receives state.snapshot
TC-33 repeated display.ready safely receives snapshot
TC-34 state.requested receives latest snapshot
TC-35 snapshot during concurrent cart mutation resolves to newest revision
TC-36 lower revision ignored
TC-37 duplicate revision ignored
TC-38 same-revision hydration snapshot accepted
TC-39 revision gap requests snapshot
TC-40 incremental event waits for recovery snapshot after gap
TC-41 REST cart recovery
TC-42 expired cart recovery state
TC-43 Cashier reload re-handshake
TC-44 Customer Display reload re-handshake
```

## Realtime Cart and Context

```text
TC-45 add item sync
TC-46 edit/quantity sync
TC-47 remove item sync
TC-48 clear cart sync
TC-49 coupon apply/remove sync
TC-50 customer attach/remove sync
TC-51 order type/table sync
TC-52 server-rejected mutation does not broadcast optimistic state
TC-53 Cashier/display render same cart revision and totals
TC-54 separate POS sessions never share projection
TC-55 sensitive customer/staff fields absent
```

## Checkout and Payment

```text
TC-56 checkout.started shows authoritative amount
TC-57 bank payment.started shows pending
TC-58 VietQR matches payment projection
TC-59 QR display does not mark paid
TC-60 provider-unavailable state
TC-61 trusted cash paid event
TC-62 trusted bank paid event
TC-63 untrusted paid message rejected by source/contract
TC-64 pending event cannot regress paid
TC-65 duplicate paid event is idempotent
TC-66 payment failure returns to documented presentation state
TC-67 Customer Display has no payment mutation path
```

## Completion and Session Rollover

```text
TC-68 sale.completed success projection
TC-69 success to thank-you timing
TC-70 display.reset to idle
TC-71 duplicate completion/reset timer safety
TC-72 old-to-new pos_session_id handoff
TC-73 old channel closes before new incremental state
TC-74 new channel starts revision/order tracking cleanly
TC-75 stale old-session event ignored after rollover
TC-76 failed rollover exposes reconnect action
TC-77 new session display.ready receives empty/fresh snapshot
```

## Failure, Security, and Scope

```text
TC-78 BroadcastChannel unavailable
TC-79 channel construction/postMessage failure
TC-80 transient disconnect keeps last valid state
TC-81 no nonce/cookie/session token/provider secret in messages
TC-82 unauthorized REST recovery rejected
TC-83 malformed text/URL projection safely rendered
TC-84 normal cart sync uses no polling timer
TC-85 no custom display/sync database table
TC-86 no KDS/Queue/shift/history/report implementation
```

---

# 42. Browser Verification

Phase 06 is a multi-window UI/synchronization phase and must be verified in a
real WordPress/WooCommerce browser environment.

Minimum browser checks:

```text
Open Cashier and Customer Display on same origin/profile
Open display before and after cart already contains items
Missing/invalid/expired pairing URL
Catalog and sync loading in parallel
Landscape two-region layout
Independent menu/cart scrolling
Category jump navigation
Unavailable-product display policy
Add/edit/quantity/remove/clear cart realtime sync
Coupon totals sync
Customer/member sync
Dine-in/table and takeaway sync
Cash checkout success
Bank-transfer pending and VietQR
Provider unavailable
Trusted bank success when configured
Success → thank-you → reset
Fresh-cart session/channel rollover
Reload Cashier while display remains open
Reload display while Cashier remains open
Revision-gap recovery
Duplicate/stale/foreign-session message rejection
BroadcastChannel unsupported/failure state
Responsive/narrow landscape behavior
Focus, reduced motion, and readable contrast/text size
```

Do not claim these passed unless they were actually tested in a browser.

---

# 43. Documentation Gate

Before implementing Phase 06, resolve and document:

```text
final state.snapshot projection fields
customer-safe cart/item/customer/payment projections
Cashier action and pairing URL contract
non-cart workflow ordering field/rules
payment failure/cancel presentation event when needed
old-to-new pos_session_id rollover event/payload
thank-you/reset timeout setting/default
QR image/payload URL safety contract
Customer Display REST recovery authorization behavior
new stable sync validation/error states
```

At minimum, update `SYNC-API.md` for workflow ordering and session rollover before
writing the synchronization implementation. Update `DATABASE.md` if a new setting
key is introduced.

If those two contracts remain unresolved, do not improvise with timestamps, cart
revision increments for payment UI, a global terminal channel, or automatic
unvalidated session switching.

---

# 44. Definition of Done

Phase 06 is complete only when:

```text
Shared WooCommerce CatalogView
        ↓
Customer Display Read-only PHP Templates

Cashier Server-confirmed State
        ↓
Session-scoped BroadcastChannel
        ↓
Ready / Request / Snapshot Recovery
        ↓
Ordered Cart / Checkout / Payment Projection
        ↓
Trusted Success / Thank You / Reset
        ↓
Validated Fresh-session Channel Rollover
```

works end-to-end without Customer Display mutating business state, calculating
authoritative values, receiving another terminal's cart, or depending on cart
polling during normal connected operation.

---

# 45. Final Phase 06 Rule

When Phase 06 is complete:

STOP.

Do not automatically implement Phase 07.

Phase 07 will explicitly introduce:

```text
KDS screen and polling
new/preparing/ready operational states
sound/timer warnings
Order Queue
complete/cancel/reprint actions
WooCommerce order projections for operations
```

Customer Display synchronization must remain isolated from the KDS/Order Queue
transport and operational state unless a later architecture decision explicitly
connects them.
