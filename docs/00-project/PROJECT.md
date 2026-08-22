# PROJECT.md

# CoffeePOS Project Definition

## 1. Overview

CoffeePOS is a WooCommerce-based POS system designed for a coffee shop.

The system is intended to provide a fast, touch-friendly cashier workflow while remaining integrated with WooCommerce for products, variations, customers, coupons, orders, payments, stock, and related commerce data.

The project includes multiple operational screens:

- Cashier / POS
- Customer Display
- Kitchen Display System (KDS)
- Order Queue
- Order History
- Shift Management
- Reports & Analytics
- Administrative configuration

The feature source defines these major functional areas and the required behavior for each. `featured.txt` is the baseline feature specification.

---

## 2. Product Goals

### Primary goals

1. Make order entry fast for a cashier.
2. Make the interface suitable for touch screens and POS hardware.
3. Support WooCommerce simple and variable products.
4. Support per-item notes and configurable options.
5. Support dine-in and takeaway orders.
6. Support guest and member customers.
7. Support cash and bank-transfer payment flows.
8. Create standard WooCommerce orders.
9. Provide a dedicated customer-facing display.
10. Provide a dedicated KDS.
11. Provide operational order management.
12. Provide shift reconciliation.
13. Provide business reporting.

The feature specification explicitly requires a full-screen POS interface optimized for touch screens, POS terminals, and tablets. It also defines dedicated URL routes for the major POS screens.

---

## 3. Core Screens

### Cashier

The cashier screen is the primary order-entry interface.

It includes:

- product categories
- live product search
- product availability
- product selection
- variation selection
- quick notes
- cart management
- order type
- customer/member selection
- coupon handling
- suspended carts
- quick stock adjustment
- checkout

Products are presented as a complete catalog grouped into category sections.
Category navigation and search locate content by scrolling rather than replacing
the catalog with filtered grids.

### Customer Display

The customer display shows:

- read-only product menu grouped by category
- product names and WooCommerce-derived prices
- current cart
- item quantities
- item prices
- totals
- customer/member information
- payment amount
- VietQR during payment
- thank-you state

The feature specification requires real-time synchronization between cashier and customer display through `BroadcastChannel`.

This synchronization is a primary product requirement. The Customer Display
must show the same server-confirmed cart projection and WooCommerce-derived
prices as the Cashier, scoped to the same active POS session.

### KDS

The KDS shows:

- incoming orders
- preparation status
- item details
- variations
- notes
- elapsed time
- warning state after 5 minutes
- critical state after 10 minutes
- quick status changes

### Order Queue

The order queue shows active orders and provides quick operational actions.

### Order History

The order history provides filtering, order detail, receipt reprinting, and quick reorder.

### Shift Management

Shift management provides:

- open shift
- opening cash
- current shift statistics
- expected cash
- close shift
- actual cash
- cash variance
- shift history

### Reports

Reports provide:

- revenue
- order count
- AOV
- products sold
- payment composition
- best sellers
- peak hours
- CSV/Excel export

---

## 4. WooCommerce Relationship

WooCommerce is the commerce foundation.

CoffeePOS must integrate with WooCommerce instead of creating a parallel commerce system.

WooCommerce remains responsible for the canonical:

- products
- variations
- prices
- stock
- customers
- coupons
- orders
- order items
- refunds
- payment-related order state where applicable

CoffeePOS may add POS-specific metadata or infrastructure where required and documented.

---

## 5. Frontend Philosophy

CoffeePOS uses PHP-owned templates and Vanilla JavaScript.

PHP renders each screen shell and defines reusable dynamic component markup in
native `<template>` elements. AJAX/REST endpoints return JSON projections, and
the project-owned `TemplateRenderer` clones those templates and binds the JSON
data into the DOM.

The UI should be structured as reusable components rather than large monolithic
templates. JavaScript controls behavior, state transitions, requests, and
targeted rendering; it does not own HTML structure or authoritative business
data.

The active cart is stored in the WooCommerce session. Browser cart state is a
rendering projection, not an independent cart or pricing engine.

---

## 6. Operational Principle

The cashier must be able to complete a typical coffee order with minimal interaction:

```text
Find product
→ Select product
→ Configure variation/options
→ Set quantity
→ Add note if necessary
→ Add to cart
→ Identify customer if applicable
→ Select order type/table
→ Apply coupon if applicable
→ Checkout
→ Payment
→ Order created
→ Receipt / customer display / KDS update
```

The system must preserve this flow as a first-class UX goal.

---

## 7. Non-Goals

Unless explicitly added to the requirements:

- CoffeePOS is not an ERP.
- CoffeePOS is not a replacement for WooCommerce administration.
- CoffeePOS is not a general restaurant management platform.
- CoffeePOS should not duplicate the entire WooCommerce data model.
- CoffeePOS should not introduce a frontend framework merely to implement POS interactions.

---

## 8. Feature Baseline

The baseline feature list is defined in `featured.txt`.

The current specification covers:

1. Cashier / POS
2. Checkout & Receipts
3. Customer Display
4. KDS
5. Order Queue
6. Order History
7. Shift Management
8. Reports & Analytics
9. Administration & Technical Infrastructure

No feature should be considered complete merely because its UI exists. It must satisfy the behavior and acceptance criteria of its phase.
