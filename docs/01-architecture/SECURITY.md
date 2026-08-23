# SECURITY.md

# CoffeePOS Security Architecture

## 1. Principle

CoffeePOS is a privileged operational application.

Every server-side operation must assume the client may be malicious or incorrect.

---

# 2. Authentication

All protected POS operations require an authenticated WordPress user unless a documented public customer-display endpoint explicitly requires otherwise.

---

# 3. Authorization

Capabilities MUST be checked server-side.

The baseline feature specification identifies:

- `manage_woocommerce`
- `view_admin_dashboard`

as capabilities relevant to POS access.

The final capability matrix must be defined before production release.

Never rely only on route visibility or JavaScript guards.

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
server-authoritative. Refund additionally requires `manage_woocommerce`;
refund and reorder use scoped locks and idempotency IDs.

## Phase 11 Reports and Final Capability Matrix

The Reports screen, sales-report REST projection, and CSV/XLSX exports require
`manage_woocommerce`. The existing POS-access policy remains sufficient for
Cashier, KDS, Order Queue, Shifts, and non-refund Order History operations.
Refund and CoffeePOS settings also require `manage_woocommerce`. Customer
Display remains limited to its documented session-scoped public projection.

Report input is date/limit bounded, WooCommerce queries use supported APIs, and
money is never summed across currencies. Export downloads require the REST
nonce and permission callback, use safe filenames and MIME headers, escape CSV
formula-leading text, and contain no customer contact fields.
