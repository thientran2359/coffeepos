# CoffeePOS Phase 11 — Hardening Evidence

## 1. Review Date and Runtime

Review date: 2026-08-23.

Runtime acceptance environment:

```text
WordPress 7.0.2
WooCommerce 11.0.1
PHP 8.2.27
HPOS enabled
WordPress timezone +00:00
WooCommerce currency VND
WooCommerce price decimals 0
PHP ZipArchive available
```

The active Local PHP configuration emits a startup warning because its optional
Imagick DLL is absent. CoffeePOS does not require Imagick and the warning did not
affect report generation or exports.

## 2. Security and Permission Review

- every CoffeePOS REST route has an explicit permission callback
- no `__return_true` permission callback exists
- Reports route, report projection, and binary exports require
  `manage_woocommerce`
- runtime permission probe: Customer role was denied with
  `coffeepos_reports_forbidden`; Administrator was allowed
- report request dates and ranking limit are normalized and bounded server-side
- report responses and exports contain aggregate order/product data only; no
  phone, email, address, credential, nonce, or raw customer object is projected
- CSV formula-leading text is prefixed safely and filenames are sanitized
- XLSX stores external text as inline strings rather than formulas
- export responses use explicit MIME, attachment, no-store, nosniff, and content
  length headers
- Reports JavaScript uses text/template binding and contains no `innerHTML`,
  `outerHTML`, `insertAdjacentHTML`, `document.write`, or `eval`
- the only reviewed direct CoffeePOS SQL remains the Phase-09 shift repository;
  dynamic values are prepared and the table name comes from the WordPress prefix

No critical or high-severity finding was identified in this Phase-11 scope.

## 3. Data Integrity Review

- WooCommerce CRUD/query APIs remain the canonical source
- only `processing`, `completed`, and `refunded` CoffeePOS orders are eligible
- gross, refunded, net, AOV, payment composition, and hourly revenue are
  calculated server-side
- money is accumulated as integer minor units inside each currency group
- currencies are never converted or summed together
- item-attributed refunds reduce product revenue/quantity; amount-only refunds
  remain visible as unallocated
- screen, CSV, and XLSX reuse one ReportView calculation path
- invalid, reversed, or longer-than-366-day custom ranges fail with stable errors
- report failures cannot return partial KPIs as success

## 4. Query and Performance Review

The Phase-11 WooCommerce gateway:

- uses `wc_get_orders`
- requests 100 orders per batch
- aggregates server-side
- does not send raw order datasets to the browser
- contains no direct order-table SQL and remains HPOS-compatible

The audit also replaced Phase-09 Shift Totals' unbounded `limit=-1` order load
with the same 100-order paginated WooCommerce pattern.

Measured with the active HPOS runtime and 10 eligible orders:

| Scenario | Elapsed | Database queries | Result |
|---|---:|---:|---:|
| Today, top 10 | 51.74 ms | 18 | 10 orders |
| 365-day custom range, top 100 | 65.07 ms | 28 | 10 orders |

These measurements describe the current Local dataset, not a universal
production SLA. The 366-day bound and 100-order batching prevent an unbounded
single request. A report projection table remains unjustified by current data.

## 5. UI and Browser Acceptance

Verified in the authenticated WordPress browser runtime:

- `/pos/reports/` loads a VND report with 10 eligible orders
- headline net revenue, AOV, product quantity, payment composition, best sellers,
  and all 24 hour buckets render from the server projection
- Last 7 days resolves to `2026-08-17 — 2026-08-23`
- custom range `2026-08-22 — 2026-08-23` loads successfully
- CSV and Excel exports complete without a UI or server error; runtime responses
  were valid BOM-prefixed CSV (1,471 bytes) and ZIP-based XLSX (5,016 bytes)
- Cashier exposes a visible Reports link for the authorized manager
- the Reports app root scrolls from 0 to 620 px in a 720 px viewport
- the generated assets are loaded with CoffeePOS version `0.0.12`
- the WordPress debug log contained no CoffeePOS fatal, warning, or deprecated
  entry after acceptance

CSV formula/BOM and XLSX package generation are additionally covered by the
Phase-11 automated scenarios.

## 6. Compatibility Review

Passed in the current HPOS environment using supported WooCommerce APIs. The
gateway contains no HPOS table assumptions and is designed to use WooCommerce's
data store abstraction under classic order storage as well.

Classic order-storage runtime acceptance was not available in this site because
HPOS is enabled. This is recorded as an environment coverage limitation, not as
a passed classic-storage browser test.

## 7. Regression Evidence

The PHP scenario suites for Phase 02 through Phase 11 passed. Phase-06 sync and
Phase-08 privacy JavaScript scenarios also passed. PHP/JavaScript syntax checks,
`git diff --check`, and the full Phase-11 report/export contract checks form the
final verification gate.
