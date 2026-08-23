# PHASE-08.md

# CoffeePOS Phase 08 — Membership & Customer Identity

## 1. Objective

Complete the operational membership workflow on top of the Phase-04 customer
context foundation.

Primary workflows:

```text
Cashier Enters Phone
    ↓
Exact Automatic Lookup
    ├── Existing WooCommerce Customer → Autofill Member Preview
    └── Not Found → Create WooCommerce Customer → Member Preview
    ↓
Attach Member To Revisioned POS Cart
    ↓
Customer Display Shows Member + Masked Phone
    ↓
Checkout Uses The Attached WooCommerce Customer
```

and:

```text
Guest Selected
    ↓
Cart Customer Context Returns To Guest
    ↓
Customer Display Shows Guest
```

At the end of this phase:

- the Cashier can explicitly use guest or member mode
- entering a valid phone automatically performs an exact customer lookup
- an existing member's allowed data is filled into the cashier member panel
- a cashier can create a member when the phone does not exist
- member creation uses WooCommerce customer APIs, not a parallel customer table
- duplicate normalized phone numbers are protected server-side
- the selected member remains server-authoritative cart context
- checkout continues to use the attached WooCommerce customer
- Customer Display shows whether the buyer is a guest or member
- Customer Display never renders the full customer phone number
- Customer Display uses a masked value such as `0353***250`
- synchronization follows the session-scoped `BroadcastChannel` contract
- loyalty rewards and tier discounts remain explicitly deferred

---

# 2. Relationship to Phase 04

Phase 04 already provides:

```text
guest CustomerContext
exact phone lookup for existing WooCommerce customers
explicit customer attachment/removal
revisioned CartView mutations
CustomerService
CustomerView
MembershipProviderInterface
optional read-only membership projection
```

Phase 08 MUST extend those contracts. It MUST NOT create a second cart customer
state or duplicate the existing lookup/attachment implementation.

For Phase 04, `member` meant a selected WooCommerce customer. Phase 08 turns
that foundation into a complete cashier workflow by adding member creation,
automatic lookup UX, privacy-safe Customer Display presentation, and an
explicit extension boundary for later loyalty features.

---

# 3. Prerequisites

Codex MUST read:

```text
AGENTS.md
featured.txt

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

docs/04-api/API-ARCHITECTURE.md
docs/04-api/POS-API.md
docs/04-api/ORDER-API.md

docs/05-phases/PHASE-04.md
docs/05-phases/PHASE-05.md
docs/05-phases/PHASE-06.md
docs/05-phases/PHASE-07.md
```

Codex MUST inspect and preserve:

```text
CustomerService and WooCommerceCustomerGateway
CustomerView and CartView
MembershipProviderInterface and current provider
GET /customers/lookup
PUT/DELETE /cart/customer
pos_session_id + expected_revision contract
checkout customer association
Customer Display BroadcastChannel message contract
ready/request/snapshot recovery
session-scoped channel isolation
shared REST client and TemplateRenderer
WooCommerce CRUD/HPOS compatibility
```

---

# 4. Scope

## In Scope

```text
Explicit Guest and Member cashier modes
Exact normalized-phone lookup
Automatic debounced lookup after valid phone input
Abort/ignore stale lookup requests
Existing-member autofill/preview
WooCommerce customer creation
Required member name and phone
Optional member email
Duplicate-phone protection
Member-creation idempotency
Phone-scoped creation concurrency lock
Attach created/existing member to active cart
Return active cart to guest
Cashier member summary and change/remove actions
Server-provided masked phone projection
Customer Display guest/member identity
Customer Display masked phone rendering
Privacy-safe BroadcastChannel customer payload
Late-open Customer Display snapshot recovery
Cart revision conflict reconciliation
Checkout preservation of WooCommerce customer identity
Membership extension projection with no active loyalty benefit
PHP-owned templates and Vanilla JavaScript behavior
Stable REST errors, security, privacy, accessibility, and tests
```

## Out of Scope

Do NOT implement:

```text
Buy-five-get-one reward
Purchase stamps or punch cards
Points earning or redemption
Points ledger
Membership tier calculation
Tier-based coupon assignment
Tier-based pricing or automatic discounts
Member-only coupon creation
Birthday rewards
Wallet or stored-value balance
Paid membership subscriptions
Member login/password UI
Email or SMS marketing
OTP phone verification
Customer address management
Customer order history in the member panel
Customer editing beyond create-member fields
Merging duplicate WooCommerce customers
Independent CoffeePOS customer or loyalty table
Shift Management
Order History/refund UI
Reports
```

The deferred features are future membership consumers. Phase 08 provides stable
identity and projection boundaries but MUST NOT award, count, reserve, redeem,
or discount anything.

---

# 5. Architecture

```text
Cashier Member UI
        ↓
Customer REST Controller
        ↓
CustomerService
        ├── WooCommerceCustomerGateway
        │       ↓
        │   WooCommerce Customer CRUD
        │
        └── MembershipProviderInterface
                ↓
            MembershipView | null

Selected Customer ID
        ↓
CartSessionService + expected_revision
        ↓
Authoritative CartView
        ↓
Privacy-safe Customer Display Projection
        ↓
Session-scoped BroadcastChannel Snapshot
```

WooCommerce owns customer identity, name, billing phone, optional email,
order-to-customer association, and later coupon validation. CoffeePOS owns the
POS workflow, phone policy, safe projections, cart selection, display masking,
and membership-provider abstraction.

---

# 6. Customer Modes

Supported cart modes remain exactly:

```text
guest
member
```

Guest means `customer_id = 0` and no WooCommerce customer is attached.

Member means:

```text
a valid WooCommerce customer is attached by customer_id
the server has reloaded the current customer
the customer has a valid normalized billing phone
the cart contains a server-owned CustomerView
```

Do not infer member mode merely because text exists in the phone field. Lookup,
preview, and attached cart state are separate concepts.

---

# 7. Member Data Contract

Phase-08 member creation accepts only:

```text
display_name   required
phone          required
email          optional
```

The application may split `display_name` into first/last name only through one
documented deterministic helper. It MUST preserve the user-visible name.

Phase 08 does not collect or expose passwords, usernames, addresses, birth
date, gender, marketing consent, tax identifiers, or internal notes.

If WooCommerce/WordPress requires an internal username, the gateway creates a
collision-safe internal value and never exposes it in either POS screen.

---

# 8. Phone Normalization

Lookup and creation MUST use the same server-side normalization policy:

```text
trim surrounding whitespace
remove spaces, dots, hyphens, and parentheses
preserve one leading + only for international input
reject letters or unsupported characters
normalize supported Vietnam +84 form to one documented comparison form
validate final digit count and prefix policy in one shared service
```

The same helper MUST serve lookup, duplicate detection, creation, masking, and
tests. Browser normalization is only a usability aid.

Do not use fuzzy, suffix-only, or wildcard matching that can attach the wrong
customer.

---

# 9. Exact Lookup Contract

Existing route:

```text
GET /coffeepos/v1/customers/lookup?phone={phone}
```

Behavior:

- normalize and validate the phone
- search WooCommerce customers by exact normalized billing phone
- return one safe CustomerView when exactly one match exists
- return controlled `customer_not_found` when none exists
- return `customer_phone_ambiguous` when multiple customers match
- never silently choose an arbitrary customer
- never attach the lookup result to the cart automatically on the server

Lookup is read-only and does not increment cart revision.

---

# 10. Automatic Lookup UX

```text
cashier types phone
    ↓
client waits 400 ms after latest input
    ↓
valid-looking phone starts lookup
    ↓
new input aborts/invalidates previous lookup
    ↓
latest matching response updates preview
```

Rules:

- do not request an empty or obviously incomplete phone
- Enter or explicit search may trigger immediately
- stale responses MUST NOT overwrite a newer phone
- errors preserve the typed phone
- a found customer fills only allowed preview fields
- the cashier explicitly confirms `Use member` before cart attachment
- changing the phone clears the previous candidate
- returning to guest clears candidate state and mutates the canonical cart

Automatic lookup does not mean automatic cart mutation.

---

# 11. Existing Member Autofill

Cashier preview may show:

```text
display name
full phone
email when present
membership status/tier only when supplied by an approved provider
points/balance only when supplied by an approved provider
```

For the default Phase-08 implementation, identity comes from WooCommerce and
unavailable extension fields are hidden. The UI MUST NOT derive purchase count,
points, or tier from order history.

The Cashier may see the full phone to confirm identity. Customer Display uses a
different safe projection.

---

# 12. Member Creation API

Required route:

```text
POST /coffeepos/v1/customers
```

Request:

```json
{
  "display_name": "Nguyen Van An",
  "phone": "0353123250",
  "email": "an@example.com",
  "client_operation_id": "member-create-01J..."
}
```

`email` may be empty or omitted.

Creation sequence:

```text
authenticate + authorize
    ↓
normalize and validate fields
    ↓
acquire normalized-phone lock
    ↓
resolve idempotent replay
    ↓
repeat exact phone lookup
    ↓
reject duplicate/ambiguous phone
    ↓
create through WooCommerce customer CRUD
    ↓
reload and return CustomerView
```

Return HTTP 201 for initial creation and HTTP 200 for idempotent replay.

Creation does not mutate cart revision. After success, the UI uses the existing
attach-customer mutation. The normal flow SHOULD attach immediately. If cart
revision conflicts, preserve the created customer candidate and allow retry.

---

# 13. Creation Idempotency and Locking

`client_operation_id` is required and follows existing safe format/length rules.

Lock key concept:

```text
coffeepos:customer-phone:{sha256(normalized_phone)}
```

The raw phone MUST NOT appear in the lock name or diagnostic logs.

Rules:

- same operation ID and normalized payload returns the same customer
- same operation ID with different payload returns `idempotency_key_reused`
- concurrent creates for one normalized phone cannot create two customers
- duplicate detection runs again after acquiring the lock
- lock failure returns a stable retryable error
- never fall back to unlocked creation

Any new operation-result storage must be documented in `DATABASE.md` first.

---

# 14. Duplicate Phone Policy

The normalized phone is the CoffeePOS membership lookup key.

One existing match:

```text
do not create
return customer_phone_exists
return authorized existing CustomerView when safe
offer Use existing member
```

Multiple existing matches:

```text
do not create
return customer_phone_ambiguous
do not select an arbitrary customer
require data cleanup outside Phase 08
```

Phase 08 does not merge or delete duplicate WooCommerce customers.

---

# 15. WooCommerce Customer Gateway

Creation MUST extend the existing customer gateway contract. The gateway MUST:

- use WooCommerce customer CRUD/APIs
- create a standard WooCommerce customer compatible with HPOS orders
- set sanitized display/name fields
- set normalized billing phone
- set email only when valid and supplied
- handle internal username requirements without exposing them
- return the authoritative customer ID
- translate WooCommerce/WordPress failures to stable application errors

Do not call `wp_insert_user` directly from a REST controller.

---

# 16. Attach Member and Return to Guest

Reuse:

```text
PUT    /coffeepos/v1/cart/customer
DELETE /coffeepos/v1/cart/customer
```

Attach request authority remains `pos_session_id`, `expected_revision`, and
`customer_id`. The server reloads the customer, resolves membership projection,
sets CustomerContext, increments revision once, and returns full CartView.

Client-supplied name, phone, email, membership, tier, points, or masked phone
are never authoritative.

Returning to guest increments revision once and MUST NOT clear cart items,
notes, coupon, table, or order type. It does not delete the WooCommerce customer.

---

# 17. Cashier CustomerView

Member example:

```json
{
  "mode": "member",
  "customer_id": 123,
  "display_name": "Nguyen Van An",
  "phone": "0353123250",
  "phone_masked": "0353***250",
  "email": "an@example.com",
  "membership": null
}
```

Guest example:

```json
{
  "mode": "guest",
  "customer_id": 0,
  "display_name": "Guest customer",
  "phone": "",
  "phone_masked": "",
  "email": "",
  "membership": null
}
```

`phone_masked` comes from a shared server-side privacy helper.

---

# 18. Customer Display Safe Projection

Member projection:

```json
{
  "mode": "member",
  "display_name": "Nguyen Van An",
  "phone_masked": "0353***250",
  "membership": null
}
```

Guest projection:

```json
{
  "mode": "guest",
  "display_name": "Guest",
  "phone_masked": "",
  "membership": null
}
```

It MUST NOT contain full phone, email, addresses, internal username, customer
notes, customer ID, or raw membership metadata.

This is presentation data, not a second cart authority.

---

# 19. Phone Masking Policy

For a normal 10-digit local phone:

```text
first 4 digits + *** + last 3 digits

0353123250 → 0353***250
```

Rules:

- preserve at most the first four and last three significant digits
- replace the middle with exactly `***`
- never reveal extra digits because separators are present
- for short legacy values, mask conservatively and never return the full value
- invalid/unmaskable values produce an empty masked value
- masking has separate automated tests from normalization

---

# 20. BroadcastChannel Privacy Contract

Cashier and Customer Display retain:

```text
same pos_session_id
session-scoped channel
monotonic cart revisions
ready/request/snapshot handshake
stale/foreign-session rejection
```

Before a snapshot enters the Customer Display channel, its customer field MUST
be converted to the safe projection from Section 18.

The message MUST NOT retain full phone/email in an unused field, nested raw
CartView, hidden DOM node, or debug payload. Every message type that can carry
customer/cart state uses one central sanitizer.

---

# 21. Customer Display UI

Guest state shows `Guest`.

Member state may show:

```text
Member
display name
masked phone, for example 0353***250
approved membership label only when supplied
```

Rules:

- never render full phone, email, or customer ID
- bind customer values as text, never HTML
- update after a newer valid cart snapshot
- late-open display obtains current member through handshake recovery
- payment/success overlays may show only the same safe projection
- new-cart reset follows the authoritative cart customer state

---

# 22. Cashier Membership UI

The cashier surface supports:

```text
Guest/Member indication
phone input
automatic lookup status
explicit search fallback
existing-member preview
Use member action
not-found state
Create member form/action
attached-member summary
Change member action
Remove member / Use guest action
inline errors
```

State model:

```text
guest → typing → searching → found/not_found/error
found → attaching → attached/conflict
not_found → creating → created_pending_attach → attached/conflict
attached → returning_to_guest → guest
```

Returned CartView determines guest versus attached. Local lookup state does not
impersonate an attached member.

---

# 23. Create Member UI

When lookup returns not found, PHP-owned fields are:

```text
phone          prefilled; required
display name   required
email          optional
```

Behavior:

- editing phone invalidates not-found state and repeats lookup
- duplicate submit is disabled while creating
- safe input remains after recoverable errors
- successful creation shows authoritative customer data
- UI immediately attempts cart attachment
- attachment conflict shows latest cart and a Retry use member action
- do not claim creation failed if creation succeeded but attachment failed

---

# 24. PHP Template and Accessibility Contract

HTML belongs in PHP templates. Repeated/asynchronous UI uses native `<template>`
elements and shared `TemplateRenderer`. JavaScript MUST NOT build member forms,
summaries, or Customer Display blocks with large HTML strings.

Before changing selectors, search all PHP, JavaScript, CSS, and tests.

Accessibility requirements:

- labeled inputs
- polite lookup status
- field-associated validation messages
- shared modal focus behavior
- full keyboard workflow
- busy/disabled pending actions
- guest/member state not communicated only by color
- focus moves to attached-member summary after success

---

# 25. REST API Summary

```text
GET    /coffeepos/v1/customers/lookup
POST   /coffeepos/v1/customers
PUT    /coffeepos/v1/cart/customer
DELETE /coffeepos/v1/cart/customer
```

All routes require WordPress authentication, POS capability authorization,
nonce protection where applicable, validation/sanitization, stable response
envelopes, and server-owned projections.

Customer Display has no arbitrary customer lookup endpoint.

---

# 26. Stable Error Codes

```text
invalid_customer_phone       400
invalid_customer_name        400
invalid_customer_email       400
customer_not_found           404
customer_phone_exists        409
customer_phone_ambiguous     409
customer_create_failed       500
customer_creation_locked     409
customer_lookup_failed       503
invalid_customer             existing documented status
cart_revision_conflict       409
idempotency_key_reused       409
```

Messages are translatable and codes machine-stable. Infrastructure failure MUST
NOT be presented as not-found because that could invite duplicate creation.

---

# 27. Validation

```text
display_name
    required plain text
    1..200 characters after trim

phone
    required
    shared normalization and supported-phone validation

email
    optional
    valid when non-empty
    maximum 254 characters

client_operation_id
    required for creation
    existing safe format
    8..128 characters
```

Client validation is a usability aid only.

---

# 28. Security and Privacy

- lookup/create require authenticated POS capability
- Customer Display cannot query arbitrary phones
- responses omit unrelated WooCommerce metadata and addresses
- logs redact phone/email and omit nonce/cookies/request bodies
- runtime data is escaped/bound as text
- attachment reloads the untrusted submitted customer ID
- duplicate detection is exact and server-side
- broadcast sanitization is centralized
- full phone is not persisted in browser storage for convenience

Masking is a presentation privacy requirement, not an authorization substitute.

---

# 29. Revision and Checkout Integration

Attachment/removal reuse the one cart revision. A stale customer mutation cannot
overwrite newer items, service context, or coupon changes. Do not introduce a
separate customer revision.

Customer creation does not reserve a cart revision. On create/attach conflict,
retain the candidate outside canonical cart state and retry after reconciliation.

At checkout, reload/revalidate the attached WooCommerce customer as required
and set the order customer through WooCommerce CRUD. Guest checkout remains
unchanged. Creation alone does not affect an order; only canonical CustomerContext
at checkout is relevant.

---

# 30. Membership Extension Boundary

Keep using:

```text
MembershipProviderInterface
    ↓
MembershipView | null
```

Optional future read-only fields may include `status_label`, `tier_code`,
`tier_label`, `points_display`, and `balance_display`. The default implementation
MUST NOT invent them. Workflow continues when the provider returns `null` or
fails safely.

Future providers require documented storage, calculation, security, and
lifecycle contracts.

---

# 31. Deferred Loyalty Features

## Buy Five Get One

Do not implement a counter in Phase 08. A later phase must define eligible
products/categories, quantity versus order counting, refund reversal, expiry,
concurrency, redemption, and WooCommerce tax/order representation.

## Tier Coupons

Do not assign a tier or discount in Phase 08. A later phase must define tiers,
thresholds, qualification periods, upgrade/downgrade rules, coupon ownership,
expiry, stacking, exclusions, refunds, and manager overrides.

Later discounts MUST use WooCommerce/server authority, never trusted JavaScript
calculations.

---

# 32. Frontend Request Safety

- debounce automatic lookup by 400 ms
- abort superseded lookup when supported
- also use a sequence token to ignore late responses
- separate lookup, create, and attach pending states
- retain one creation operation ID across ambiguous retry
- create a new operation ID only for a genuinely new attempt
- prevent duplicate attachment clicks
- reconcile cart mutation from returned CartView
- sanitize customer display messages centrally before publishing

---

# 33. Required UI States

```text
empty phone
invalid/incomplete phone
searching
one member found
member not found
ambiguous phone
lookup unavailable
creating member
creation validation error
creation conflict with existing member
creation succeeded, attachment pending
attachment revision conflict
attached member
returning to guest
Customer Display guest
Customer Display member
Customer Display late-open recovery
```

Never render a failed lookup as a confirmed guest. Guest is a deliberate cart
state; lookup failure is an error.

---

# 34. Expected Implementation Areas

Following current repository naming:

```text
Application/Customer/CustomerService.php
Application/Contracts customer gateway
Application/Projection/CustomerView.php
shared phone normalization/masking helper
Integration/WooCommerce/WooCommerceCustomerGateway.php
REST customer/cart controller and RouteRegistrar
API client
cashier customer/member templates and controller
Customer Display identity template and controller
central safe broadcast projection helper
scoped CSS
Phase08 tests
```

Do not create every listed file automatically. Reuse modules whose current
responsibility already fits.

---

# 35. Automated Verification

At minimum run PHP/JavaScript syntax checks, all available Phase01-Phase07
regression tests, Phase08 core scenarios, and Cashier/Customer Display contract
tests.

Phase-08 tests MUST cover:

1. local and supported `+84` normalization
2. invalid/incomplete phone rejection
3. exact one-customer lookup
4. not-found and ambiguous results
5. infrastructure failure not treated as not-found
6. stale automatic lookup ignored
7. creation with required fields and optional email
8. invalid name/email rejection
9. duplicate and concurrent phone protection
10. idempotent creation retry
11. operation ID reuse with changed payload
12. created/existing member cart attachment
13. attachment revision conflict
14. guest transition preserving unrelated cart data
15. Cashier full-phone authorization
16. safe display projection containing no full phone/email
17. `0353123250 → 0353***250`
18. short/invalid value never leaking full phone
19. all customer-carrying broadcast messages sanitized
20. late-open, stale, and foreign-session display behavior
21. attached customer reaching WooCommerce order
22. unchanged guest checkout
23. null/failing membership provider
24. no loyalty award/discount in Phase 08

---

# 36. Browser Acceptance

Verify in the real WooCommerce browser environment:

1. Existing phone automatically finds and autofills a member; `Use member`
   attaches it.
2. Unknown phone opens member creation; one WooCommerce customer is created and
   attached.
3. Existing/normalized duplicate phone cannot create another customer.
4. Customer Display for the same session shows Member, name, and `0353***250`,
   never the full phone/email.
5. Returning to guest preserves cart/service context and updates the display.
6. A Customer Display opened after attachment recovers the safe member snapshot.
7. Checkout assigns the order to the attached WooCommerce customer.
8. Quickly changing phone input cannot allow an old lookup to overwrite the
   latest value.
9. Retrying after an ambiguous create response creates only one customer.

Do not claim browser acceptance passed unless actually executed.

---

# 37. Acceptance Criteria

- Cashier clearly supports guest/member identity
- valid phone triggers automatic exact lookup
- existing allowed customer information autofills
- cashier can create a WooCommerce-backed member
- duplicate/concurrent creation is controlled and retry idempotent
- created/existing member attaches through revisioned cart contract
- guest transition preserves unrelated cart state
- checkout uses the authoritative attached customer
- Customer Display shows guest/member in real time
- Customer Display uses masked phone and never carries/renders full phone/email
- late-open and session isolation still work
- absent membership provider does not break identity workflow
- no free-item, tier, points, or coupon automation is added
- PHP owns markup and JS uses existing template/state conventions
- privacy/security and regression tests pass
- documentation matches implementation

---

# 38. Required Documentation Updates During Implementation

```text
docs/00-project/REQUIREMENTS.md
    creation, autofill, masking, and deferred loyalty

docs/01-architecture/DOMAIN-MODEL.md
    WooCommerce member identity and extension boundary

docs/01-architecture/DATA-FLOW.md
    lookup/create/attach and safe display flows

docs/01-architecture/SECURITY.md
    customer PII, duplicate protection, logs, display privacy

docs/02-database/DATABASE.md
    operation storage if introduced; no customer/loyalty table

docs/02-database/WOOCOMMERCE-DATA.md
    customer fields written through WooCommerce CRUD

docs/03-ui/UI-ARCHITECTURE.md and COMPONENTS.md
    cashier membership and display states/selectors

docs/04-api/API-ARCHITECTURE.md and POS-API.md
    create route, errors, idempotency, safe projections

docs/04-api/ORDER-API.md
    authoritative member-to-order association where needed
```

---

# 39. Edge Cases

```text
phone with separators
+84 and local form resolving to one key
incomplete phone while typing
reversed lookup response order
same phone submitted in two tabs
lost create response and retry
one or multiple exact legacy matches
missing optional email
invalid legacy phone
null/failing membership provider
cart change between create and attach
display opened after attachment
new-cart reset after checkout
short phone masking input
Unicode or HTML-like member name
unauthorized lookup/create
foreign session or stale revision broadcast
```

---

# 40. Definition of Done

```text
[ ] Roadmap identifies Phase 08 as Membership & Customer Identity
[ ] Existing Phase-04 contracts are reused
[ ] Automatic exact lookup is safe
[ ] Existing-member autofill works
[ ] WooCommerce-backed creation works
[ ] Duplicate/concurrent creation is controlled
[ ] Creation is idempotent
[ ] Member attaches to revisioned cart
[ ] Guest transition preserves unrelated data
[ ] Safe Customer Display projection is centralized
[ ] Full phone/email never enters display payload/rendering
[ ] Masking produces documented output
[ ] Late-open/session synchronization still works
[ ] Checkout uses attached WooCommerce customer
[ ] PHP templates own HTML
[ ] Stable errors/privacy are documented
[ ] Syntax and Phase01-Phase07 regression checks pass
[ ] Phase08 tests and browser acceptance pass or blockers are reported
[ ] Buy-five-get-one and tier coupon logic remain unimplemented
[ ] Shift Management remains deferred to Phase 09
```

---

# 41. Final Phase 08 Rule

Phase 08 owns membership identity workflow, not loyalty economics.

It MUST identify or create a WooCommerce-backed member, attach that member to
the authoritative cart, show guest/member on both screens, and protect phone
privacy on Customer Display.

It MUST NOT award free products, count stamps/points, assign tiers, apply member
discounts/coupons, or create a second customer database.

Stop after Phase 08 acceptance. Shift Management begins in Phase 09 under its
own specification.
