# CATALOG-UI.md

# CoffeePOS Shared Catalog UI Contract

## 1. Purpose

Cashier and Customer Display present the same WooCommerce catalog through
different UI templates.

This document owns the shared catalog projection, category-section structure,
navigation, search-location behavior, selectors, and extension rules. Screen
documents own only their screen-specific layout and actions.

---

# 2. Ownership

```text
WooCommerce catalog and prices
        ↓
CatalogService
        ↓
CatalogView JSON
        ├──→ Cashier interactive templates
        └──→ Customer Display read-only templates
```

Rules:

- WooCommerce owns products, variations, visibility, stock, order, and price.
- `CatalogService` owns grouping and projection, not HTML.
- Both screens consume the same `CatalogView` response.
- Each screen owns its own PHP `<template>` markup.
- Screen controllers may navigate or render; they do not regroup WooCommerce
  data or calculate price.

---

# 3. Catalog Endpoint

Primary endpoint:

```text
GET /coffeepos/v1/catalog
```

The endpoint returns the complete POS-visible catalog needed by both screens.
It is loaded once on screen startup and may be revalidated on focus or through a
documented refresh action. Category clicks and local product search do not make
additional catalog requests.

Conceptual response:

```json
{
  "success": true,
  "data": {
    "catalog": {
      "version": "catalog-hash-or-revision",
      "currency": "VND",
      "categories": [
        {
          "id": 12,
          "slug": "coffee",
          "name": "Coffee",
          "sort_order": 10,
          "products": [
            {
              "id": 123,
              "occurrence_key": "12:123",
              "name": "Latte",
              "type": "variable",
              "price_amount": "45000",
              "price_display": "45.000 đ",
              "image_url": "",
              "is_variable": true,
              "is_in_stock": true,
              "is_purchasable": true,
              "badge_label": ""
            }
          ]
        }
      ]
    }
  }
}
```

`price_amount` follows the API money contract and `price_display` is a
WooCommerce-formatted plain-text value for presentation. Neither screen formats
or recalculates the canonical price independently.

Products belonging to multiple visible categories may appear in multiple
sections. `occurrence_key` is unique per category/product occurrence; product
actions continue to use the canonical product ID.

Categories and products use WooCommerce/menu ordering with a stable ID
tie-breaker. Empty categories are omitted from the rendered catalog.

---

# 4. Shared Section Structure

Both screens render a complete set of category sections from `CatalogView`.

```text
Catalog Scroll Region
├── Category Section[]
│   ├── Category Heading
│   └── Product Collection
└── Catalog Empty/Error State
```

Stable hooks:

```text
data-component="catalog-scroll"
data-component="catalog-category-section"
data-category-id
data-component="catalog-category-products"
data-product-id
data-occurrence-key
```

One screen may render cards while another renders compact rows, but category
and product identity must remain consistent.

Cashier stacks category sections vertically in one scroll region; each section
contains its own responsive Product Card grid. Customer Display arranges the
category sections themselves in a two- or three-column menu grid and uses
compact product rows.

---

# 5. Cashier Category Navigation

Cashier displays a sticky category navigation associated with the catalog scroll
region.

```text
Category click
    ↓
find category section by stable ID
    ↓
scroll that section into view inside catalog-scroll
    ↓
update active category
```

Rules:

- Category click does not filter, hide, rebuild, or refetch products.
- `All` scrolls to the beginning of the catalog.
- Manual scrolling updates the active category through a scroll-spy mechanism.
- Prefer `IntersectionObserver`; provide a measured-scroll fallback when needed.
- Programmatic scrolling must account for sticky search/category controls.
- Active state must not oscillate between sections during smooth scrolling.
- Focus is preserved on the clicked category control.

Stable action:

```text
data-action="scroll-category"
```

---

# 6. Cashier Product Search

Search locates products in the already loaded complete catalog. It does not
replace the category sections.

Flow:

```text
User types
    ↓
normalize query
    ↓
search local CatalogView index
    ↓
show matching product suggestions
    ↓
select result / press Enter
    ↓
scroll to category + product occurrence
    ↓
set active category + focus/highlight product
```

Rules:

- Search by product name; SKU may be added without changing the navigation
  contract.
- Vietnamese diacritics and case should be normalized for matching.
- Each result carries `category_id`, `product_id`, and `occurrence_key`.
- Selecting a result scrolls to the exact occurrence and briefly highlights it.
- Enter selects the first result; arrow keys navigate suggestions; Escape closes
  the result list.
- Clearing search closes results and leaves the catalog/scroll position intact.
- No-result state belongs to the suggestion panel; the main catalog remains
  visible and unchanged.
- Search never automatically adds a product to cart.

Stable hooks:

```text
data-component="product-search"
data-component="product-search-results"
data-action="locate-product"
```

---

# 7. Customer Display Menu

Customer Display renders a read-only visual menu from the same `CatalogView`.
It renders category sections directly without the Cashier category navigation
or category jump controls.

Desktop/landscape composition:

```text
Customer Display
├── Menu Region (approximately 68–72%)
│   ├── Brand/Header
│   ├── Compact Category Jump Navigation
│   └── Category Section Grid (2–3 columns)
│       └── Product Rows: name, optional badge, WooCommerce price
└── Realtime Cart Region (approximately 28–32%)
    ├── Cart Items
    ├── Totals
    └── Payment/Thank-you State
```

The product menu remains visible while cart, checkout, and payment states update
in the right region. The menu is informational:

- no select-product action
- no cart mutation
- no quantity controls
- no price calculation

Cashier renders out-of-stock products in a disabled state so staff can see
availability. Customer Display hides products that are not purchasable or not
in stock, then omits any category left empty by that presentation rule. Optional
badges come from the catalog projection and are not hard-coded in JavaScript.

The menu and cart use independent scroll regions. Cart totals/payment actions
remain visible while cart items scroll. On narrower layouts, the cart retains
priority and the category grid reduces columns without overlapping content.

---

# 8. Templates and Modules

Required PHP-owned templates:

```text
coffeepos-category-button-template
coffeepos-catalog-category-template
coffeepos-product-card-template
coffeepos-product-search-result-template
coffeepos-customer-category-template
coffeepos-customer-product-row-template
```

Conceptual JavaScript responsibilities:

```text
CatalogApi              → fetch/revalidate CatalogView
CatalogRenderer         → render category sections through TemplateRenderer
CategorySectionNavigator→ scroll + scroll-spy
ProductSearchNavigator  → local index + locate/highlight
CashierCatalog          → interactive product-card behavior
CustomerCatalog         → read-only menu behavior
```

Shared modules consume projections and stable hooks. They must not contain
screen-specific markup, WooCommerce calls, cart mutations, or pricing rules.

---

# 9. Loading and Failure

- Initial loading uses stable skeleton dimensions.
- A catalog error shows retry without clearing a previously valid catalog.
- An empty catalog shows one screen-appropriate empty state.
- A refresh swaps the complete CatalogView only after a successful response.
- A stale catalog is acceptable for browsing, but add-to-cart and checkout still
  revalidate WooCommerce price, availability, and stock server-side.

---

# 10. Extension Rules

New catalog fields must be optional and backward-compatible unless the API
version changes. A new badge, image treatment, dietary marker, or secondary
label should extend `CatalogView` and the relevant screen template without
changing product identity, cart commands, or the other screen's markup.

Do not:

- fork catalog fetching/grouping logic per screen
- make category navigation issue product-filter requests
- make search replace the primary catalog DOM
- couple search matching to CSS classes
- put customer-display menu actions into Cashier components
- change shared projection fields only to satisfy one screen's layout

---

# 11. Contract Verification

At minimum, automated or browser contract tests must verify:

1. One CatalogView renders both Cashier and Customer Display templates.
2. Category order and product occurrence identity are deterministic.
3. Category navigation performs no product/catalog request.
4. Manual scrolling updates active category.
5. Search selection locates an existing product node without replacing catalog.
6. Customer product rows expose no cart mutation action.
7. Customer unavailable-product policy does not change the Cashier projection.
8. Adding an optional CatalogView field does not break either renderer.
