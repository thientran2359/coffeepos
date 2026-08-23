# CoffeePOS Report API

## 1. Purpose

The Report API provides manager-only sales analytics and exports derived from
canonical CoffeePOS WooCommerce orders. It does not create report storage or a
second revenue source of truth.

## 2. Authorization

Both endpoints require:

```text
authenticated WordPress user
valid REST nonce
manage_woocommerce
```

Failure returns `coffeepos_reports_forbidden`. Route or UI visibility is not an
authorization control.

## 3. Sales Report

```text
GET /coffeepos/v1/reports/sales
```

Query parameters:

| Parameter | Values | Rules |
|---|---|---|
| `preset` | `today`, `yesterday`, `last_7_days`, `current_month`, `custom` | defaults to `today` |
| `date_from` | `YYYY-MM-DD` | required only for `custom` |
| `date_to` | `YYYY-MM-DD` | required only for `custom` |
| `product_limit` | `5..100` | server clamps to the range; defaults to 10 |

Custom ranges are inclusive in the WordPress timezone and cannot exceed 366
calendar days.

Success follows the standard envelope:

```json
{
  "success": true,
  "data": {
    "report": {
      "range": {},
      "summary": {},
      "currency_groups": [],
      "warnings": [],
      "generated_at": "2026-08-23T10:00:00Z"
    }
  }
}
```

Each currency group contains order/product counts, gross/refund/net/AOV money
objects, cash/bank/unknown payment buckets, product rankings, unallocated
refunds, and exactly 24 peak-hour buckets. Money objects contain integer minor
units, decimal amount text, display text, and currency. Different currencies are
never summed.

## 4. Export

```text
GET /coffeepos/v1/reports/sales/export
```

It accepts the same filters and requires:

```text
format=csv|xlsx
```

On success it returns binary bytes rather than the JSON success envelope.

CSV headers include:

```text
Content-Type: text/csv; charset=UTF-8
Content-Disposition: attachment; filename="coffeepos-sales-...csv"
```

XLSX headers include:

```text
Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet
Content-Disposition: attachment; filename="coffeepos-sales-...xlsx"
```

Both use `Cache-Control: private, no-store, max-age=0` and
`X-Content-Type-Options: nosniff`. Validation, authorization, query, and export
failures continue to use the standard JSON error envelope.

## 5. Eligibility and Formulas

Eligible orders are trusted CoffeePOS WooCommerce orders in `processing`,
`completed`, or `refunded`, selected by creation date. Net revenue subtracts all
WooCommerce refunds. Product quantity/revenue subtract only item-attributed
refund data; amount-only refunds remain unallocated and visible.

The screen and both export formats reuse `SalesReportService`; no transport owns
an independent formula.

## 6. Stable Errors

```text
invalid_report_range
report_range_too_large
report_query_failed
report_export_failed
report_export_unavailable
coffeepos_reports_forbidden
```

`report_export_unavailable` returns 503 when runtime support such as ZipArchive
is missing. Query or export failure never returns partial report data as success.

## 7. Query Boundary

The WooCommerce gateway uses paginated 100-order batches through
`wc_get_orders`. It supports HPOS through WooCommerce's data-store abstraction,
does not query order tables directly, and never sends raw WooCommerce objects to
the browser.
