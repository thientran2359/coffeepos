# API-ARCHITECTURE.md

# CoffeePOS API Architecture

## 1. Purpose

This document defines the transport and application API architecture for CoffeePOS.

The API layer connects:

```text
Cashier / Customer Display / KDS / Admin
        ↓
REST / AJAX / Sync Transport
        ↓
Application Services
        ↓
Domain + Infrastructure
        ↓
WordPress / WooCommerce / CoffeePOS Storage
```

---

# 2. API Principles

## 2.1 Controllers are thin

REST/AJAX controllers MUST:

1. authenticate
2. authorize
3. validate input
4. call an application service
5. serialize the result

Controllers MUST NOT contain large business workflows.

---

## 2.2 One business operation, one application service

For example:

```text
POST checkout
    ↓
CheckoutService
```

Do not implement checkout logic separately in:

- REST
- AJAX
- Cashier JavaScript
- Customer Display
- Admin

---

# 3. Transport Choice

Use WordPress REST API for:

- data retrieval
- structured application operations
- order operations
- customer lookup
- reports where appropriate
- KDS/order queue retrieval

Use WordPress AJAX only when there is a concrete WordPress/POS workflow reason.

Do not create both REST and AJAX endpoints for the same operation without a documented reason.

---

# 4. Namespace

Recommended REST namespace:

```text
/coffeepos/v1
```

Example:

```text
/wp-json/coffeepos/v1/products
```

---

# 5. Response Contract

Successful response:

```json
{
  "success": true,
  "data": {}
}
```

Error response:

```json
{
  "success": false,
  "error": {
    "code": "invalid_cart",
    "message": "The cart is no longer valid.",
    "details": {}
  }
}
```

The HTTP status should also communicate the failure category.

---

# 6. Error Categories

Recommended status mapping:

```text
400 invalid request
401 unauthenticated
403 unauthorized
404 not found
409 conflict
422 validation failure
429 rate limited
500 server/infrastructure failure
```

---

# 7. Error Codes

Stable error codes include:

```text
invalid_request
invalid_product
invalid_variation
invalid_cart
empty_cart
out_of_stock
invalid_coupon
invalid_customer
invalid_order_type
invalid_table
invalid_payment
payment_failed
payment_pending
order_creation_failed
order_not_found
refund_failed
shift_not_open
shift_already_closed
shift_not_found
unauthorized
```

---

# 8. Serialization Rule

API responses should expose application DTO/view data rather than raw WordPress/WooCommerce objects.

Example:

```json
{
  "id": 123,
  "name": "Latte",
  "type": "variable",
  "price": "45000",
  "stock_status": "instock"
}
```

Do not serialize entire WooCommerce objects into the frontend.

---

# 9. Authentication and Permissions

Every protected endpoint must define:

```text
authentication
permission_callback / authorization
input validation
```

The browser must not be trusted to determine access.

---

# 10. Idempotency

Operations that may create side effects should support safe repeated requests where practical.

High-risk operations:

```text
checkout
payment completion
refund
shift close
stock adjustment
```

The exact mechanism may use:

- request/client operation ID
- WooCommerce order state checks
- application-level conflict detection

Do not create duplicate orders because the cashier double-clicked Checkout.

---

# 11. Pagination

Collection endpoints should support documented pagination.

Examples:

```text
page
per_page
search
status
date_from
date_to
```

Do not load an unbounded historical order dataset into the browser.

---

# 12. Filtering

Filtering parameters should be validated against documented values.

Examples:

```text
category
search
status
order_type
date_from
date_to
shift_id
```

---

# 13. Money Representation

API money values should be serialized consistently as decimal strings.

Example:

```json
{
  "amount": "85000.00"
}
```

Do not use binary floating-point values as the API contract for money.

---

# 14. Timestamps

Use a consistent machine-readable timestamp format in API responses.

UI is responsible for localized display.

---

# 15. Versioning

The initial namespace is:

```text
coffeepos/v1
```

Breaking response changes require a version change or explicit compatibility strategy.

---

# 16. Client Responsibilities

The frontend:

- sends valid requests
- shows loading state
- handles errors
- renders responses
- maintains temporary UI state

The frontend MUST NOT:

- authorize itself
- determine payment success
- trust client totals
- bypass validation

---

# 17. Application Boundary

Example:

```text
REST Controller
    ↓
CheckoutService
    ↓
CartValidator
    ↓
WooCommerceOrderGateway
    ↓
PaymentGateway
```

The actual class names may differ, but responsibilities must remain separated.
