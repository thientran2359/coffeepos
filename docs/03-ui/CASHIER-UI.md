# CASHIER-UI.md

# CoffeePOS Cashier UI Specification

## 1. Purpose

The Cashier is the primary POS workflow.

The Cashier workflow requires:

- category-section navigation
- live product search that locates products in those sections
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
├── SHARED STAFF SIDEBAR
│   ├── Permitted Destinations
│   ├── Current Screen
│   └── Staff Identity + Logout
│
├── HEADER
│   ├── Brand
│   ├── Current Shift
│   ├── Cashier
│   └── Utility Actions
│
├── MENU
│   ├── Search
│   ├── Sticky Category Navigation
│   └── Catalog Scroll Region
│       └── Category Section[]
│           └── Product Card[]
│
├── CART
│   ├── Order Type
│   ├── Customer + Coupon compact context row
│   ├── Cart Items
│   ├── Order Note summary/action
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
    ├── Order Note Dialog
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
Cross-screen navigation belongs to the shared left staff sidebar. The Cashier
header remains dedicated to current-shift, current-cashier, and operational
actions.

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
→ scroll catalog region to category section
```

Active category must have a clear visual state.

Manual catalog scrolling updates the active category through scroll-spy. A
category click must not hide products, issue another product request, or rebuild
the catalog. `All` returns to the beginning of the catalog.

---

# 5. Product Search

Search is a live catalog locator.

Flow:

```text
input
→ debounce
→ search the loaded CatalogView index
→ matching suggestions
→ select result
→ scroll to and highlight product
```

Requirements:

- search by product name
- normalize case and Vietnamese diacritics
- include category/product occurrence identity in every result
- update active category after locating a result
- show no-result state without replacing the main catalog
- allow clearing search
- support keyboard result navigation

---

# 6. Product Catalog Sections

All POS-visible products are grouped by WooCommerce category in one scrollable
catalog. The shared grouping and navigation contract belongs in
`CATALOG-UI.md`.

Each product card should show:

```text
image
name
price
stock state
variation indicator where useful
```

Product availability is visible before selection.

The same product may appear in multiple categories through a unique occurrence
key while retaining its canonical product ID.

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
○ Oat
○ Soy
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

Modifiers do not change price. Price-changing choices must be represented by a
WooCommerce variation and use its WooCommerce price.

---

# 12. Quick Notes

Quick-note buttons should be fast to tap.

Phase-12 default chips:

```text
Ít đường
Nhiều đường
Ít sữa
Ít đá
```

Selection should be visibly toggled.

The UI allows multiple selections. Quick-note IDs remain structured state and
each selected label is mirrored into the custom-note textarea on a separate
line. Deselecting a chip removes that exact generated line without deleting
other staff instructions.

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

# 13.1 Order Note

The Cashier cart/summary area provides a separate order-level textarea. It does
not appear in the item configuration modal and does not modify any item note.
Save and clear are revisioned cart mutations; stale revisions reconcile with the
returned authoritative CartView. The field supports at most 2000 characters and
shows saving, saved, and recoverable error feedback.

The order note is private staff context. It is not broadcast to Customer
Display. Clearing or starting a new cart clears the order note. Quick reorder
starts with an empty order note.

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
send product/configuration + expected_revision
 ↓
Cart API / WooCommerce session
 ↓
server resolves WooCommerce price
 ↓
canonical cart projection + revision
 ↓
update cart UI
 ↓
broadcast same projection to Customer Display
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

The UI shows a pending state during a mutation and replaces totals with the
server-confirmed projection. It must not calculate or broadcast an optimistic
price/total as confirmed state.

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

Customer and Coupon share one compact context row in the Cashier cart. Each
half keeps its own stable action and state. On narrow screens the labels may
truncate visually, but the actions and accessible labels remain available.

---

# 21.1 Order Note Dialog

The cart shows a compact Order Note trigger and a one-line saved-note summary.
Activating it opens the PHP-owned Order Note dialog. The dialog owns the
textarea, save/clear feedback, Close action, backdrop close, Escape close, focus
trap, and focus restoration. Moving the editor into the dialog does not change
the revisioned order-note API or make the note item-level/customer-visible.

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

1. All products render in WooCommerce category sections.
2. Category click scrolls to the matching section without filtering/refetching.
3. Manual scrolling updates the active category.
4. Product search returns live local suggestions.
5. Selecting a search result scrolls to and highlights the exact product.
6. Search/no-result/clear behavior does not replace the catalog.
7. Stock state is visible.
8. Simple products can be selected.
9. Variable products open a configuration modal.
10. Required variation choices are enforced.
11. Variation price/availability updates correctly.
12. Modifiers can be selected according to configuration.
13. Quick notes can be selected.
14. Free-form notes can be entered.
15. Quantity can be changed.
16. Configured items can be added to cart.
17. Existing cart items can be edited.
18. Cart items can be removed.
19. Cart can be cleared with confirmation.
20. Subtotal, discount and total are displayed.
21. Dine-in and takeaway are selectable.
22. Dine-in can select a table.
23. Customer can be guest/member.
24. Customer can be found by phone.
25. Coupons can be applied and removed.
26. Cart can be held and resumed.
27. Checkout cannot begin with invalid cart state.
28. Loading and error states are visible.
29. No critical business decision relies only on client-side state.
