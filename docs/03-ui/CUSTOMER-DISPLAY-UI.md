# CUSTOMER-DISPLAY-UI.md

# CoffeePOS Customer Display UI Specification

## 1. Purpose

Customer Display is a separate customer-facing application surface.

The feature baseline requires real-time synchronization with the cashier through `BroadcastChannel`.

---

# 2. Design Goals

The display should be:

- highly readable
- visually simple
- customer-facing rather than operator-facing
- updated in real time
- focused on item, quantity, price and total

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

---

# 4. Idle State

Show:

```text
branding
welcome message
optional store information
```

No cashier controls.

---

# 5. Cart State

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
```

QR must correspond to the current payment context.

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

Messages should include a semantic event type.

Conceptual messages:

```text
cart.updated
customer.updated
checkout.started
payment.started
payment.updated
sale.completed
display.reset
```

The final payload contract belongs in `SYNC-API.md`.

---

# 12. Failure Handling

If synchronization is interrupted:

- keep last valid state
- display a neutral connection state if required
- do not invent payment status
- reset only when instructed by a valid application event

---

# 13. Acceptance Criteria

1. Cart changes appear in real time.
2. Quantities update in real time.
3. Totals update in real time.
4. Customer identity appears when selected.
5. Payment amount appears when checkout starts.
6. VietQR appears for bank transfer.
7. Payment success is reflected only after an authoritative success event.
8. Thank-you state is displayed after successful payment.
9. Display can return to idle.
