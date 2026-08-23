# POS-API.md

# CoffeePOS POS API

## 1. Purpose

This document defines APIs for cashier/product/customer/cart-oriented operations.

The API is designed around application use cases rather than arbitrary database CRUD.

---

# 2. Product Endpoints

## GET /coffeepos/v1/catalog

Purpose:

Return the complete POS-visible `CatalogView` grouped and ordered by
WooCommerce category for Cashier and Customer Display.

Response fields and grouping rules are defined in
`docs/03-ui/CATALOG-UI.md`. The response includes a catalog version, currency,
ordered categories, and display-ready product projections using WooCommerce
price and availability.

This is the primary catalog endpoint for both screens. Category clicks and
Cashier search operate locally on this complete projection and do not trigger
filtered product requests.

---

## GET /coffeepos/v1/products

Purpose:

Provide a generic paged product query for use cases that need server-side
filtering. It is not the primary Cashier/Customer Display catalog source.

Query parameters:

```text
search
category
page
per_page
status
```

Response concept:

```json
{
  "success": true,
  "data": {
    "items": [],
    "pagination": {
      "page": 1,
      "per_page": 30,
      "total": 0,
      "pages": 0
    }
  }
}
```

Each product item may contain:

```text
id
name
type
price
image
stock_status
stock_quantity where allowed
categories
has_variations
```

---

# 3. Product Detail

## GET /coffeepos/v1/products/{id}

Purpose:

Load the product configuration required by Product Modal.

Response may include:

```text
id
name
type
image
description where appropriate
base price
stock
attributes
variations
modifier configuration
quick notes configuration
```

The exact response must expose only the data needed by the cashier.

Modifier configuration contains selection rules and display data only. It does
not contain a CoffeePOS price adjustment. Price-changing choices are represented
by WooCommerce variations.

---

# 4. Category Endpoint

## GET /coffeepos/v1/categories

Purpose:

Retrieve available product categories.

Response:

```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": 12,
        "name": "Coffee",
        "slug": "coffee",
        "count": 10
      }
    ]
  }
}
```

---

# 5. Customer Lookup

## GET /coffeepos/v1/customers/lookup

Query:

```text
phone
```

Response when found:

```json
{
  "success": true,
  "data": {
    "customer": {
      "id": 123,
      "name": "Customer",
      "phone": "0900000000"
    }
  }
}
```

If membership support exists, membership projection may be included.

Do not expose unnecessary customer information.

---

# 6. Customer Lookup Errors

Examples:

```text
invalid_customer_phone
customer_not_found
customer_lookup_failed
customer_phone_ambiguous
```

`customer_not_found` should be a controlled response, not a server exception.

## PUT /coffeepos/v1/cart/customer

Attaches a trusted WooCommerce customer to the active cart. The request contains
`pos_session_id`, `expected_revision`, and `customer_id`. The server reloads the
customer and ignores client-supplied customer display data.

## DELETE /coffeepos/v1/cart/customer

Returns the active cart to guest mode using `pos_session_id` and
`expected_revision`.

Both operations increment the cart revision and return the full `CartView`.

---

# 7. Cart Operations

The active cart is authoritative server-side state in the WooCommerce session.
The browser keeps only the latest returned projection.

Every cart request is authenticated through the normal WordPress/WooCommerce
session and identifies the logical cart with `pos_session_id`. This opaque ID is
not a credential. Mutations also provide `expected_revision`.

## POST /coffeepos/v1/cart/session

Creates a new empty logical cart in the current WooCommerce session.

Response:

```json
{
  "success": true,
  "data": {
    "cart": {
      "pos_session_id": "01J...",
      "revision": 0,
      "items": [],
      "currency": "VND"
    }
  }
}
```

The returned identifier may be placed in the Customer Display URL and channel
name, but the WooCommerce session token must never be exposed.

## GET /coffeepos/v1/cart

Returns the current canonical projection for `pos_session_id`. Cashier and
Customer Display use this endpoint for initial state and recovery.

Query:

```text
pos_session_id
```

An unknown or expired ID returns `cart_session_not_found`; it must not create a
new empty cart implicitly.

## POST /coffeepos/v1/cart/items

Request:

```json
{
  "pos_session_id": "01J...",
  "expected_revision": 4,
  "product_id": 123,
  "variation_id": 456,
  "quantity": 2,
  "modifiers": {},
  "quick_notes": [],
  "custom_note": ""
}
```

The server ignores any client price, resolves the current WooCommerce
product/variation price, validates configuration and stock, mutates the session
cart, increments `revision`, and returns the full cart projection.

## PATCH /coffeepos/v1/cart/items/{key}

Updates quantity or configuration using `pos_session_id` and
`expected_revision`. Price is re-resolved from WooCommerce.

## DELETE /coffeepos/v1/cart/items/{key}

Removes one item and returns the incremented cart projection.

## DELETE /coffeepos/v1/cart

Clears the logical cart after confirmation and returns the empty incremented
projection. It does not destroy the surrounding WooCommerce session.

## POST /coffeepos/v1/cart/validate

Purpose:

Load and validate the current session cart before checkout.

Request:

```json
{
  "pos_session_id": "01J...",
  "expected_revision": 5
}
```

Response:

```json
{
  "success": true,
  "data": {
    "cart": {},
    "validation": {
      "valid": true,
      "errors": []
    }
  }
}
```

If `expected_revision` is stale, mutation/validation endpoints return HTTP 409
with `cart_revision_conflict` and the latest cart projection or a documented
link to retrieve it.

---

# 8. Cart Validation

Server validation should verify:

```text
product exists
variation belongs to product
variation is valid
required options are valid
quantity is valid
stock is sufficient
coupon is valid
customer context is valid
order type is valid
table is valid when required
```

---

# 9. Coupon

## POST /coffeepos/v1/cart/coupon

Request:

```json
{
  "pos_session_id": "01J...",
  "expected_revision": 5,
  "code": "SUMMER10"
}
```

The server validates using WooCommerce.

Response contains an updated trusted cart projection.

---

## DELETE /coffeepos/v1/cart/coupon

Purpose:

Remove a coupon from the current cart context.

The request uses `pos_session_id` and `expected_revision`; the response returns
the incremented canonical cart projection.

---

# 10. Table Endpoint

## GET /coffeepos/v1/tables

Purpose:

Retrieve selectable service tables when table management is enabled.

The initial requirements do not define a complete table-management subsystem.

Do not build advanced table CRUD unless required.

## PUT /coffeepos/v1/cart/service-context

Atomically sets the service context using `pos_session_id`,
`expected_revision`, `order_type`, and, for dine-in, `table_id`.

`dine_in` requires a valid enabled table. `takeaway` clears table context in the
same mutation. The server resolves the table label and returns the full
incremented `CartView`.

---

# 11. Held Cart APIs

## POST /coffeepos/v1/held-carts

Request:

```json
{
  "label": "Customer Nguyen",
  "pos_session_id": "01J...",
  "expected_revision": 9
}
```

The server loads the active cart from the WooCommerce session. It does not
accept a browser-owned cart snapshot as suspended-cart data.

Response:

```json
{
  "success": true,
  "data": {
    "held_cart": {
      "id": 10,
      "label": "Customer Nguyen",
      "created_at": "2026-08-22T12:00:00Z"
    }
  }
}
```

---

## GET /coffeepos/v1/held-carts

Returns held carts belonging to the authorized cashier/user according to project policy.

---

## GET /coffeepos/v1/held-carts/{id}

Returns the selected suspended cart.

---

## POST /coffeepos/v1/held-carts/{id}/resume

Restores the payload into a logical WooCommerce session cart and returns its
`pos_session_id`, new revision, and canonical projection after WooCommerce price
and stock revalidation.

---

## DELETE /coffeepos/v1/held-carts/{id}

Deletes the held cart.

---

# 12. Stock Adjustment

## POST /coffeepos/v1/stock-adjustments

Request:

```json
{
  "product_id": 123,
  "variation_id": 0,
  "quantity_delta": -1,
  "reason": "Damaged"
}
```

Server must:

- authorize
- validate product/variation
- validate delta
- update stock through WooCommerce mechanisms
- preserve audit context where required

The UI is not authoritative.

---

# 13. Cart Response Projection

A cart projection should consistently expose:

```text
pos_session_id
revision
updated_at
currency
items
subtotal
discount
total
customer
order_type
table
coupon
validation
```

Each cart item:

```text
key
product
variation
quantity
configuration
note
unit_price
line_total
```

The final pricing fields should be server-derived when the API is authoritative.

All item prices and totals are resolved from WooCommerce product/variation and
coupon data. Client-supplied prices and modifier price adjustments are ignored.

---

# Phase-05 Coupon Operations

```text
GET    /coffeepos/v1/coupons/applicable?pos_session_id={id}
POST   /coffeepos/v1/cart/coupon
DELETE /coffeepos/v1/cart/coupon
```

The list returns safe `{code,label}` fields only. Mutations require
`pos_session_id`, `expected_revision`, and `code`, delegate eligibility and
discount calculation to WooCommerce, increment the single cart revision once,
and return the complete canonical `CartView`. The client never submits a
discount or total.
