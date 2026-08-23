# CoffeePOS Phase 12 — Staff Access, Workflow Polish & Receipt Completion

## 1. Objective

Phase 12 completes the staff-facing daily workflow before further production,
loyalty, hardware, and payment work. Staff sign in at `/pos/` with their normal
WordPress account, are authorized through granular CoffeePOS capabilities, and
navigate every permitted POS surface through one shared component.

The phase also completes administrator-configurable item quick notes, adds a
separate order-level note, and replaces the blank/incomplete print path with one
authoritative browser receipt flow.

The exit condition is a tested WordPress-account staff entry flow with no PIN or
parallel credential store, exact server-side permission enforcement, usable
staff navigation, distinct item/order notes, and complete receipts from every
documented print entry point.

## 2. Prerequisites

- Phase 03 revisioned server-authoritative cart and Cashier shell
- Phase 05 checkout, payment, WooCommerce order, and receipt endpoints
- Phase 06 Customer Display privacy and synchronization boundary
- Phase 07 KDS and Order Queue projections and transitions
- Phase 08 member/guest customer projection
- Phase 09 Shift Management
- Phase 10 Order History, reprint, cancel, refund, and reorder
- Phase 11 Reports and production-hardening evidence
- WordPress user, role, capability, auth-cookie, nonce, and logout APIs

Before implementation, read the Phase-12 changes in `REQUIREMENTS.md`,
`ROADMAP.md`, `ARCHITECTURE.md`, `DOMAIN-MODEL.md`, `STATE-MACHINES.md`,
`DATA-FLOW.md`, `SECURITY.md`, `DATABASE.md`, `WOOCOMMERCE-DATA.md`,
`UI-ARCHITECTURE.md`, `COMPONENTS.md`, `CASHIER-UI.md`, `KDS-UI.md`,
`ADMIN-UI.md`, `API-ARCHITECTURE.md`, `AUTH-ROUTING.md`, `POS-API.md`, and
`ORDER-API.md`.

## 3. In Scope

- `/pos/` login, authorized landing, no-access, and logout experience
- WordPress username/email and password authentication through `wp_signon()`
- granular CoffeePOS capabilities and default WordPress role bundles
- migration of routes, REST permission callbacks, and allowed-action projections
  away from broad interim access checks
- shared capability-aware navigation on every staff screen
- administrator-managed item quick-note definitions and Phase-12 defaults
- multiple structured quick-note selections plus separate item free text
- one revisioned private order note per cart/order
- KDS, Queue, History, checkout, and receipt note projections as specified
- one authoritative receipt projection/template for print and reprint
- browser print styling and blank-print prevention
- activation/migration, security, regression, and real-browser verification
- documentation alignment for every changed capability, route, setting, field,
  metadata key, projection, and error

## 4. Out of Scope

- PIN login or PIN fallback
- CoffeePOS-owned usernames, passwords, password hashes, login tokens, staff
  sessions, or employee account table
- replacing the native WordPress Users screen
- granting CoffeePOS managers broad WordPress user-management permissions
- OAuth, SSO, biometric, badge, or device login
- changing WordPress password reset or two-factor flows
- customer-facing order notes
- note-driven price adjustments
- loyalty rewards, tiers, buy-five-get-one, or member coupons (Phase 14)
- physical printer drivers, USB/Bluetooth/LAN discovery, cash drawer, or kitchen
  printer transport (Phase 15)
- automated bank-transfer confirmation or provider callbacks (Phase 15)
- redesigning existing checkout, Customer Display payment, KDS, Queue, Shift,
  History, or Reports business rules outside this phase

## 5. Authentication Source of Truth

WordPress is authoritative for users, credentials, password policy,
authentication hooks, auth cookies, account status, and logout. CoffeePOS calls
supported WordPress APIs and MUST NOT compare passwords, create its own password
hash, store a PIN, or issue an independent auth token.

The `/pos/` form uses normal server form submission, a WordPress nonce, and
`wp_signon()`. There is no REST login endpoint. All authentication failures use
a generic message. Credentials and authentication cookies never enter logs,
BroadcastChannel messages, Customer Display projections, or CoffeePOS storage.

Installed WordPress authentication/security plugins must remain able to
participate through normal hooks. CoffeePOS must not claim compatibility with a
particular two-factor challenge until its real flow is tested, but it must not
bypass or replace that flow.

## 6. Roles and Capability Matrix

Roles are default bundles for setup convenience. Exact capabilities authorize
routes and operations.

| Capability | Meaning |
|---|---|
| `coffeepos_access_cashier` | Cashier screen and normal cart/checkout workflow |
| `coffeepos_access_kds` | KDS screen, read model, and KDS transitions |
| `coffeepos_access_order_queue` | Queue screen, read model, and queue transitions |
| `coffeepos_manage_own_shift` | Current user's shift open/read/close/history |
| `coffeepos_view_order_history` | CoffeePOS order list and detail |
| `coffeepos_reprint_receipts` | Receipt projection and print/reprint |
| `coffeepos_reorder_orders` | Quick reorder into a new cart |
| `coffeepos_cancel_orders` | Eligible order cancellation |
| `coffeepos_refund_orders` | Eligible WooCommerce refunds |
| `coffeepos_view_reports` | Reports, report data, CSV, and XLSX |
| `coffeepos_manage_settings` | CoffeePOS settings |

Default grants:

| Role | Cashier | KDS | Queue | Own shift | History | Reprint | Reorder | Cancel | Refund | Reports | Settings |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| CoffeePOS Cashier | ✓ |  |  | ✓ | ✓ | ✓ | ✓ |  |  |  |  |
| CoffeePOS Kitchen |  | ✓ | ✓ |  |  |  |  |  |  |  |  |
| CoffeePOS Supervisor | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |  |  |  |
| CoffeePOS Manager | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

Administrator and Shop Manager receive all CoffeePOS capabilities during the
Phase-12 migration so existing administrators retain access. CoffeePOS roles do
not receive unrelated privileges such as `manage_woocommerce`, `edit_users`, or
`promote_users`. Native WordPress user-management capabilities remain required
to create users, reset passwords, and assign roles.

An authenticated user's role name is never the operation check. A user may
receive a custom set of CoffeePOS capabilities through WordPress and must then
see and access exactly that set.

## 7. Capability Registration and Migration

Registration is versioned and idempotent:

1. create/update the four default roles through WordPress role APIs;
2. add the exact current capability set to each bundle;
3. add all CoffeePOS capabilities to Administrator and Shop Manager;
4. migrate every protected CoffeePOS route, REST permission callback,
   allowed-action flag, and settings action to the capability in this document;
5. retain safe denial when a user lacks the new capability;
6. store no per-user duplicate permission record.

Normal deactivation must not delete roles/capabilities and unexpectedly mutate
existing users. Uninstall cleanup follows the documented plugin data-deletion
policy. A migration failure must be visible to administrators and must not
silently grant broader access.

## 8. Staff Entry and Route Contract

`AUTH-ROUTING.md` is authoritative. Summary:

- anonymous `/pos/` renders the PHP-owned login screen;
- authenticated `/pos/` redirects to the first permitted route in this order:
  Cashier, KDS, Order Queue, Shifts, Order History, Reports;
- anonymous protected routes redirect to `/pos/` with an allowlisted same-site
  CoffeePOS return target;
- authenticated users without the route capability receive a controlled 403;
- users with no screen capability see a safe no-access state and logout;
- `/pos/customer/` remains a paired public projection and never becomes a staff
  login target;
- logout uses `wp_logout_url()` and returns to `/pos/`.

Open redirects, arbitrary `redirect_to` values, a partial unauthorized shell,
or a fallback into another WordPress page are forbidden.

## 9. Login UI Contract

The PHP-owned login template includes CoffeePOS/store branding, semantic
username/email and password fields, optional remember-me, submit button, generic
error region, and accessible focus behavior. It defines:

```text
idle
submitting
authentication_error
nonce_error
no_access
```

The form works without a JavaScript credential request. JavaScript may prevent
accidental duplicate submission but may not authenticate, store, or log the
credentials. Password managers and keyboard-only use must work.

## 10. Shared Staff Navigation

Cashier, KDS, Order Queue, Shifts, Order History, and Reports use one PHP-owned
Staff Navigation component. It shows only permitted routes, identifies the
current route, displays the staff display name, may show current shift state,
and includes WordPress logout. A Settings destination may point to the native
CoffeePOS admin settings page only for `coffeepos_manage_settings` users.

The component supports desktop and tablet compact/drawer layouts, visible focus,
logical focus order, Escape-to-close where a drawer is used, and no hidden
keyboard trap. Login and Customer Display do not render it.

Menu filtering is not authorization. Direct routes, REST data, exports, receipt,
refund, cancel, and reorder repeat exact server checks.

## 11. Item Quick Notes

Phase 12 continues the existing `coffeepos_quick_notes` option and does not add
a parallel note system. Default definitions are:

| Stable ID | Default label |
|---|---|
| `less_sugar` | Ít đường |
| `extra_sugar` | Nhiều đường |
| `less_milk` | Ít sữa |
| `less_ice` | Ít đá |

An administrator with `coffeepos_manage_settings` may edit label, enabled state,
sort order, and optional product/category applicability. Stable IDs are not
silently changed when labels change. Quick notes never change price.

The item modal renders enabled/applicable definitions as multi-select chips.
Selections remain a structured stable-ID array. The item free-text textarea is a
separate field; chip labels are not concatenated into it. Edit mode restores the
two values independently. The server rejects disabled, unknown, duplicate, or
inapplicable IDs with the existing configuration-validation boundary.

At order creation, `_coffeepos_quick_notes` stores stable IDs and captured labels
using the Phase-12 schema in `DATABASE.md`. Readers accept the old string-ID
array. KDS, Queue, History, and Receipt display safely captured labels. Reorder
extracts IDs and revalidates current configuration; invalid historical choices
are reported according to the existing reorder contract rather than trusted.

## 12. Order-Level Note

The cart aggregate adds one `order_note`, separate from every item's custom note
and quick notes. It is private staff text, maximum 2000 characters, sanitized
with WordPress textarea rules, revisioned with the server-side cart, and cleared
when a new empty cart is started.

Endpoints:

```text
PUT    /coffeepos/v1/cart/order-note
DELETE /coffeepos/v1/cart/order-note
```

Both require `pos_session_id`, `expected_revision`, and
`coffeepos_access_cashier`, and return the complete updated Cashier CartView.
`PUT` also accepts `note`. Invalid content returns `invalid_order_note`. Stale
revision handling remains the established full-CartView reconciliation flow.

The order note is included in staff CartView and checkout server state, but
excluded from CustomerCartView, customer REST recovery, and BroadcastChannel
messages. Checkout persists it through WooCommerce CRUD as private order meta
`_coffeepos_order_note`.

KDS displays it once in the order header; Queue and History detail display it as
order context. Item notes remain on their line items. Quick reorder does not copy
the historical order note. Receipt includes it only when the administrator has
enabled `coffeepos_receipt_print_order_note` (default false).

## 13. Authoritative Receipt Contract

`GET /coffeepos/v1/orders/{id}/receipt` remains the single ReceiptView source and
requires `coffeepos_reprint_receipts`. It is built from WooCommerce order/order
items and documented CoffeePOS metadata, never from stale cart DOM or browser
totals.

Required projection:

```text
store name and address
order ID/number
created time in WordPress timezone
cashier display name
customer-safe identity
order type and table
items, variation, modifiers, quick notes, custom item note
quantity, unit amount, line total
subtotal, discount, refund, total, currency
payment method
cash received and change when recorded
order note only when enabled
```

Checkout print, Queue reprint, and History reprint load the same endpoint and
populate the same PHP-owned receipt template. The client may bind text and
attributes through the shared TemplateRenderer but may not construct receipt
markup with JavaScript strings.

The print action remains disabled/pending until a successful, complete
ReceiptView is bound. Missing/forbidden/failed data shows a recoverable error and
MUST NOT invoke `window.print()`. Repeated prints reuse/reset the same receipt
host without duplicate markup. `afterprint` restores the application state.

Print CSS hides all non-receipt application content, defines sensible `@page`
rules, preserves readable item/totals grouping, and supports browser/default,
58 mm-friendly, and 80 mm-friendly layouts. Selecting or transporting to a
physical printer remains Phase 15.

## 14. API and Projection Changes

Phase 12 adds no credential REST API. It adds the two revisioned order-note cart
mutations and extends authorized staff projections with `order_note`.

Existing route/action permission callbacks migrate as follows:

```text
Cashier/cart/checkout      coffeepos_access_cashier
KDS                       coffeepos_access_kds
Order Queue               coffeepos_access_order_queue
own shifts                coffeepos_manage_own_shift
History list/detail       coffeepos_view_order_history
receipt                   coffeepos_reprint_receipts
reorder                   coffeepos_reorder_orders
cancel                    coffeepos_cancel_orders
refund                    coffeepos_refund_orders
reports/exports           coffeepos_view_reports
settings                  coffeepos_manage_settings
```

Allowed-action projections use the same exact checks. A list/detail response may
show an order while independently returning `can_refund=false`,
`can_cancel=false`, `can_reorder=false`, or `can_reprint=false`.

## 15. Settings and Persistence

Phase 12 uses:

```text
coffeepos_quick_notes                 existing option, normalized definitions
coffeepos_receipt_print_order_note    new boolean option, default false
_coffeepos_order_note                 new private WooCommerce order meta
_coffeepos_quick_notes                version-compatible order-item meta
```

No new custom database table is permitted. WordPress users/roles/capabilities,
the WooCommerce session, WooCommerce order/order-item CRUD, and WordPress Options
remain the approved owners.

## 16. Security and Privacy Rules

- Generic login errors prevent username/email enumeration.
- Login and logout nonces are verified; REST requests retain normal REST nonce
  and auth-cookie protection.
- Return targets are same-site allowlisted CoffeePOS staff routes.
- Every route, endpoint, export, and mutation checks an exact server capability.
- Role labels, navigation visibility, allowed-action buttons, and client state
  are never trusted as permission.
- Passwords, auth cookies, nonces, private notes, and raw capabilities are never
  logged or broadcast.
- Order and item notes are sanitized on input and escaped/bound as text on every
  staff and receipt output.
- Customer Display never receives the private order note or staff navigation.
- Receipt customer information remains the approved safe display projection;
  it does not expose unnecessary billing data.

## 17. Stable Errors

```text
coffeepos_login_failed
coffeepos_login_nonce_invalid
coffeepos_auth_required
coffeepos_route_forbidden
coffeepos_action_forbidden
coffeepos_no_staff_access
invalid_order_note
receipt_not_available
receipt_render_failed
```

Existing cart revision, order not found, validation, refund, cancel, reorder,
and report errors remain unchanged. Errors follow the shared safe envelope for
REST or escaped HTML state for server routes.

## 18. Implementation Order

1. Add versioned roles/capabilities and migration tests.
2. Centralize route/operation capability mapping and migrate permission checks.
3. Implement `/pos/` login, safe return/landing, 403/no-access, session expiry,
   and logout behavior.
4. Add the shared staff-navigation PHP component to all staff screens.
5. Complete quick-note admin normalization, server validation, item edit, and
   backward-compatible historical serialization.
6. Add revisioned order-note domain/session/API/UI/order persistence and staff
   projections while confirming Customer Display exclusion.
7. Implement the shared authoritative receipt projection/template/print CSS and
   wire all three print entry points.
8. Run automated, WordPress/WooCommerce runtime, real-browser, privacy, and
   regression acceptance.

Do not partially migrate authorization so one path still relies only on
`manage_woocommerce` or a generic logged-in check.

## 19. Required Verification

### Automated and static

- PHP syntax for every changed PHP file
- JavaScript syntax for every changed JavaScript file
- existing Phase 01–11 affected regression suites
- role/capability registration and idempotent migration scenarios
- route map and every REST permission callback for anonymous, each default role,
  Administrator, Shop Manager, and a custom-capability user
- allowed-action projections independently matching operation capabilities
- safe return-target/open-redirect tests
- generic login error and nonce-failure tests without credential logging
- order-note length, sanitization, revision conflict, clear, checkout, new-cart,
  Customer Display exclusion, and reorder-exclusion tests
- quick-note defaults, admin normalization, applicability, multi-select edit,
  legacy string-array reads, captured-label writes, and reorder revalidation
- receipt projection completeness, authorization, escaping, optional order note,
  repeat-print host, and no-print-on-error tests

### WordPress/WooCommerce runtime

- real `wp_signon()` auth-cookie session and logout
- HPOS and supported classic order storage for order/order-item metadata
- activation and upgrade with existing Administrator/Shop Manager accounts
- existing Customer Display paired public flow remains functional
- checkout, KDS, Queue, Shifts, History, refunds, reorder, and Reports retain
  their prior authoritative business behavior under the new capabilities

### Real browser

- anonymous `/pos/`, wrong credentials, valid credentials, remember-me, logout,
  expired session, safe return route, no-access, and forbidden direct route
- each role sees only expected navigation/screens/actions
- desktop and tablet navigation, keyboard focus, drawer behavior, and logout
- item quick-note select/edit plus independent free-text item note
- order note save/clear/error, KDS/Queue/History display, and Customer Display
  non-disclosure
- checkout print, Queue reprint, and History reprint with complete content
- print cancel/repeat, print error, long names/notes, discount/refund, cash/change,
  bank transfer, member/guest, dine-in/table, and takeaway
- browser/default, 58 mm-friendly, and 80 mm-friendly print preview

Do not claim browser or authentication acceptance unless it was performed in the
real WordPress/WooCommerce environment.

## 20. Acceptance Criteria

- [ ] `/pos/` presents a WordPress-account login for anonymous users.
- [ ] CoffeePOS contains no PIN flow, credential copy, custom staff session, or
      REST login endpoint.
- [ ] WordPress authentication hooks, cookies, account state, and logout remain
      authoritative.
- [ ] Safe landing, return target, no-access, forbidden route, session expiry,
      and logout behavior match `AUTH-ROUTING.md`.
- [ ] All four default roles and all granular capabilities are registered and
      migrated idempotently.
- [ ] Administrator and Shop Manager retain CoffeePOS access without giving
      CoffeePOS roles unrelated WordPress administration capabilities.
- [ ] Every route, endpoint, export, and allowed action enforces its exact
      capability server-side.
- [ ] One shared accessible staff navigation appears on all and only staff
      application screens.
- [ ] Admin-configured quick-note chips include the four approved defaults,
      support multiple selections, and remain separate from item free text.
- [ ] Historical quick-note metadata remains readable and new orders capture IDs
      and labels without making labels primary identifiers.
- [ ] The revisioned order note remains separate from item notes, persists on the
      WooCommerce order, and appears only in approved staff projections.
- [ ] Customer Display and quick reorder never receive/copy the private order
      note.
- [ ] Checkout, Queue, and History use one complete authoritative ReceiptView and
      PHP-owned receipt template.
- [ ] A missing, loading, forbidden, or failed receipt never opens a blank print
      dialog.
- [ ] Print content is safe, responsive to supported paper widths, repeatable,
      and restores the application after printing.
- [ ] Prior-phase critical workflows pass affected regression and real-browser
      acceptance under the new permission model.

## 21. Definition of Done

Phase 12 is complete only when documentation, implementation, migration,
automated checks, WordPress/WooCommerce runtime verification, and real-browser
acceptance agree. No protected route or operation may depend only on navigation
visibility, role name, login state, client values, or the old broad permission
baseline. No blank receipt, credential duplication, private-note Customer
Display leak, or critical prior-phase regression may remain open.

Completion authorizes planning Phase 13. It does not implement Phase 13, Phase
14 loyalty benefits, or Phase 15 hardware/payment integrations.
