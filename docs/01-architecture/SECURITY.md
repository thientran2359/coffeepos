# SECURITY.md

# CoffeePOS Security Architecture

## 1. Principle

CoffeePOS is a privileged operational application.

Every server-side operation must assume the client may be malicious or incorrect.

---

# 2. Authentication

All protected POS operations require an authenticated WordPress user unless a
documented public Customer Display endpoint explicitly requires otherwise.

Phase 12 uses WordPress authentication exclusively:

- `/pos/` renders a CoffeePOS-branded login form for anonymous staff
- the form verifies a WordPress nonce and calls `wp_signon()`
- the resulting WordPress auth cookie and normal authentication hooks own the
  session, including compatible security and two-factor plugins
- CoffeePOS stores no PIN, password, password copy, or separate staff session
- authentication errors are generic and do not reveal whether a username exists
- successful redirects are restricted to safe internal CoffeePOS staff routes
- logout uses the nonce-protected WordPress logout URL and returns to `/pos/`

The login form is a normal server-rendered WordPress form. CoffeePOS MUST NOT add
an unauthenticated REST endpoint for credentials.

---

# 3. Authorization

Capabilities MUST be checked server-side.

Phase 12 defines these authoritative CoffeePOS capabilities:

| Capability | Protected operation |
|---|---|
| `coffeepos_access_cashier` | Cashier screen and cart/checkout operations |
| `coffeepos_access_kds` | KDS screen, projection, and KDS transitions |
| `coffeepos_access_order_queue` | Order Queue screen, projection, and queue transitions |
| `coffeepos_manage_own_shift` | The authenticated user's shift lifecycle |
| `coffeepos_view_order_history` | Order History list and detail |
| `coffeepos_reprint_receipts` | Receipt projection and reprint |
| `coffeepos_reorder_orders` | Quick reorder |
| `coffeepos_cancel_orders` | Eligible order cancellation |
| `coffeepos_refund_orders` | Eligible order refunds |
| `coffeepos_view_reports` | Reports screen, data, and exports |
| `coffeepos_manage_settings` | CoffeePOS settings |

Default role bundles are:

| WordPress role | Capabilities |
|---|---|
| CoffeePOS Cashier | cashier, own shift, history, reprint, reorder |
| CoffeePOS Kitchen | KDS, order queue |
| CoffeePOS Supervisor | Cashier + Kitchen bundles, cancel |
| CoffeePOS Manager | all CoffeePOS capabilities, including refund, reports, and settings |

The table abbreviates the capability names defined immediately above. WordPress
Administrator and Shop Manager roles receive all CoffeePOS capabilities during
the Phase-12 activation/migration for backward compatibility. A CoffeePOS role
does not receive broad WordPress administration capabilities such as
`manage_woocommerce`, `edit_users`, or `promote_users` merely because it can
manage CoffeePOS work.

Only a user who already has the native WordPress user-management capabilities
may create accounts, reset passwords, or assign roles through WordPress Users.
CoffeePOS does not implement a parallel employee-account manager in Phase 12.

Roles are convenience bundles. Every route and operation checks the exact
capability server-side. Role display, menu visibility, JavaScript state, and a
successful login are not authorization.

Never rely only on route visibility or JavaScript guards.

Roles and capabilities are registered idempotently during activation/migration.
Updates apply to existing roles. Normal deactivation does not remove them and
thereby unexpectedly lock out or alter existing WordPress users.

---

# 4. Nonces

All applicable WordPress AJAX and form requests must use nonce verification.

REST endpoints must use the appropriate WordPress authentication/permission mechanisms.

---

# 5. Input Validation

Validate:

- IDs
- quantities
- prices where supplied
- dates
- order types
- table identifiers
- customer phone numbers
- coupon codes
- payment amounts
- shift values
- filter parameters

Never assume client input is well-formed.

---

# 6. Pricing

Client-provided prices are never trusted.

The server must resolve/validate:

- product price
- variation price
- discounts
- coupon effects
- total

Current CoffeePOS modifiers and quick notes do not affect price. The server must
ignore any client-supplied modifier price. A choice that changes price must be
represented by WooCommerce pricing data, normally a product variation.

Cart mutations must load the cart from the authenticated user's WooCommerce
session, verify the `pos_session_id`, and reject stale `expected_revision`
values. The public correlation ID must never be treated as authorization.

---

# 7. Stock

Client-side stock display is informational.

Before checkout:

- revalidate product availability
- revalidate variation availability
- use WooCommerce stock mechanisms

Quick stock adjustments require authorization and a documented reason.

---

# 8. Payment

Client-side payment state is never authoritative.

Never transition an order to paid solely because:

```text
button clicked
popup closed
QR displayed
JavaScript event fired
```

Payment completion must use the approved server-side payment flow.

---

# 9. Order Operations

Sensitive operations include:

- cancel
- refund
- order modification
- payment completion
- stock adjustment
- shift close

Each requires server-side authorization and validation.

---

# 10. Output Escaping

Escape output according to context:

- HTML
- attributes
- URLs
- JavaScript data
- JSON

Do not print unescaped customer notes or product data.

---

# 11. Customer Data

Treat:

- phone numbers
- customer names
- membership information

as protected business data.

Do not expose unnecessary customer data to the Customer Display.

Phase-08 Customer Display payloads contain only guest/member mode, safe display
name, server-produced masked phone, and approved membership presentation. Full
phone, email, customer ID, address, internal username, and a nested raw Cashier
CartView are prohibited in every cart/workflow snapshot.

Member creation uses a lock key derived from a phone hash; raw phone and email
must not appear in lock names or logs. Duplicate detection and creation
idempotency are server-side.

---

# 12. Logging

Logs must not contain:

- passwords
- authentication tokens
- full payment credentials
- secrets
- unnecessary personal data

Useful operational logging includes:

- operation
- user
- order ID
- shift ID
- result
- error category
- timestamp

---

# 13. REST/AJAX

Controllers should follow:

```text
Authenticate
→ Authorize
→ Validate
→ Execute application service
→ Return stable response
```

Never put important business logic directly inside a controller callback.

---

# 14. Customer Display

Customer Display synchronization must avoid broadcasting sensitive staff/admin information.

Broadcast only data required for customer presentation.

Never broadcast the WordPress authentication cookie, WooCommerce session token,
REST nonce, or another credential. `pos_session_id` is an opaque correlation ID
only. Receivers must reject messages for a different session or an older
revision.

---

# 15. Direct Database Access

Avoid raw SQL where WordPress/WooCommerce APIs are sufficient.

When SQL is required:

- use prepared statements
- document the query purpose
- document table ownership
- consider caching/query cost

---

# 16. Security Failure Policy

On authorization or validation failure:

- do not perform the operation
- return a stable error
- do not leak internal implementation details

---

# 17. Security Review Gate

Before Phase 11 completion, review:

- capabilities
- nonces
- REST permission callbacks
- input validation
- output escaping
- SQL safety
- payment handling
- refund authorization
- stock adjustment authorization
- customer data exposure
- logs
## Phase 09 Shift Security

Shift identity and cashier ownership are resolved server-side. Open/close
mutations use a cashier-scoped database lock, monetary inputs are normalized,
notes are sanitized, and all derived totals come from WooCommerce transactions.
## Phase 10 Historical Order Security

History routes accept only CoffeePOS-created WooCommerce orders. Refundable
balance, state transitions, current product data, and cart reconstruction are
server-authoritative. Phase 10 originally required `manage_woocommerce` for
refund; Phase 12 replaces that interim check with
`coffeepos_refund_orders`. Refund and reorder use scoped locks and idempotency
IDs, and reorder independently requires `coffeepos_reorder_orders`.

## Phase 11 Reports and Phase-12 Capability Migration

Phase 11 verified the Reports screen, sales-report REST projection, CSV/XLSX
exports, refunds, and settings using `manage_woocommerce`. Phase 12 migrates
those operations respectively to `coffeepos_view_reports`,
`coffeepos_refund_orders`, and `coffeepos_manage_settings`, and replaces the
broad POS-access policy with the exact surface/operation capabilities in section
3. Customer Display remains limited to its documented session-scoped public
projection.

Report input is date/limit bounded, WooCommerce queries use supported APIs, and
money is never summed across currencies. Export downloads require the REST
nonce and permission callback, use safe filenames and MIME headers, escape CSV
formula-leading text, and contain no customer contact fields.
