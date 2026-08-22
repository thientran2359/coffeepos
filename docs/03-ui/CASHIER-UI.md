# CASHIER-UI.md

# CoffeePOS Cashier UI Specification

## 1. Purpose

The Cashier is the primary POS workflow.

The baseline feature specification requires:

- category filtering
- live product search
- stock visibility
- simple and variable products
- variation modal
- quick notes
- cart management
- dine-in/takeaway
- customer/member lookup
- coupons
- suspended carts
- quick stock adjustment
- checkout

---

# 2. Screen Composition

```text
CASHIER
│
├── HEADER
│   ├── Brand
│   ├── Current Shift
│   ├── Cashier
│   └── Utility Actions
│
├── MENU
│   ├── Search
│   ├── Categories
│   └── Product Grid
│
├── CART
│   ├── Order Type
│   ├── Customer
│   ├── Cart Items
│   ├── Coupon
│   ├── Subtotal
│   ├── Discount
│   ├── Total
│   └── Checkout
│
└── OVERLAYS
    ├── Product Modal
    ├── Customer Lookup
    ├── Table Selector
    ├── Coupon Selector
    ├── Hold Cart
    ├── Held Cart List
    ├── Checkout
    └── Confirm Dialogs
```

---

# 3. Header

Minimum information:

```text
CoffeePOS
Current shift status
Current cashier
```

Utility actions may include:

```text
Hold cart
Held carts
Customer display
Shift
```

Do not overload the header with operational controls from other screens.

---

# 4. Product Categories

The category area MUST support:

```text
All
WooCommerce product categories
```

The baseline specification gives examples such as:

```text
Đồ uống
Đồ ăn
Cà phê
```

Categories should be data-driven.

## Interaction

```text
click category
→ update active category
→ load/filter product grid
```

Active category must have a clear visual state.

---

# 5. Product Search

Search is live.

Flow:

```text
input
→ debounce
→ product query
→ loading state
→ results
```

Requirements:

- search by product name
- preserve current category behavior according to product query contract
- show empty state
- allow clearing search

---

# 6. Product Grid

Each product card should show:

```text
image
name
price
stock state
variation indicator where useful
```

Product availability is visible before selection.

Click behavior:

```text
simple available product
→ add/configure flow

variable product
→ product configuration modal

out-of-stock product
→ blocked
```

---

# 7. Product Selection Decision

```text
Product Click
     ↓
Is product purchasable?
     ├── No → show unavailable state
     │
     └── Yes
          ↓
      Simple?
       ├── Yes → determine whether configuration is required
       │          ↓
       │       add to cart
       │
       └── No → open configuration modal
```

Even a simple product may open configuration when POS-specific options are configured.

---

# 8. Product Configuration Modal

## Opening

Input:

```text
product_id
optional existing cart item
mode = add|edit
```

## Structure

```text
┌──────────────────────────────────┐
│ Product Name                  X  │
│ Base Price                       │
├──────────────────────────────────┤
│ VARIATIONS                       │
│ [Size] [Temperature] ...         │
├──────────────────────────────────┤
│ OPTIONS / MODIFIERS              │
│ [Milk] [Syrup] ...               │
├──────────────────────────────────┤
│ QUICK NOTES                      │
│ [Ít đá] [Không đá] ...           │
├──────────────────────────────────┤
│ NOTE                             │
│ [____________________________]   │
├──────────────────────────────────┤
│ QTY            [-] 1 [+]         │
├──────────────────────────────────┤
│              Cancel  Add  45k    │
└──────────────────────────────────┘
```

Exact visual design belongs to implementation, but semantic sections are required.

---

# 9. Variation Selection

The UI MUST derive variation attributes from WooCommerce.

Example:

```text
Size:
[ S ] [ M ] [ L ]

Temperature:
[Hot] [Cold]
```

Do not hard-code attribute names.

## Required selection

If a valid purchasable variation cannot be resolved, Add to Cart must remain unavailable.

---

# 10. Variation Price

When selection changes:

```text
variation selection
→ resolve matching variation
→ update price display
→ update availability
```

Do not use arbitrary browser price calculations.

---

# 11. Modifier Selection

Modifier groups can contain options.

Example:

```text
Milk
○ Regular
○ Oat +10k
○ Soy +10k
```

The final selection rule must follow the configured modifier group:

```text
single-select
multi-select
required
optional
minimum
maximum
```

Those rules must come from the modifier configuration.

---

# 12. Quick Notes

Quick-note buttons should be fast to tap.

Baseline examples:

```text
Ít đá
Không đá
Ít ngọt
Không đường
Nhiều sữa
Mang về
```

Selection should be visibly toggled.

If multiple notes are allowed, the UI must allow multiple selections.

---

# 13. Custom Note

Free-form note:

```text
<textarea>
```

Examples:

```text
Không cho ống hút
Làm riêng
```

The note belongs to the item.

---

# 14. Quantity

Default:

```text
1
```

Actions:

```text
-
quantity
+
```

Rules:

- minimum cart quantity is 1
- increase/decrease is immediate in UI
- final quantity is validated server-side
- stock restrictions must be honored

---

# 15. Add to Cart

Before add:

```text
product valid
variation valid
required configuration valid
quantity valid
```

Flow:

```text
Add
 ↓
construct cart item
 ↓
Cart Service
 ↓
cart updated
 ↓
close modal
 ↓
update cart UI
 ↓
sync customer display
```

Do not directly manipulate the final WooCommerce order at this point.

---

# 16. Edit Cart Item

Flow:

```text
Cart Item
 ↓ click Edit
Product Configuration Modal
 ↓
load existing configuration
 ↓
modify
 ↓
Update
 ↓
replace cart item configuration
 ↓
recalculate cart projection
```

The edit modal should start with the same selected:

- variation
- modifiers
- notes
- quantity

as the current item.

---

# 17. Cart Item Display

Each cart item should show:

```text
Product name
Variation
Modifiers
Notes where useful
Quantity
Unit price
Line total
```

Actions:

```text
increase
decrease
edit
remove
```

---

# 18. Cart Totals

Display:

```text
Subtotal
Discount
Total
```

The UI may update optimistically for responsiveness, but checkout must recalculate/validate trusted totals server-side.

---

# 19. Order Type

Default order type should follow the project's operational default.

Supported:

```text
Dine-in
Takeaway
```

Selecting Dine-in:

```text
Order Type
→ Dine-in
→ Table selector
→ selected table
```

Selecting Takeaway:

```text
Order Type
→ Takeaway
→ clear/disable table context
```

---

# 20. Customer

Customer section should support:

```text
Guest
Member
Lookup by phone
```

Flow:

```text
Customer
→ Phone
→ Search
→ Found
→ Select
```

The displayed customer summary may include:

```text
name
phone
membership status
points when available
```

---

# 21. Coupon

Coupon flow:

```text
Coupon
→ choose available coupon OR enter code
→ validate
→ apply
→ update cart totals
```

Remove:

```text
Remove coupon
→ server/cart recalculation
→ update totals
```

---

# 22. Suspended Cart

Hold flow:

```text
Current Cart
→ Hold
→ enter label
→ save
→ cart reset
```

Resume flow:

```text
Held Carts
→ select cart
→ Resume
→ load cart
→ cashier continues
```

Delete requires confirmation.

---

# 23. Clear Cart

Clear flow:

```text
Clear
→ confirmation
→ if confirmed
→ empty cart
→ update totals
→ sync customer display
```

---

# 24. Checkout Entry

Checkout button should be disabled when:

```text
cart empty
cart invalid
required order type data missing
```

When clicked:

```text
validate current UI state
→ open checkout
```

Final validation happens server-side.

---

# 25. Loading States

Examples:

```text
Product loading
Search loading
Customer lookup
Coupon validation
Hold cart
Resume cart
Cart update
Checkout
```

Disable duplicate actions while non-idempotent requests are pending.

---

# 26. Error States

Examples:

```text
Product unavailable
Variation unavailable
Stock changed
Coupon invalid
Customer lookup failed
Hold failed
Resume failed
Checkout failed
Payment failed
```

Errors should preserve the cashier's current work where safe.

Do not erase a cart after a failed checkout.

---

# 27. Keyboard Interaction

Recommended:

```text
Ctrl/Cmd + K → search
Escape → close modal
Enter → confirm focused action
```

Exact shortcuts should be documented before implementation if enabled.

---

# 28. Acceptance Criteria

The Cashier UI is complete for this specification when:

1. Categories can be selected.
2. Products can be searched live.
3. Stock state is visible.
4. Simple products can be selected.
5. Variable products open a configuration modal.
6. Required variation choices are enforced.
7. Variation price/availability updates correctly.
8. Modifiers can be selected according to configuration.
9. Quick notes can be selected.
10. Free-form notes can be entered.
11. Quantity can be changed.
12. Configured items can be added to cart.
13. Existing cart items can be edited.
14. Cart items can be removed.
15. Cart can be cleared with confirmation.
16. Subtotal, discount and total are displayed.
17. Dine-in and takeaway are selectable.
18. Dine-in can select a table.
19. Customer can be guest/member.
20. Customer can be found by phone.
21. Coupons can be applied and removed.
22. Cart can be held and resumed.
23. Checkout cannot begin with invalid cart state.
24. Loading and error states are visible.
25. No critical business decision relies only on client-side state.
