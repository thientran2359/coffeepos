# CoffeePOS Staff Authentication and Routing

## 1. Purpose

This document defines Phase-12 staff entry, WordPress authentication, route
authorization, landing behavior, logout, and staff navigation capabilities.

CoffeePOS does not support PIN login. WordPress users, passwords, authentication
cookies, authentication hooks, and account lifecycle are authoritative.

## 2. Route Classes

| Route | Class | Required capability |
|---|---|---|
| `/pos/` | staff entry/login/landing | none to render; landing is capability-derived |
| `/pos/cashier/` | protected staff | `coffeepos_access_cashier` |
| `/pos/kds/` | protected staff | `coffeepos_access_kds` |
| `/pos/order-queue/` | protected staff | `coffeepos_access_order_queue` |
| `/pos/shifts/` | protected staff | `coffeepos_manage_own_shift` |
| `/pos/order-history/` | protected staff | `coffeepos_view_order_history` |
| `/pos/reports/` | protected staff | `coffeepos_view_reports` |
| `/pos/settings/` | protected staff settings | `coffeepos_manage_settings` |
| `/pos/customer/` | paired public projection | documented Customer Display session contract |

Trailing-slash variants resolve canonically without changing authorization.

## 3. `/pos/` Behavior

For an anonymous request, `/pos/` renders a PHP-owned CoffeePOS login screen.
For an authenticated request, it resolves the first authorized landing route in
this order:

```text
cashier → kds → order queue → shifts → order history → reports → settings
```

An authenticated user with no CoffeePOS screen capability receives a safe
no-access screen with a WordPress logout action. The user is never redirected to
a screen they cannot access.

## 4. Login Form Contract

The form posts to the same `/pos/` route using normal form transport and includes:

```text
log              WordPress username or email input
pwd              password input
rememberme       optional boolean
redirect_to      optional safe internal CoffeePOS staff URL
coffeepos_nonce  WordPress nonce for the login intent
```

The handler:

1. accepts POST only and verifies the nonce;
2. applies bounded input handling without logging credentials;
3. calls `wp_signon()` so normal WordPress authentication hooks apply;
4. returns a generic error for all authentication failures;
5. validates `redirect_to` against the current site's CoffeePOS protected-route
   allowlist and the authenticated user's route capability;
6. otherwise redirects to the first authorized landing route.

There is no `/coffeepos/v1/login` endpoint and no CoffeePOS authentication
token. Login rate limiting, password policy, two-factor authentication, and
other installed WordPress security hooks must not be bypassed.

## 5. Protected Route Contract

Anonymous protected-route requests redirect to `/pos/` with an encoded internal
return target. Authenticated users without the route capability receive a
controlled HTTP 403 page. The router must not render a partial application shell
or fall through to WordPress content.

The Customer Display is the only route in this table that does not use staff
authentication. Its `pos_session_id` remains a correlation/pairing identifier,
not staff authorization.

## 6. Logout

Staff navigation uses `wp_logout_url()` so WordPress supplies and verifies the
logout nonce. Successful logout redirects to `/pos/`. CoffeePOS does not clear
other users, revoke passwords, or implement a parallel logout endpoint.

## 7. Navigation Projection

The server renders only links whose exact route capabilities the current user
has. The component may expose staff display name, role labels for display, and
current-shift state. It must not expose WordPress auth cookies, nonces beyond the
required action, raw capability internals to Customer Display, or unauthorized
destinations.

Navigation filtering is usability only. Every route and REST action repeats its
server-side capability check.

## 8. Operation Capability Matrix

| Operation | Capability |
|---|---|
| Cashier catalog/cart/customer/coupon/checkout | `coffeepos_access_cashier` |
| KDS read/transition | `coffeepos_access_kds` |
| Queue read/transition | `coffeepos_access_order_queue` |
| Own shift open/read/close/history | `coffeepos_manage_own_shift` |
| Order History read/detail | `coffeepos_view_order_history` |
| Receipt projection/reprint | `coffeepos_reprint_receipts` |
| Quick reorder | `coffeepos_reorder_orders` |
| Cancel eligible order | `coffeepos_cancel_orders` |
| Refund eligible order | `coffeepos_refund_orders` |
| Report data/export | `coffeepos_view_reports` |
| CoffeePOS settings | `coffeepos_manage_settings` |

If an operation spans two surfaces, the operation-specific capability remains
the authority. For example, seeing Order History does not itself authorize a
refund or receipt reprint.

## 9. Stable Failures

```text
coffeepos_login_failed        generic login failure
coffeepos_login_nonce_invalid invalid/expired login intent
coffeepos_auth_required       missing/expired WordPress session
coffeepos_route_forbidden     authenticated user lacks route capability
coffeepos_action_forbidden    authenticated user lacks operation capability
coffeepos_no_staff_access     user has no CoffeePOS screen capability
```

Login failures must not disclose account existence. REST failures use the shared
API error envelope; HTML route failures use safe escaped templates.

## 10. Acceptance Contract

- WordPress credentials work through the normal WordPress auth stack.
- No PIN, custom password, staff credential table, or REST login is introduced.
- Anonymous staff routes return to `/pos/` login with only a safe return target.
- Every authenticated route and operation enforces its exact capability.
- Role bundles do not grant unrelated WordPress administration privileges.
- Login, no-access, protected screens, navigation, session expiry, and logout
  behave consistently on desktop and tablet.
- Customer Display remains isolated from staff authentication and navigation.
