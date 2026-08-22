# COMPONENTS.md

# CoffeePOS Component Contracts

## 1. Purpose

This document defines reusable UI components and behavior hooks.

Components should be reused rather than independently recreated on each screen.

---

# 2. Product Card

## Purpose

Represent one sellable WooCommerce product in the cashier grid.

## Required display

Potential data:

```text
image
name
price
stock state
variation indicator
```

## Actions

```text
select product
```

## Hooks

Conceptual:

```html
<article
    data-component="product-card"
    data-product-id="123"
>
```

## States

```text
normal
hover
selected
out_of_stock
loading
```

Clicking an out-of-stock product must not add it directly to the cart.

---

# 3. Category Navigation

## Purpose

Filter products by category.

Baseline categories include:

```text
All
Drinks
Food
Coffee
...
```

The implementation must load actual WooCommerce categories rather than hard-code all categories.

## State

```text
active
inactive
loading
empty
```

---

# 4. Search Input

## Purpose

Live product search.

Behavior:

- debounce user input
- show loading state
- update product grid
- support empty results

Avoid sending a request for every keystroke.

---

# 5. Product Configuration Modal

## Purpose

Configure a product before adding/updating a cart item.

## Sections

```text
Header
Product information
Variation attributes
Modifier groups
Quantity
Quick notes
Custom note
Footer
```

## Modes

```text
add
edit
```

## Actions

```text
cancel
add_to_cart
update_cart_item
```

## Validation

Required variation selections must be complete.

---

# 6. Variation Selector

## Purpose

Choose WooCommerce variation attributes.

The UI must be generated from actual product attributes.

Do not assume a fixed attribute such as `size`.

## State

```text
unselected
selected
disabled
unavailable
```

---

# 7. Modifier Group

## Purpose

Represent an optional/configurable group.

Example:

```text
Milk
  Regular
  Oat
  Soy
```

The exact modifier source and rules are defined by the database/domain documentation.

---

# 8. Quantity Control

Required controls:

```text
decrease
quantity display
increase
```

Optional direct input may be added later.

Rules:

- quantity must remain positive for a cart item
- prevent accidental rapid double updates where necessary

---

# 9. Quick Notes

Examples from the feature baseline:

```text
Ít đá
Không đá
Ít ngọt
Không đường
Nhiều sữa
Mang về
```

Quick notes must have stable IDs.

Labels may be localized.

---

# 10. Custom Note

A textarea for free-form item notes.

Rules:

- sanitize server-side
- display safely in cart/KDS/receipt where appropriate
- keep it attached to the order item

---

# 11. Cart Item

## Display

```text
product name
variation summary
modifier summary
note
unit price
quantity
line total
```

## Actions

```text
increase
decrease
edit
remove
```

---

# 12. Cart Summary

Display:

```text
subtotal
discount
total
```

Optional:

```text
coupon
tax
fees
```

The exact total breakdown depends on WooCommerce configuration.

The browser display is not authoritative.

---

# 13. Order Type Selector

Values:

```text
Dine-in
Takeaway
```

Dine-in opens/selects table context.

---

# 14. Customer Lookup

## Input

Phone number.

## States

```text
idle
searching
found
not_found
error
```

## Results

Customer summary may include:

```text
name
phone
membership status
points if supported
```

---

# 15. Table Selector

## Purpose

Select table for dine-in orders.

The initial scope requires selection of a service table but does not define a full table-management application.

---

# 16. Coupon Selector

Supports:

```text
select available coupon
enter coupon code
apply
remove
```

Coupon validation is server-side.

---

# 17. Hold Cart Dialog

Allows:

```text
label/name
confirm hold
cancel
```

The current cart becomes suspended only after successful server persistence.

---

# 18. Held Cart List

Displays:

```text
label
created time
cashier/user
```

Actions:

```text
resume
delete
```

---

# 19. Checkout Modal

Conceptual flow:

```text
Order Summary
→ Payment Method
→ Payment Details
→ Confirmation
```

Supported baseline methods:

```text
cash
bank transfer
```

---

# 20. Cash Payment Panel

Displays:

```text
total
amount received
change
```

Quick values:

```text
+10k
+20k
+50k
+100k
+200k
+500k
Exact
```

The actual change calculation must match server validation.

---

# 21. Bank Transfer Payment Panel

Displays:

```text
amount
VietQR
payment status
```

The QR must represent the current order/payment amount.

---

# 22. Payment Success Dialog

Should show:

```text
order number
total
payment method
change if applicable
```

Actions may include:

```text
print receipt
new order
```

---

# 23. KDS Card

Displays:

```text
order number
elapsed time
items
quantities
variations
important notes
status
```

Quick actions:

```text
start
ready
complete
```

Visual elapsed-time severity:

```text
normal
warning
critical
```

---

# 24. Order Card

Shared operational card for order queue/history where appropriate.

Display:

```text
order number
customer
table/order type
time
total
status
```

Available actions depend on screen.

---

# 25. Shift Summary

Displays:

```text
opening cash
cash sales
bank sales
total sales
expected cash
```

At close:

```text
actual cash
variance
```

---

# 26. Modal Contract

Every modal should define:

```text
id
trigger
open behavior
close behavior
confirm behavior
loading state
error state
focus behavior
```

Do not make individual modals invent incompatible lifecycle behavior.

---

# 27. Empty State

Every list/data region should define an empty state.

Example:

```text
No products found.
No held carts.
No active orders.
No order history.
No shifts.
```

---

# 28. Error State

Error states should include:

```text
message
retry action where possible
```

Do not expose stack traces or raw server exceptions.

