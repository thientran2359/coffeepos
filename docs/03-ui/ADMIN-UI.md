# ADMIN-UI.md

# CoffeePOS Admin UI Specification

## 1. Purpose

The capability-protected frontend screen at `/pos/settings/` configures
CoffeePOS behavior without mixing administrative settings into the Cashier
workflow or requiring access to the WordPress/WooCommerce admin menu.

---

# 2. Settings Areas

Potential settings include:

```text
POS page
Customer Display page
KDS settings
Order Queue settings
Polling intervals
Quick notes
Receipt settings
VietQR/payment configuration
Display settings
Permissions
```

Only settings required by the feature/architecture should be implemented.

---

# 3. POS Page

Admin can select/configure the WordPress page used for the POS screen according to the routing architecture.

---

# 4. Customer Display Page

Admin can configure the page used by Customer Display.

---

# 5. Polling Settings

Where configurable:

```text
KDS polling interval
Order Queue polling interval
```

The documented baseline for KDS is 5 seconds.

Do not expose dangerous or unreasonable values without validation.

---

# 5.1 Dine-in Tables

The Settings screen provides one textarea backed by
`coffeepos_service_tables`. Each non-empty line is one enabled table label and
its line order becomes `sort_order`. Duplicate labels and empty lines are
ignored. Existing unchanged labels retain their stable positive integer IDs;
new labels receive new IDs. The input is bounded to 200 lines and 100 characters
per label.

This controls selection only. It does not define occupancy, reservations, table
orders, or a floor plan.

---

# 6. Modifiers and Quick Notes

Admin may manage modifier groups/options and configured quick notes.

Each definition should have:

```text
stable ID
label
enabled/disabled
sort order
selection rules where applicable
optional product/category applicability
```

These settings do not define price adjustments. Price-changing customer choices
must be configured as WooCommerce variations.

Phase 12 supplies these editable defaults: `less_sugar`/Ít đường,
`extra_sugar`/Nhiều đường, `less_milk`/Ít sữa, and `less_ice`/Ít đá. IDs remain
stable when labels or ordering change. Deleting or disabling a definition does
not rewrite historical orders.

---

# 7. Payment Configuration

Payment provider configuration must never store secrets in normal public-facing settings without an appropriate secure mechanism.

Exact VietQR/provider fields belong to payment API documentation.

---

# 8. Permissions

Admin screens must use WordPress capabilities.

Do not rely on menu visibility alone.

The `/pos/settings/` route and every save require
`coffeepos_manage_settings`. Saves use a WordPress nonce, an explicit option
allowlist, and the central Settings sanitizers. WordPress user/account
management remains in the native WordPress Users interface and requires native
WordPress user-management capabilities. Phase 12 does not add a PIN field,
password field, employee-account store, or CoffeePOS account editor.

The activation/migration registers the CoffeePOS Cashier, Kitchen, Supervisor,
and Manager role bundles documented in `SECURITY.md`. Administrator and Shop
Manager receive all CoffeePOS capabilities for backward compatibility.

Receipt settings include
`coffeepos_receipt_print_order_note` (boolean, default off). Browser receipt
layout support is part of Phase 12; physical-printer connection settings remain
Phase 15.

---

# 9. Settings Validation

All settings must be:

- typed
- validated
- sanitized
- escaped on output

---

# 10. Acceptance Criteria

1. `/pos/settings/` is separate from Cashier and replaces the former
   WooCommerce-admin submenu.
2. Settings are protected by capabilities.
3. Values are validated and sanitized.
4. Saving invalid settings fails safely.
5. Changing settings does not require editing plugin source code.
