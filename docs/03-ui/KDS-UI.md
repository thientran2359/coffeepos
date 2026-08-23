# KDS-UI.md

# CoffeePOS Kitchen Display System UI Specification

## Phase-07 finalized behavior (2026-08-23)

KDS uses one non-overlapping poll scheduled after the prior request settles.
The baseline/effective interval is five seconds. The initial accepted list
establishes the sound baseline and never alerts; a later response containing one
or more previously unseen `new` order IDs produces one short alert only when the
operator explicitly enabled sound. Sound preference is per browser.

Elapsed time uses response `server_time` and order `received_at` to derive a
server clock offset. One screen timer updates all cards locally every second.
`05:00` is warning and `10:00` is critical. No per-card or per-second server
request is allowed.

Repeated cards/items are PHP-owned native templates rendered through the shared
`TemplateRenderer`. A stale action replaces the affected card with the server
projection; a failed refresh keeps the last accepted list visible.

## 1. Purpose

KDS presents active preparation work.

The baseline feature requires periodic order refresh, new-order sound, preparation statuses, item details, notes, elapsed time, warning/critical states, and quick status actions.

---

# 2. Main Layout

```text
KDS
├── Header
│   ├── Title
│   ├── Sound Toggle
│   └── Refresh Indicator
├── Status Filter
└── Order Grid
```

---

# 3. Order Card

Display:

```text
order number
time received
elapsed time
items
quantities
variations
important notes
current state
```

---

# 4. States

```text
NEW
PREPARING
READY
COMPLETED
```

Only active states need to remain visible by default.

---

# 5. Timer

Elapsed time starts from the defined KDS received timestamp.

Severity:

```text
0–4:59
normal

5:00–9:59
warning

10:00+
critical
```

The timer should update every second locally without causing a server request every second.

---

# 6. Sound

A new order may trigger an alert sound.

Controls:

```text
sound enabled
sound disabled
```

The browser may impose audio policies; the UI must provide an explicit enable mechanism where necessary.

---

# 7. Quick Actions

Examples:

```text
Start
Ready
Complete
```

A button should show pending state while the request is being processed.

---

# 8. Polling

The feature baseline specifies checking for new orders every 5 seconds.

The implementation should avoid duplicate concurrent polling requests.

---

# 9. Empty State

```text
No active orders.
```

---

# 10. Error State

Examples:

```text
Unable to load orders.
Retry
```

Do not clear existing valid cards merely because one refresh failed.

---

# 10.1 Order-Level Note

When present, the private order note appears once in the order-card header with
stronger prominence than line-item notes. Item notes remain attached to their
own lines. Long notes wrap safely and never inject markup. Customer Display does
not consume this field.

---

# 11. Acceptance Criteria

1. New orders appear without manual refresh.
2. New order notification can be enabled/disabled.
3. Order card shows required item information.
4. Timer updates every second.
5. Warning begins at 5 minutes.
6. Critical state begins at 10 minutes.
7. Preparation state can change with one primary action.
8. Polling does not create overlapping requests.
9. Existing cards remain usable during temporary refresh failure.
10. Order-level and item-level notes remain visually distinct.
