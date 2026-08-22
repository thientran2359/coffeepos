# ADMIN-UI.md

# CoffeePOS Admin UI Specification

## 1. Purpose

Admin UI configures CoffeePOS behavior without mixing administrative settings into cashier workflow.

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

---

# 7. Payment Configuration

Payment provider configuration must never store secrets in normal public-facing settings without an appropriate secure mechanism.

Exact VietQR/provider fields belong to payment API documentation.

---

# 8. Permissions

Admin screens must use WordPress capabilities.

Do not rely on menu visibility alone.

---

# 9. Settings Validation

All settings must be:

- typed
- validated
- sanitized
- escaped on output

---

# 10. Acceptance Criteria

1. Administrative settings are separate from cashier UI.
2. Settings are protected by capabilities.
3. Values are validated and sanitized.
4. Saving invalid settings fails safely.
5. Changing settings does not require editing plugin source code.
