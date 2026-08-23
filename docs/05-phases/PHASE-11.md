# CoffeePOS Phase 11 — Reports, Analytics & Production Hardening

## 1. Objective

Phase 11 provides manager-facing sales reports over authoritative CoffeePOS
WooCommerce orders and completes the security, performance, error-handling,
permission, and WooCommerce-compatibility review required for a production-ready
POS foundation.

The exit condition is a protected `/pos/reports/` screen with reproducible KPIs,
CSV and Excel exports, plus documented and tested production-hardening results.

## 2. Prerequisites

- Phase 05 authoritative payment and WooCommerce order creation
- Phase 07 operational order lifecycle
- Phase 08 customer identity and privacy contract
- Phase 09 order-to-shift association and refund-aware shift totals
- Phase 10 CoffeePOS order eligibility, refunds, and historical order queries
- WooCommerce CRUD and HPOS-compatible query APIs
- existing POS routing, REST nonce, error envelope, and PHP template system

Before implementation, inspect the contracts in `REQUIREMENTS.md`,
`ROADMAP.md`, `ARCHITECTURE.md`, `DOMAIN-MODEL.md`, `DATA-FLOW.md`,
`SECURITY.md`, `DATABASE.md`, `WOOCOMMERCE-DATA.md`, `UI-ARCHITECTURE.md`,
`API-ARCHITECTURE.md`, and `ORDER-API.md`.

## 3. In Scope

- `/pos/reports/` manager screen
- today, yesterday, last 7 days, current month, and custom date ranges
- total net revenue, order count, average order value, and products sold
- cash versus bank-transfer revenue
- best-selling products by net item revenue and net quantity
- peak-hour order and revenue distribution
- multi-currency-safe report projections
- CSV export
- Excel `.xlsx` export
- bounded, HPOS-compatible WooCommerce report queries
- loading, empty, error, partial-data, and export states
- final capability matrix and permission audit
- nonce, input-validation, output-escaping, and customer-data audit
- payment, refund, stock, idempotency, and concurrency audit
- query/performance and asset-loading audit
- error-envelope, logging, and failure-recovery audit
- supported WordPress, WooCommerce, PHP, HPOS, and classic order-storage review
- regression and real-browser acceptance across completed phases

## 4. Out of Scope

- a custom analytics warehouse or duplicated order ledger
- external business-intelligence services
- scheduled email reports
- forecasts, targets, budgets, or profit/margin accounting
- tax-accounting or statutory financial statements
- inventory valuation
- customer cohort, lifetime-value, or loyalty analytics
- staff payroll or commission reports
- automated payment-provider settlement reconciliation
- live WebSocket analytics
- a new charting or spreadsheet production dependency
- changing historical WooCommerce orders from the Reports screen
- claiming certification for PCI DSS or another external standard

## 5. Source of Truth and Eligibility

WooCommerce remains authoritative for orders, order items, payment method,
totals, currency, status, and refunds. CoffeePOS shift storage is consulted only
for explicitly shift-owned data; Phase 11 does not introduce a report table.

An order is report-eligible only when it is a trusted CoffeePOS order and its
WooCommerce status is `processing`, `completed`, or `refunded`. Eligibility uses
the established Phase-10 CoffeePOS order predicate. Cancelled, failed, pending,
on-hold, draft, trash, and non-CoffeePOS orders are excluded.

The report period is a sales cohort based on the WooCommerce order creation
timestamp in the configured WordPress timezone. Refunds reduce the period that
contains the original order, even when the refund was created later.

## 6. Date-Range Contract

Supported presets are:

```text
today
yesterday
last_7_days
current_month
custom
```

Dates use `YYYY-MM-DD`, are inclusive, and are interpreted in the WordPress
timezone. `last_7_days` includes today. Custom ranges require both boundaries,
must have `date_from <= date_to`, and are limited to 366 calendar days per
request. The server returns normalized machine timestamps and display labels;
the browser does not calculate authoritative boundaries.

## 7. KPI Definitions

For each currency group:

```text
gross_revenue = sum(order total)
refund_total = sum(WooCommerce total refunded)
net_revenue = gross_revenue - refund_total
order_count = count(report-eligible orders)
aov = net_revenue / order_count, or zero when order_count is zero
```

`products_sold` is the sum of WooCommerce line-item quantity minus recorded
item-quantity refunds, never below zero. An amount-only refund cannot be
invented as a product quantity refund.

All calculations use fixed-decimal money handling at the application boundary.
The API serializes money as decimal strings and includes display-ready values.
JavaScript never recalculates financial totals.

## 8. Payment Composition

Payment composition uses the trusted `_coffeepos_payment_method` established at
checkout and supports exactly:

```text
cash
bank_transfer
```

Each bucket exposes order count, gross revenue, refunds, and net revenue. A
refund is attributed to the original order's payment method. Orders with
missing or unsupported legacy metadata are reported in an `unknown` bucket and
must not be silently assigned to cash or bank transfer.

## 9. Product Analytics

Products are grouped by WooCommerce product/variation identity while preserving
a stable label for deleted historical products.

- quantity ranking uses net item quantity
- revenue ranking uses line total minus WooCommerce item-attributed refunds
- amount-only order refunds remain unallocated and do not fabricate a product
  allocation
- the projection exposes the unallocated refund amount when it is non-zero
- ties use quantity/revenue, then product name, then ID for deterministic order
- interactive rankings default to 10 rows and accept a bounded limit of 5–100

Variation rows remain distinct. A future grouped-parent report is outside this
phase unless added as an explicitly documented filter.

## 10. Peak Hours

Peak-hour analytics use 24 buckets (`00` through `23`) based on the order
creation timestamp in the WordPress timezone. Every bucket exposes order count
and net revenue. Empty hours remain present with zero values so the UI and
exports use a stable shape.

## 11. Currency Contract

Money from different WooCommerce order currencies must never be summed.
The ReportView contains one currency group per encountered order currency.
Revenue KPIs, AOV, payment composition, product revenue, and hourly revenue are
calculated inside that group. Quantity-only totals may be shown across groups,
but their currency-independent nature must be explicit.

The common single-currency case is the primary UI. When multiple currencies are
present, the screen renders separate labeled summaries rather than silently
converting money.

## 12. REST Contract

```text
GET /coffeepos/v1/reports/sales
GET /coffeepos/v1/reports/sales/export
```

The sales endpoint accepts:

```text
preset
date_from
date_to
product_limit
```

It returns a ReportView containing normalized range metadata, currency groups,
global product quantity, payment buckets, product rankings, peak-hour buckets,
data-quality warnings, and generation timestamp.

The export endpoint accepts the same range plus `format=csv|xlsx`. It is the
documented binary-download exception to the standard JSON success envelope.
Validation and authorization failures still use the standard REST error
contract. Export filenames contain only a sanitized range and generation date.

## 13. Query Architecture and Performance

The report application service reads WooCommerce through a dedicated gateway.
Queries must remain compatible with HPOS and classic order storage and must not
read WooCommerce order tables directly.

Rules:

- use bounded WooCommerce order batches; never send an unbounded order dataset
  to the browser
- select only report-eligible CoffeePOS orders in the requested range
- aggregate server-side and return projections, not WooCommerce objects
- avoid per-order product/customer queries where batched or already-loaded data
  is available
- do not add a report table without measured evidence and an approved
  architecture/database change
- any cache must be short-lived, filter-keyed, currency-safe, and invalidated or
  allowed to expire after order/refund changes
- report generation failure returns an error; partial totals are never labeled
  complete

The performance audit must record representative query counts and elapsed time
for empty, normal, and maximum-range datasets. Any accepted limitation must be
documented rather than hidden by the UI.

## 14. Export Contract

CSV is UTF-8 with a BOM and RFC 4180-compatible quoting. It uses a normalized
row structure with a `section` column so summary, payment, product, peak-hour,
currency, and warning rows remain distinguishable. Spreadsheet-formula-leading
text is escaped to prevent CSV injection.

Excel export is a real `.xlsx` workbook with these worksheets:

```text
Summary
Payments
Products
Peak Hours
Warnings
```

The workbook is produced by a small project-owned writer using `ZipArchive` and
valid OOXML; no spreadsheet framework is added. Values have explicit text,
number, date, or money types. If the required runtime extension is unavailable,
the server returns `report_export_unavailable` and never serves a mislabeled
file.

Exports use the same report service and formulas as the screen. They must not
run an independent calculation path.

## 15. Reports UI

The full-screen Reports surface contains:

```text
Header + navigation back to Cashier
Date preset and custom-range controls
KPI cards
Payment composition
Best sellers by revenue
Best sellers by quantity
Peak-hours view
Currency/data-quality warnings
CSV and Excel export actions
```

The screen defines loading, ready, empty, invalid-range, error, exporting, and
export-failed states. Filters remain usable after an error. Repeated rows and
cards use native PHP `<template>` elements with the shared TemplateRenderer.
Vanilla JavaScript may render simple CSS/SVG bars from server values but must not
introduce a charting framework or construct application markup with strings.

The screen must scroll independently where content exceeds the viewport and
must remain keyboard accessible at desktop and tablet widths.

## 16. Final Capability Matrix

Phase 11 finalizes these production rules:

| Surface/operation | Required authorization |
|---|---|
| Cashier, KDS, Order Queue, Shifts, Order History detail/reorder | existing CoffeePOS POS-access policy |
| Reports screen and report data/export | `manage_woocommerce` |
| Refund and CoffeePOS settings | `manage_woocommerce` |
| Customer Display | only its documented session-scoped public projection |

Every route and REST endpoint checks authorization server-side. Hiding a link or
button is not authorization. The audit must verify that users with only the POS
access capability cannot retrieve reports, exports, refunds, settings, or raw
customer data.

## 17. Production-Hardening Gate

Before Phase 11 can complete, audit and resolve or explicitly document:

### Security

- route authentication and REST permission callbacks
- nonce handling and replay-sensitive idempotency
- request bounds, date/filter validation, and money normalization
- contextual output escaping and template binding safety
- SQL preparation wherever direct CoffeePOS-table access is still required
- customer/member privacy in HTML, JSON, sync messages, logs, and exports
- CSV injection, file headers, filenames, and export authorization
- payment confirmation, refund, stock, shift close, and order transition trust

### Reliability and errors

- stable error codes and safe HTTP status mapping
- no raw exception, SQL, path, secret, nonce, or credential leakage
- double-submit, stale revision, lock timeout, and retry behavior
- failure never appearing as checkout, refund, export, or transition success
- actionable loading, empty, offline, and retry states

### Performance

- no duplicate catalog/order/report fetch caused by asset bootstrapping
- no overlapping polling on KDS or Order Queue
- bounded history/report queries and payload sizes
- no avoidable N+1 order/product/customer queries
- screen-specific assets load only where required
- browser interaction remains usable during normal report generation

### Compatibility

- supported PHP and WordPress versions
- current supported WooCommerce release
- HPOS enabled and classic order storage where WooCommerce still supports it
- WooCommerce CRUD use instead of order-table assumptions
- multisite table prefixes and configured POS base slug
- WordPress timezone, locale, currency precision, and pretty permalinks

Audit findings that affect a documented contract must update the corresponding
architecture, database, UI, API, security, or phase document in the same change.

## 18. Stable Errors

```text
invalid_report_range
report_range_too_large
report_query_failed
report_export_failed
report_export_unavailable
coffeepos_reports_forbidden
```

Errors must use the shared envelope and must not return partial KPIs as success.

## 19. Required Verification

- PHP syntax for every changed PHP file
- JavaScript syntax for every changed JavaScript file
- Phase 11 report formula, date-boundary, refund, payment, product, hour,
  multi-currency, export, and authorization scenarios
- CSV quoting/formula-injection tests
- `.xlsx` package and worksheet-content validation
- HPOS-compatible WooCommerce runtime queries
- classic order-storage compatibility where available
- regression suites for all affected prior phases
- role/capability and direct-endpoint access tests
- browser acceptance for presets, custom range, empty data, multiple currencies,
  errors, scrolling, CSV download, and Excel download
- documented security, performance, error-handling, permission, and compatibility
  audit evidence

## 20. Acceptance Criteria

- [ ] Reports are restricted to authorized managers.
- [ ] Presets and custom ranges use inclusive WordPress-timezone boundaries.
- [ ] Only trusted eligible CoffeePOS orders contribute to reports.
- [ ] Revenue and AOV are server-calculated and refund-aware.
- [ ] Orders with different currencies are never summed together.
- [ ] Products sold and both best-seller rankings follow documented refund rules.
- [ ] Cash, bank transfer, and unknown legacy payment data remain distinguishable.
- [ ] All 24 peak-hour buckets use the WordPress timezone.
- [ ] CSV and `.xlsx` exports reproduce the same authoritative report data.
- [ ] Export content and filenames are safe against injection and disclosure.
- [ ] Report queries are bounded, HPOS-compatible, and avoid a duplicate ledger.
- [ ] The Reports UI defines normal, loading, empty, error, and exporting states.
- [ ] The Reports screen is responsive, accessible, and scrollable.
- [ ] The final capability matrix is enforced by routes and endpoints.
- [ ] Security, performance, error, permission, and compatibility audits are
      completed with critical findings resolved.
- [ ] Prior-phase critical workflows pass regression checks.

## 21. Definition of Done

Phase 11 is complete only when implementation, contracts, automated tests,
runtime WooCommerce checks, exports, real-browser acceptance, and hardening audit
evidence agree. No critical or high-severity security/data-integrity defect may
remain open.

This phase completes the documented roadmap. Completion means CoffeePOS is a
production-ready foundation within its stated scope; it does not imply external
security, payment, tax, or accounting certification.
