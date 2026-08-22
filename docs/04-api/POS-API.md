# POS-API.md

# CoffeePOS POS API

## 1. Purpose

This document defines APIs for cashier/product/customer/cart-oriented operations.

The API is designed around application use cases rather than arbitrary database CRUD.

---

# 2. Product Endpoints

## GET /coffeepos/v1/products

Purpose:

Retrieve products for the cashier menu.

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
```

`customer_not_found` should be a controlled response, not a server exception.

---

# 7. Cart Operations

The browser may keep a local active-cart state, but server operations that require authoritative validation should use application endpoints.

Potential endpoint:

## POST /coffeepos/v1/cart/validate

Purpose:

Validate current cart before checkout.

Request:

```json
{
  "cart": {
    "items": [],
    "customer": {},
    "order_type": "takeaway",
    "table": null,
    "coupon_codes": []
  }
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
  "cart": {},
  "code": "SUMMER10"
}
```

The server validates using WooCommerce.

Response contains an updated trusted cart projection.

---

## DELETE /coffeepos/v1/cart/coupon

Purpose:

Remove a coupon from the current cart context.

---

# 10. Table Endpoint

## GET /coffeepos/v1/tables

Purpose:

Retrieve selectable service tables when table management is enabled.

The initial requirements do not define a complete table-management subsystem.

Do not build advanced table CRUD unless required.

---

# 11. Held Cart APIs

## POST /coffeepos/v1/held-carts

Request:

```json
{
  "label": "Customer Nguyen",
  "cart": {}
}
```

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

Returns a cart projection suitable for restoring into the Cashier UI.

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
