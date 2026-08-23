# PHASE-07.md

# CoffeePOS Phase 07 — KDS & Order Queue

## 1. Objective

Provide the operational screens that receive paid CoffeePOS orders, guide
kitchen/preparation work, and expose the active serving queue without creating
a second order system.

Primary workflows:

```text
Paid CoffeePOS WooCommerce Order
    ↓
KDS Operational Projection
    ↓
NEW → PREPARING → READY → COMPLETED
    ↓
Order Queue Active Projection
    ↓
Complete / Cancel / Reprint
```

At the end of this phase:

- `/pos/kds` is a dedicated preparation screen
- `/pos/order-queue` is a dedicated active-order screen
- both screens read authorized WooCommerce order projections
- newly paid CoffeePOS orders appear through non-overlapping polling
- KDS cards expose item, variation, modifier, quick-note, and custom-note detail
- elapsed time updates locally from a server-derived clock anchor
- 5-minute warning and 10-minute critical severity are deterministic
- sound is opt-in and fires only for newly detected orders after initial load
- preparation transitions are validated and persisted server-side
- complete and cancel operations use WooCommerce CRUD and shared application
  services
- receipt reprint reuses the Phase-05 receipt projection and PHP template
- retry, duplicate click, stale screen, and partial refresh cases are controlled
- Shift Management, Order History, refunds, quick reorder, and reports remain out
  of scope

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
docs/03-ui/KDS-UI.md

docs/04-api/API-ARCHITECTURE.md
docs/04-api/ORDER-API.md

docs/05-phases/PHASE-01.md
docs/05-phases/PHASE-02.md
docs/05-phases/PHASE-03.md
docs/05-phases/PHASE-04.md
docs/05-phases/PHASE-05.md
docs/05-phases/PHASE-06.md
```

Codex MUST inspect and preserve:

```text
POS routing and capability checks
WooCommerce order creation and HPOS-compatible CRUD
Phase-05 order/item metadata
Phase-05 receipt projection and print flow
shared TemplateRenderer contract
shared REST client and error envelope
existing full-screen asset loading
Customer Display BroadcastChannel isolation
```

Phase 07 does not replace or couple itself to the Phase-06 Customer Display
transport. KDS and Order Queue synchronize with the server by documented polling.

---

# 3. Scope

## In Scope

```text
KDS route and full-screen shell
Order Queue route and full-screen shell
Authorized operational order query
KDSOrderView projection
OrderQueueView projection
WooCommerce order-item detail projection
CoffeePOS KDS workflow state
KDS transition validation
KDS transition revision/concurrency guard
Received/started/ready/completed timestamps
NEW/PREPARING/READY/COMPLETED presentation
Cancellation branch where state policy permits
Five-second baseline polling
Non-overlapping polling controller
Visibility-aware refresh behavior
Manual refresh/retry
New-order detection
Opt-in sound notification
Per-browser sound preference
Local one-second elapsed timer
Five-minute warning severity
Ten-minute critical severity
KDS status filters
KDS order cards
Order Queue active list
Order Queue status/service filters
Order completion
Order cancellation
Receipt reprint
Pending, stale, empty, partial-error, and offline states
PHP-owned screen and repeated-item templates
Vanilla JavaScript controllers and stores
WooCommerce CRUD/HPOS-compatible persistence
Settings-backed polling intervals
Phase-07 automated and browser tests
```

## Out of Scope

Do NOT implement:

```text
Shift open/close or cash reconciliation
Order History screen
Historical date-range browsing
Refunds or partial refunds
Quick reorder
Reports or exports
Product/cart editing from KDS or Order Queue
Payment changes or manual payment completion
Customer Display synchronization changes
BroadcastChannel transport for KDS/Queue
WebSocket, SSE, service worker, or push infrastructure
Per-kitchen station routing
Multiple preparation courses
Kitchen printer routing
Inventory adjustments
Delivery dispatch
Table occupancy/reservation management
Custom WooCommerce statuses for KDS states
A duplicate CoffeePOS order table
A duplicate active-order queue table
A custom KDS event-log table
Arbitrary order-item completion state
Background audio before user interaction
```

---

# 4. Architecture

```text
WooCommerce Orders + Order Items
        ↓
Operational Order Query Gateway
        ↓
KDS / Queue Application Services
        ↓
KDSOrderView / OrderQueueView
        ↓
Protected CoffeePOS REST Endpoints
        ↓
Polling Stores
        ↓
PHP-owned Templates + TemplateRenderer
```

Mutation flow:

```text
KDS / Queue Action
        ↓
Capability + Nonce + Input Validation
        ↓
Load WooCommerce Order through CRUD
        ↓
Verify CoffeePOS Eligibility + Expected KDS Revision
        ↓
Validate State Transition
        ↓
Persist Operational Metadata / WooCommerce Status
        ↓
Return Canonical Projection
        ↓
Replace Affected Card
```

Rules:

- WooCommerce remains the canonical order and order-item system
- KDS state is CoffeePOS operational context attached to the order
- Order Queue is a query/projection, not stored queue data
- the UI requests transitions but never owns transition authority
- KDS and Queue reuse one operational service for shared actions
- list responses contain DTO/view data, never serialized WooCommerce objects
- REST responses contain JSON projections, never rendered HTML fragments
- PHP owns application and repeated-card markup
- JavaScript owns polling, interaction, clocks, and targeted rendering
- no Phase-06 cart/payment BroadcastChannel message is reused as KDS authority

---

# 5. Routes and Access

Use the existing major-screen routes:

```text
/pos/kds
/pos/order-queue
```

Rules:

- both routes use the configured POS base slug
- both require the existing CoffeePOS POS-access capability policy
- unauthorized access returns the existing controlled 403 screen
- route rendering must not query or mutate orders before capability validation
- KDS and Order Queue own independent PHP content templates and screen
  controllers
- admin bar remains hidden only because these are POS routes
- a missing WooCommerce dependency renders the established controlled
  unavailable state, not a fatal error

Phase 07 does not introduce a public kitchen URL, access token in the URL, or
unauthenticated operational endpoints.

---

# 6. Eligible Orders

An order is eligible for Phase-07 operational projections only when all are
true:

```text
WooCommerce order exists
created_via = coffeepos OR _coffeepos_pos_session_id is present
payment is paid according to WooCommerce
order is not refunded, failed, trash, or draft
```

Do not infer eligibility from order number, customer, payment method label, or
browser state.

KDS active states are:

```text
new
preparing
ready
```

Order Queue active orders are eligible orders that are not operationally
completed and whose WooCommerce status is not cancelled, refunded, failed, or
trash.

An order that becomes ineligible between polls is removed from the active
projection on the next accepted response.

---

# 7. KDS Workflow State

Authoritative CoffeePOS KDS states are exactly:

```text
new
preparing
ready
completed
cancelled
```

Allowed forward transitions:

```text
new       → preparing
preparing → ready
ready     → completed
```

Allowed cancellation transitions:

```text
new       → cancelled
preparing → cancelled
```

Invalid transitions include:

```text
new → ready
new → completed
preparing → completed
ready → preparing
ready → cancelled
completed → any active state
cancelled → any active state
```

The server rejects invalid transitions with `invalid_order_state` and returns
the current safe projection in error details where practical.

KDS display severity is derived, not persisted:

```text
elapsed < 5 minutes       → normal
5 minutes to < 10 minutes → warning
10 minutes or more        → critical
```

---

# 8. WooCommerce Status Mapping

Do not register custom WooCommerce statuses for `new`, `preparing`, or `ready`.

Mapping rules:

- `new`, `preparing`, and `ready` remain CoffeePOS operational metadata
- the WooCommerce order remains in its paid active status during preparation
- transition to `completed` uses the supported WooCommerce order CRUD/status
  operation and sets WooCommerce status to `completed`
- transition to `cancelled` uses the supported WooCommerce cancellation/status
  operation and sets WooCommerce status to `cancelled`
- externally completed WooCommerce orders project as KDS `completed`
- externally cancelled WooCommerce orders project as KDS `cancelled`
- refunded/failed orders never remain active KDS or Queue cards

When WooCommerce status and CoffeePOS KDS metadata conflict, terminal
WooCommerce status wins for projection safety. The implementation must not
silently reopen a terminal order.

---

# 9. Operational Persistence

Phase 07 persists KDS workflow context as WooCommerce order metadata through
WooCommerce CRUD APIs. Before implementation, these keys MUST also be added to
`DATABASE.md`:

```text
_coffeepos_kds_state
_coffeepos_kds_revision
_coffeepos_kds_received_at
_coffeepos_kds_started_at
_coffeepos_kds_ready_at
_coffeepos_kds_completed_at
_coffeepos_kds_cancelled_at
_coffeepos_kds_operations
```

Rules:

- state values use the exact lowercase allow-list from Section 7
- revision is a non-negative monotonic integer
- timestamps use UTC ISO-8601 values
- `received_at` is set when a paid CoffeePOS order becomes operationally
  eligible
- `started_at`, `ready_at`, and `completed_at` are written once on their
  corresponding successful transition
- `cancelled_at` is written once on an accepted cancellation
- retries must not overwrite an earlier transition timestamp
- `_coffeepos_kds_operations` is a bounded JSON idempotency ledger, not a second
  order/event store; retain at most the eight most recent successful operations
- each ledger entry contains only `operation_id`, request fingerprint,
  `target_state`, resulting revision, and UTC completion time
- sanitize and validate a decoded ledger; malformed legacy data is treated as
  empty rather than executed or rendered
- no operational transaction data is stored in WordPress options
- no custom table is introduced in Phase 07
- never use direct `wp_postmeta` SQL because HPOS may be active

Phase-05 order creation should initialize:

```text
state = new
revision = 0
received_at = paid/order creation UTC time
```

For eligible pre-Phase-07 CoffeePOS orders with missing KDS metadata, projection
uses a compatibility fallback:

```text
state = new
revision = 0
received_at = WooCommerce paid date, else created date
```

The list query must not write fallback metadata. The first accepted transition
may persist the missing baseline and requested next state in one save.

---

# 10. Concurrency and Idempotency

Every KDS/Queue mutation includes:

```json
{
  "expected_state": "preparing",
  "expected_revision": 4,
  "target_state": "ready",
  "client_operation_id": "kds-uuid"
}
```

Rules:

- the server reloads the WooCommerce order before validation
- state and revision must match the current operational projection
- a successful transition increments `_coffeepos_kds_revision` exactly once
- stale state/revision returns HTTP 409 with `order_state_conflict`
- the response includes the current projection where safe
- repeated delivery of an already accepted operation ID returns the accepted
  result or an idempotent current result without applying the transition twice
- the same operation ID with different material input returns
  `duplicate_operation_conflict`
- pending UI disables only the affected card/action
- two screens racing on one order cannot both advance from the same revision

Do not use timestamps, list poll sequence, or client clocks as a concurrency
substitute.

---

# 11. KDS List API

Required route:

```text
GET /coffeepos/v1/kds/orders
```

Supported query parameters:

```text
states[] = new|preparing|ready
limit
```

Baseline behavior:

- default states are all active KDS states
- default limit is 100 and maximum limit is 200
- sort by `received_at` ascending, then numeric order ID ascending
- use WooCommerce order queries/CRUD and remain HPOS compatible
- return server time and the effective polling interval
- do not include completed/cancelled/refunded/failed orders in the active list
- do not expose billing phone, address, email, internal notes, or payment secrets

Conceptual response:

```json
{
  "success": true,
  "data": {
    "server_time": "2026-08-23T08:00:00Z",
    "poll_interval_ms": 5000,
    "orders": []
  }
}
```

---

# 12. KDS Order Projection

`KDSOrderView` contains only preparation-safe data:

```json
{
  "id": 1001,
  "number": "1001",
  "received_at": "2026-08-23T07:55:00Z",
  "service": {
    "order_type": "dine_in",
    "table_label": "B4"
  },
  "kds": {
    "state": "new",
    "revision": 0,
    "started_at": null,
    "ready_at": null,
    "completed_at": null
  },
  "items": []
}
```

Each KDS item contains:

```text
order_item_id
product_name
quantity
variation_summary
modifier_summary
quick_note_summary
custom_note
```

Projection rules:

- product/order-item names come from the saved WooCommerce order item
- variations come from the saved WooCommerce variation/order-item metadata
- CoffeePOS modifiers decode `_coffeepos_modifiers`
- quick notes decode `_coffeepos_quick_notes`
- custom note reads `_coffeepos_note`
- invalid legacy JSON becomes an empty optional field, not a fatal error
- all display fields are plain text
- the client must use `textContent`/TemplateRenderer safe text binding
- KDS does not need customer phone, billing address, price, payment reference, or
  cashier secrets

---

# 13. KDS Transition API

Required route:

```text
POST /coffeepos/v1/kds/orders/{id}/transition
```

Request:

```json
{
  "expected_state": "new",
  "expected_revision": 0,
  "target_state": "preparing",
  "client_operation_id": "kds-20260823-uuid"
}
```

Server flow:

```text
Authenticate and authorize
    ↓
Validate order ID and payload
    ↓
Load WooCommerce order
    ↓
Verify CoffeePOS eligibility and paid state
    ↓
Resolve terminal WooCommerce status
    ↓
Verify expected state/revision
    ↓
Validate allowed transition
    ↓
Write state/timestamp/revision with WooCommerce CRUD
    ↓
Return canonical KDSOrderView
```

For `completed` and `cancelled`, the transition service also applies the
WooCommerce terminal status described in Section 8.

---

# 14. Order Queue List API

Required route:

```text
GET /coffeepos/v1/order-queue/orders
```

Supported query parameters:

```text
kds_state = all|new|preparing|ready
order_type = all|dine_in|takeaway
limit
```

Rules:

- return only active eligible CoffeePOS orders
- sort oldest received order first
- filters are allow-listed and sanitized server-side
- default limit is 100 and maximum limit is 200
- response includes `server_time` and effective `poll_interval_ms`
- Queue projection is queried from WooCommerce; it is never hydrated from the
  KDS DOM/store
- one polling failure does not erase the last accepted Queue list

---

# 15. Order Queue Projection

`OrderQueueView` contains:

```text
id
number
created_at
received_at
customer.display_name
customer.is_guest
service.order_type
service.table_label
total.amount
total.currency
total.display
woocommerce_status
kds.state
kds.revision
receipt.available
allowed_actions
```

Rules:

- customer projection exposes display name only; do not expose phone/address
- total is derived from the saved WooCommerce order
- table/order type use the documented Phase-05 metadata
- `allowed_actions` is calculated server-side from current state/capability
- UI hiding is not authorization; every action is revalidated server-side
- no raw order/customer objects are serialized

---

# 16. Shared Complete Operation

KDS `ready → completed` and Order Queue **Complete** use the same application
service and transition contract.

Required Queue route may delegate to that service:

```text
POST /coffeepos/v1/orders/{id}/complete
```

The request includes the expected KDS state/revision and a client operation ID.

Server must:

- require current KDS state `ready`
- reject already cancelled/refunded/failed orders
- update KDS state/timestamp/revision
- update WooCommerce status to `completed`
- use WooCommerce CRUD
- return the canonical terminal projection
- handle an identical retry idempotently

Completing an order must not change payment totals, payment method, stock, cart,
customer, or receipt contents.

---

# 17. Shared Cancel Operation

Required route:

```text
POST /coffeepos/v1/orders/{id}/cancel
```

The request includes:

```text
expected_state
expected_revision
client_operation_id
optional sanitized reason
```

Server must:

- allow cancellation only from `new` or `preparing`
- reject `ready`, `completed`, cancelled, refunded, failed, or trash orders
- apply WooCommerce cancellation through CRUD/status APIs
- set KDS state `cancelled`
- set/increment the operational revision
- add an order note when a non-empty reason is supplied
- never treat cancellation as a refund
- return a canonical terminal result
- prevent repeated destructive side effects

Phase 07 does not implement refunding paid funds. The UI must clearly label this
as order cancellation, not payment refund.

---

# 18. Receipt Reprint

Order Queue reprint uses the existing protected route:

```text
GET /coffeepos/v1/orders/{id}/receipt
```

Rules:

- receipt data remains derived from the saved WooCommerce order
- reuse the Phase-05 receipt PHP template and renderer contract
- do not duplicate receipt pricing or order-item parsing in Queue JavaScript
- only an available receipt exposes the reprint action
- load failure keeps the order card and exposes retry feedback
- printing must not mutate order or KDS state

---

# 19. Polling Contract

The feature baseline is one request every 5 seconds per operational screen.

Required behavior:

```text
Request starts
    ↓
Wait for success/failure
    ↓
Apply accepted response
    ↓
Schedule next request after effective interval
```

Rules:

- never use `setInterval` to create overlapping requests
- at most one list request is active per screen
- a manual refresh aborts or waits for the current request before starting one
- a filter change invalidates the older response through request sequencing or
  cancellation
- a late response from an older filter must not replace newer state
- polling stops when the controller is destroyed
- when the document is hidden, skip continuous requests and refresh immediately
  after visibility returns
- temporary failure keeps the last accepted cards visible
- repeated failure may use a bounded delay/backoff, but successful recovery
  returns to the configured interval
- server response may clamp the effective interval

Phase 07 polling is independent on KDS and Queue. They must not share one browser
timer or mutate each other's stores.

---

# 20. Polling Settings

Before implementation, add these exact option keys to `DATABASE.md` and the
approved settings surface:

```text
coffeepos_kds_poll_interval_ms
coffeepos_order_queue_poll_interval_ms
```

Contract:

```text
default: 5000 ms
minimum: 3000 ms
maximum: 60000 ms
```

Rules:

- sanitize as bounded positive integers
- only authorized administrators may update them
- send the effective value in screen config/list response
- clients must not accept arbitrary query-string polling intervals
- settings are configuration, not transaction state

---

# 21. New-Order Detection

KDS detects new orders by comparing accepted server projections, not by order
number text or DOM presence.

Rules:

- initial successful load establishes the baseline and plays no sound
- a later accepted poll finds IDs not present in the previous accepted active
  set
- only newly appearing orders in KDS state `new` qualify for sound
- one poll containing multiple new orders produces one alert, not one sound per
  card
- filter changes and retry recovery do not replay all existing orders as new
- an order ID already acknowledged in the current page lifetime does not alert
  again merely because it left/re-entered a filter
- DOM render failure must not corrupt the acknowledged-ID set

The server remains the source of the list. New-order detection is presentation
logic only.

---

# 22. Sound Contract

Browsers may block audio until explicit interaction.

Rules:

- sound is disabled by default until the operator explicitly enables it
- the Sound Toggle is a real button with pressed state and readable label
- enabling sound performs the minimum user-gesture audio unlock required
- failure to unlock audio renders a controlled explanation
- preference is per browser/device and may use localStorage
- preferred key: `coffeepos:kds:sound-enabled`
- do not store sound preference on WooCommerce orders or in operational metadata
- no remote audio asset or production library is required
- the alert must be short and non-looping
- disabling sound stops future alerts immediately
- no sound is played for state updates, completed orders, cancellation, initial
  hydration, or polling errors

The UI must remain fully usable when audio is unavailable.

---

# 23. Timer and Clock Contract

Elapsed KDS time starts at `received_at`.

At every accepted response, calculate a local server offset:

```text
server_offset_ms = parsed_server_time - local_receive_time
estimated_server_now = local_now + server_offset_ms
elapsed = max(0, estimated_server_now - received_at)
```

Rules:

- server timestamps are authoritative
- update visible timers once per second locally
- do not request the server once per second
- use a single screen timer, not one interval per card
- clamp negative elapsed values to zero
- invalid timestamps render a neutral `--:--` and normal severity
- format at least `mm:ss`; support hours without wrapping after 59 minutes
- warning begins exactly at 05:00
- critical begins exactly at 10:00
- severity updates with the same local tick as the displayed timer
- a poll refresh may correct server offset and elapsed display

---

# 24. KDS Filters and Ordering

Required filters:

```text
All active
New
Preparing
Ready
```

Rules:

- filters are stable buttons/tabs with pressed/selected state
- filtering may be requested server-side; the store still retains only the
  accepted response for the active filter
- filter actions do not mutate orders
- cards are ordered oldest `received_at` first
- tie-break by numeric order ID ascending
- polling must not reorder equal records nondeterministically
- focus should not jump to another card on routine refresh

---

# 25. KDS Card UI

Every card displays:

```text
order number
received time
elapsed time
normal/warning/critical severity
service type and table where applicable
current KDS state
item name and quantity
variation summary
modifier summary
quick-note summary
important custom note
one valid primary transition action
```

Primary action mapping:

```text
new       → Start
preparing → Ready
ready     → Complete
```

Rules:

- pending affects the selected card only
- important notes remain visually prominent and safely escaped
- missing optional data does not leave placeholder punctuation
- action failure restores the action and keeps the last canonical state
- conflict replaces the card with the returned current projection and explains
  that another screen updated it
- completed/cancelled cards disappear only after a successful terminal result or
  accepted poll

---

# 26. Order Queue UI

Layout:

```text
Order Queue
├── Header
│   ├── Screen Name
│   ├── Refresh Status
│   └── Manual Refresh
├── Filters
└── Active Order List/Grid
    └── Order Card[]
```

Each card displays:

```text
order number
customer display name or Guest
dine-in table or Takeaway
order/received time
WooCommerce-formatted total
KDS state
Complete action when ready
Cancel action when new/preparing
Reprint receipt action when available
```

Rules:

- destructive cancel requires a confirmation dialog
- complete is available only when server projection permits it
- reprint is non-mutating
- a card may expose multiple actions, but only one mutation runs at a time
- filter controls do not become Order History date/status browsing

---

# 27. Loading, Empty, and Error States

KDS states:

```text
initial loading
active list
no active orders
refreshing with existing cards
temporary refresh failure with existing cards
blocking initial failure with Retry
sound unavailable
action pending
action conflict
```

Order Queue uses equivalent list/action states.

Rules:

- initial loading has a dedicated status region
- empty text is `No active orders.`
- routine refresh does not blank or replace cards with a full-screen spinner
- one failed poll retains valid cards and shows a non-blocking warning
- first-load failure exposes Retry
- per-card action errors stay associated with that card where practical
- error messages are plain text and do not expose stack traces/provider details
- a successful later poll clears stale refresh warnings

---

# 28. PHP Templates

Required conceptual templates:

```text
templates/kds/content.php
templates/kds/header.php
templates/kds/filters.php
templates/kds/order-grid.php

templates/order-queue/content.php
templates/order-queue/header.php
templates/order-queue/filters.php
templates/order-queue/order-list.php

templates/components/kds-templates.php
templates/components/order-queue-templates.php
```

Required native templates include:

```text
KDS order card
KDS order item
Order Queue card
```

Receipt reprint reuses the established Phase-05 receipt template.

Rules:

- PHP owns HTML structure
- repeated AJAX content comes from native `<template>` elements
- JavaScript clones/binds templates through `TemplateRenderer`
- no application markup is built with JavaScript template strings
- templates contain no raw WooCommerce query/business logic
- output is escaped according to context

---

# 29. Stable UI Hooks

Use stable behavior contracts such as:

```text
data-component="kds-screen"
data-component="kds-refresh-status"
data-component="kds-order-grid"
data-component="kds-empty"
data-component="kds-order-card"
data-component="kds-order-items"
data-component="kds-timer"
data-component="order-queue-screen"
data-component="order-queue-list"
data-component="order-queue-card"

data-action="toggle-kds-sound"
data-action="filter-kds-state"
data-action="refresh-kds"
data-action="transition-kds-order"
data-action="filter-order-queue"
data-action="refresh-order-queue"
data-action="complete-order"
data-action="cancel-order"
data-action="reprint-order"
```

Rules:

- verify all consumers before renaming a hook
- use `data-key` with the stable order ID for list reconciliation
- use `data-field` only for safe text binding
- visual severity classes remain separate from action selectors
- do not put capabilities, nonces, or trusted state in data attributes as an
  authorization mechanism

---

# 30. JavaScript Modules

Recommended ownership:

```text
assets/js/screens/kds.js
assets/js/screens/order-queue.js
assets/js/components/kds-order-grid.js
assets/js/components/kds-sound.js
assets/js/components/kds-timer.js
assets/js/components/order-queue-list.js
assets/js/components/order-operation-dialog.js
assets/js/state/kds-store.js
assets/js/state/order-queue-store.js
assets/js/core/polling-controller.js
```

Rules:

- `app.js` remains a bootstrap
- the shared REST client owns request/envelope handling
- shared polling logic may be reused without sharing KDS/Queue state
- screen controllers coordinate modules and DOM events
- stores contain projections and UI state, not WooCommerce business rules
- the timer module derives display time only
- sound module has no order-query responsibility
- no frontend framework or production dependency is introduced

---

# 31. KDS Client State

Minimum state:

```text
orders
active_filter
initial_loaded
refresh_state
last_success_at
server_offset_ms
acknowledged_order_ids
sound_enabled
sound_unlocked
pending_order_id
pending_operation_id
error
```

Rules:

- accepted list response replaces the active-filter projection
- per-card transition response replaces/removes only the matching order
- stale request sequences are ignored
- acknowledged IDs are presentation memory, not persistent order data
- no client state can mark an order completed/cancelled without server success

---

# 32. Order Queue Client State

Minimum state:

```text
orders
filters
initial_loaded
refresh_state
last_success_at
pending_order_id
pending_operation_id
confirmation_order_id
error
```

Rules:

- Queue does not import or depend on the KDS store
- complete/cancel responses reconcile by order ID
- stale conflict details replace the matching projection
- print state does not mutate active order projection

---

# 33. Race Conditions

Phase 07 must handle:

```text
poll finishes after filter changed
manual refresh during automatic request
automatic request during pending transition
two KDS screens transition the same order
KDS completes while Queue polls
Queue cancels while KDS polls
external WooCommerce status change
order becomes refunded/cancelled between query and action
sound enabled while a poll completes
screen hidden/resumed during request
late request completes after controller destroy
```

Rules:

- request sequence/AbortController prevents stale list replacement
- server state/revision protects mutations
- accepted mutation response wins locally over an older in-flight poll
- a subsequent newer poll may reconcile external state
- removed terminal orders are not resurrected by an older response
- action buttons are not unlocked by unrelated poll completion

---

# 34. Security

Every Phase-07 route requires:

```text
WordPress authentication
CoffeePOS capability check
REST nonce policy
strict order ID validation
allow-listed filters and states
bounded limit/poll settings
expected state/revision validation
client operation ID validation
server-side transition validation
contextual output escaping
```

Never trust the client for:

```text
order eligibility
paid state
WooCommerce status
KDS current state/revision
allowed actions
customer identity
prices/totals
received/elapsed timestamps
receipt availability
completion/cancellation success
```

Do not expose:

```text
billing phone/address/email
REST nonce in API payloads
payment provider secrets
WooCommerce session cookies/tokens
internal order notes not intended for preparation
raw exception traces
```

Cancellation is a sensitive operation and must be audited through WooCommerce
order notes and the authenticated user context where supported.

---

# 35. Performance and HPOS

Rules:

- query orders through `wc_get_orders()`/WooCommerce order APIs
- avoid direct `wp_posts`/`wp_postmeta` assumptions
- bound every operational query
- avoid N+1 product/customer queries when saved order data is sufficient
- project from order items and documented metadata
- do not call receipt projection for every Queue card
- do not calculate reports/shift aggregates in list endpoints
- full-list polling is acceptable only within the bounded active window
- if future scale requires cursors/deltas, update `ORDER-API.md` before changing
  the response contract

No cache may make a state transition stale or bypass expected revision checks.

---

# 36. Settings and Asset Loading

Phase 07 may add only the polling settings from Section 20.

Asset rules:

- load KDS assets only on `/pos/kds`
- load Queue assets only on `/pos/order-queue`
- load shared polling/template/API modules only when consumed
- localize only safe route config, nonce, screen name, and effective interval
- do not load Cashier checkout or Customer Display synchronization controllers
  on operational screens
- bump plugin asset version when required for browser cache invalidation

---

# 37. Acceptance Criteria

## Routes and Projections

1. `/pos/kds` and `/pos/order-queue` render authorized independent screens.
2. Unauthorized route/API access is rejected.
3. Only paid eligible CoffeePOS orders enter operational projections.
4. Raw WooCommerce/customer objects and private billing data are not exposed.
5. KDS item projection safely includes variation/modifier/quick-note/custom-note
   context.

## KDS Workflow

6. New orders appear through polling without manual refresh.
7. `new → preparing → ready → completed` works through validated server actions.
8. Invalid, stale, duplicate, and terminal transitions are controlled.
9. Successful complete updates WooCommerce and removes the active card.
10. Cancellation works only from approved states and is not presented as refund.

## Timer and Sound

11. Elapsed time uses server-derived `received_at` and updates locally each
    second.
12. Warning starts at 05:00 and critical starts at 10:00.
13. Initial hydration plays no sound.
14. One later poll containing new orders produces one alert when sound is
    enabled.
15. Sound preference is opt-in, per browser, and failure does not block KDS.

## Polling and Failure

16. Baseline effective interval is five seconds.
17. Polling requests never overlap.
18. Filter changes/late responses cannot overwrite newer state.
19. Existing cards remain visible during temporary polling failure.
20. Resume from a hidden tab triggers an immediate refresh.

## Order Queue

21. Queue shows number, customer, service/table, time, total, and KDS state.
22. Complete/cancel availability matches the server projection.
23. Queue and KDS shared operations reconcile on their next poll.
24. Receipt reprint uses the saved WooCommerce receipt projection.
25. Queue does not implement history, refunds, reorder, shifts, or reports.

## Rendering and Storage

26. Repeated markup is PHP-owned and rendered through `TemplateRenderer`.
27. No custom KDS/Queue order table is created.
28. Operational metadata uses WooCommerce CRUD and documented keys.
29. Phase-05 Cashier/checkout and Phase-06 Customer Display behavior remain
    functional.

---

# 38. Required Test Cases

## Eligibility and Projection

```text
TC-01 paid CoffeePOS order is eligible
TC-02 unpaid order excluded
TC-03 non-CoffeePOS order excluded
TC-04 cancelled/refunded/failed order excluded
TC-05 missing KDS metadata uses deterministic fallback
TC-06 KDS item projection includes saved configuration/notes
TC-07 invalid legacy item JSON becomes empty safe data
TC-08 Queue customer projection omits private billing fields
TC-09 Queue total comes from WooCommerce order
```

## KDS Transitions

```text
TC-10 new to preparing
TC-11 preparing to ready
TC-12 ready to completed
TC-13 new to cancelled
TC-14 preparing to cancelled
TC-15 new cannot skip to ready/completed
TC-16 ready cannot regress or cancel
TC-17 terminal state cannot reopen
TC-18 transition timestamp written once
TC-19 revision increments exactly once
TC-20 stale expected state/revision conflict
TC-21 identical retry is idempotent
TC-22 operation ID payload conflict
TC-23 external terminal WooCommerce status wins
TC-24 HPOS-compatible CRUD path, no direct postmeta query
```

## APIs and Security

```text
TC-25 KDS list authorization
TC-26 Queue list authorization
TC-27 transition authorization and nonce policy
TC-28 invalid order ID/state/filter/limit rejected
TC-29 KDS list bounded and deterministically ordered
TC-30 Queue list bounded and deterministically ordered
TC-31 no raw WooCommerce object/private payment/customer data
TC-32 cancel uses WooCommerce and records reason safely
TC-33 complete/cancel do not alter payment totals
TC-34 receipt reprint is read-only
```

## Polling and Client Ordering

```text
TC-35 initial load schedules next request after settlement
TC-36 slow request does not overlap another request
TC-37 manual refresh coordinates with active request
TC-38 filter change ignores late response
TC-39 controller destroy stops polling
TC-40 hidden document pauses routine polling
TC-41 visible resume refreshes immediately
TC-42 temporary failure keeps accepted cards
TC-43 successful recovery clears refresh error
TC-44 older poll cannot resurrect terminal order
```

## Timer and Sound

```text
TC-45 server offset and received_at calculate elapsed time
TC-46 invalid/future timestamp is controlled
TC-47 timer updates locally without per-second API request
TC-48 04:59 normal
TC-49 05:00 warning
TC-50 09:59 warning
TC-51 10:00 critical
TC-52 initial list plays no sound
TC-53 later new order plays one alert
TC-54 multiple new orders in one response play one alert
TC-55 filter/retry does not replay old alert
TC-56 sound disabled/unavailable remains usable
```

## UI and Scope

```text
TC-57 KDS PHP card/item templates and stable hooks
TC-58 Queue PHP card template and stable hooks
TC-59 no JavaScript application-markup template strings
TC-60 per-card pending/conflict behavior
TC-61 cancel confirmation dialog
TC-62 receipt uses Phase-05 template/projection
TC-63 no BroadcastChannel dependency for KDS/Queue
TC-64 no custom operational table
TC-65 no Phase-08/09/10 implementation leakage
TC-66 Phase-05 and Phase-06 regression contracts pass
```

---

# 39. Browser Verification

Phase 07 MUST be verified in a real WordPress/WooCommerce browser environment.

Minimum checks:

```text
Open authorized KDS and Order Queue routes
Unauthorized screen/API request
Empty initial state
Create a paid Cash order and observe it within the polling window
Create a paid Bank order and observe it within the polling window
Initial KDS load produces no sound
Explicitly enable/disable sound
New order after baseline produces one sound
Start → Ready → Complete from KDS
Queue observes the same transitions after polling
Complete a ready order from Queue
Cancel a new/preparing order with confirmation
Attempt invalid/stale transition from two windows
Reprint receipt from Queue
Variation/modifier/quick-note/custom-note rendering
Dine-in table and Takeaway rendering
05:00 warning and 10:00 critical presentation
Slow response does not create overlapping polling
Temporary network/server failure retains cards
Manual retry and successful recovery
Filter change while request is active
Hide/resume tab refresh behavior
Large-screen and tablet KDS layout
Keyboard focus, touch targets, contrast, reduced motion
Cashier checkout and Customer Display regression flow
```

Do not claim a browser scenario passed unless it was actually performed.

---

# 40. Documentation Gate

Before implementing Phase 07, update the corresponding contracts in the same
change:

```text
DATABASE.md
  - exact KDS metadata keys and timestamp/revision rules
  - exact polling option keys and bounds

ORDER-API.md
  - KDS list projection and bounds
  - KDS transition endpoint
  - Queue list projection and filters
  - complete/cancel expected-state/revision/idempotency payloads
  - new error codes and conflict details

KDS-UI.md
  - PHP template hooks
  - opt-in sound/initial hydration behavior
  - server-clock timer contract

COMPONENTS.md
  - KDS/Queue card action and pending/conflict behavior
```

Also resolve before implementation:

```text
exact WooCommerce cancellation note format
whether external WooCommerce processing orders without KDS metadata are
backfilled only on transition or by an explicit migration
```

Do not improvise with a custom table, custom WooCommerce statuses, direct
postmeta SQL, unauthenticated routes, optimistic client transitions, or global
browser state if these contracts remain unresolved.

---

# 41. Definition of Done

Phase 07 is complete only when:

```text
Paid CoffeePOS WooCommerce Orders
        ↓
Authorized Bounded Operational Queries
        ↓
KDS / Queue Canonical Projections
        ↓
Non-overlapping Five-second Polling
        ↓
Server-time Timer + Opt-in New-order Sound
        ↓
Revisioned Server-validated State Transitions
        ↓
WooCommerce Complete / Cancel + Receipt Reprint
```

works end-to-end without duplicating WooCommerce orders, trusting client state,
overlapping polling requests, replaying initial-load sound, exposing private
customer/payment data, or implementing later-phase functionality.

Documentation, automated tests, PHP/JavaScript syntax checks, and required real
browser scenarios must match the implementation.

---

# 42. Final Phase 07 Rule

When Phase 07 is complete:

STOP.

Do not automatically implement Phase 08.

Phase 08 will explicitly introduce:

```text
member creation
automatic phone lookup/autofill
guest/member Customer Display identity
masked member phone on Customer Display
membership extension boundaries
```

Shift Management moves to Phase 09 and will explicitly introduce:

```text
open shift
opening cash
active cashier shift
cash/bank/total sales statistics
expected cash
close shift
actual cash
variance
shift notes/history
```

KDS and Order Queue must remain operational order surfaces. They must not infer,
persist, or reconcile shift accounting before Phase 09 defines that contract.
