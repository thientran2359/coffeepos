# CUSTOMER-DISPLAY-UI.md

# CoffeePOS Customer Display UI Specification

## Approved checkout overlay revision (2026-08-23)

Opening Cashier checkout opens a centered Customer Display overlay above the
cart. Cash selection shows the canonical total and asks the customer to hand
cash to the cashier. Bank selection replaces it with the server-generated
VietQR and asks the customer to scan it. After the cashier manually confirms
receipt and checkout succeeds, the overlay shows order success and remains
visible. Only Cashier's Start new order action and valid `display.reset` close
success and reveal the new cart.

The Cashier checkout modal never renders the QR image. It shows only the method
selector, confirmation guidance, and the authorized completion action; the QR
image is Customer Display-only.

## 1. Purpose

Customer Display is a separate customer-facing application surface.

The feature baseline requires real-time synchronization with the cashier through `BroadcastChannel`.

Cashier and Customer Display must be paired by the same opaque
`pos_session_id`. The display renders the latest server-confirmed cart
projection; it does not maintain an independent cart.

It also renders a read-only product menu grouped by WooCommerce category from
the shared `CatalogView`. The menu and realtime cart are separate presentation
regions.

---

# 2. Design Goals

The display should be:

- highly readable
- visually simple
- customer-facing rather than operator-facing
- updated in real time
- useful as a readable product menu
- focused on item, quantity, price and total

## 2.1 Landscape Layout

The reference composition is:

```text
Customer Display
├── Product Menu (approximately 68–72%)
│   ├── Brand
│   ├── Category Jump Navigation
│   └── Category Grid (2–3 columns)
│       └── Compact Product Rows
└── Realtime Cart (approximately 28–32%)
    ├── Cart Header
    ├── Scrollable Cart Items
    ├── Totals
    └── Payment / Thank-you State
```

The cart column remains stable while cart items update. Totals and the current
payment action/state stay visible at the bottom. The product menu and cart items
have independent scroll regions.

Do not copy the reference brand, decorative artwork, or literal product data.
Reuse CoffeePOS branding, WooCommerce catalog data, shared design tokens, and
the documented responsive rules.

Startup loads two independent projections in parallel:

```text
GET /catalog                 → render read-only product menu
Cart GET/sync handshake      → render realtime cart region
```

A catalog failure must not stop realtime cart/payment updates. A temporary sync
failure must not erase a previously valid product menu.

---

# 3. States

```text
IDLE
CART
CHECKOUT
PAYMENT_PENDING
PAYMENT_SUCCESS
THANK_YOU
```

Failure may return the display to:

```text
CHECKOUT
```

These states govern the realtime cart/payment region. The product menu remains
visible unless a documented critical full-screen payment/success treatment
temporarily takes focus.

---

# 4. Idle State

Show:

```text
branding
welcome message
optional store information
read-only product menu
```

No cashier controls.

---

# 5. Cart State

The left/menu region continues to show the category menu. The right region
shows the synchronized cart.

Display:

```text
items
quantities
unit prices
line totals
subtotal/discount/total
customer information where applicable
```

The customer display consumes a projection from the cashier/application state.

It must not independently reconstruct authoritative totals.

Prices and totals displayed here are the same WooCommerce-derived values
returned for the Cashier cart projection.

---

## 5.1 Product Menu

The menu contract belongs in `CATALOG-UI.md`.

Each category section displays:

```text
category name
optional category presentation marker
product name
optional projection-provided badge
WooCommerce-formatted price
```

The menu is informational. Product rows have no add/select action and cannot
mutate the cart. Categories may be arranged in two or three columns according
to available width while retaining deterministic WooCommerce/menu order.

Products that are not purchasable or not in stock are hidden from the Customer
Display menu; categories with no remaining visible products are omitted. The
Cashier may still show those products in a disabled availability state.

---

# 6. Customer Information

When customer is identified:

```text
customer name
membership information
points where supported
```

Do not expose unnecessary personal information.

---

# 7. Checkout State

Display:

```text
order summary
total
```

This state confirms what the customer is about to pay.

---

# 8. Payment Pending

For bank transfer:

```text
amount due
VietQR
payment instruction
payment pending indicator
compact item summary
subtotal/discount/total
```

QR must correspond to the current payment context.
The payment overlay reuses the accepted customer-safe cart projection. It must
not recalculate line totals, discounts, or totals in the browser. Long item
lists scroll inside the summary so the payment instruction and QR remain
visible and usable.

---

# 9. Payment Success

Display:

```text
payment successful
amount
order reference
```

Then transition to:

```text
THANK_YOU
```

---

# 10. Thank You

Show:

```text
thank you message
```

Then reset to:

```text
IDLE
```

after the documented timeout or cashier reset event.

---

# 11. Synchronization

Transport:

```text
Cashier
  ↕
BroadcastChannel
  ↕
Customer Display
```

Channel:

```text
coffeepos:<pos_session_id>
```

Messages should include a semantic event type.

Conceptual messages:

```text
display.ready
state.requested
state.snapshot
cart.updated
customer.updated
checkout.started
payment.started
payment.updated
sale.completed
display.reset
```

The final payload contract belongs in `SYNC-API.md`.

On startup, Customer Display sends `display.ready` with its last known revision.
Cashier responds with a full `state.snapshot`; the display therefore does not
need to wait for the next cart change. Revision gaps trigger
`state.requested`. Messages for another session or an older revision are
ignored.

Customer Display may send only readiness, snapshot requests, and
acknowledgements. It cannot mutate cart, payment, or order state.

---

# 12. Failure Handling

If synchronization is interrupted:

- keep last valid state
- display a neutral connection state if required
- do not invent payment status
- reset only when instructed by a valid application event

---

# 13. Acceptance Criteria

1. A read-only product menu renders from the shared CatalogView.
2. Products are grouped into ordered category sections.
3. Product names and WooCommerce-derived prices are visible.
4. Landscape layout keeps the product menu beside the realtime cart.
5. Product rows cannot mutate the cart.
6. Cart changes appear in real time.
7. Quantities update in real time.
8. Totals update in real time.
9. Customer identity appears when selected.
10. Payment amount appears when checkout starts.
11. VietQR appears for bank transfer.
12. Payment success is reflected only after an authoritative success event.
13. Thank-you state is displayed after successful payment.
14. Display can return to idle.
15. A display opened after items were added receives the current snapshot.
16. Stale and foreign-session messages are ignored.
17. A revision gap recovers through a full snapshot.
18. Customer Display never calculates or changes WooCommerce-derived prices.
19. Every successful customer-facing cart mutation is pushed without cart polling.
20. Cashier and Customer Display render the same accepted cart revision.
