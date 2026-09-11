<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<template id="coffeepos-customer-category-template">
    <section class="coffeepos-customer-category" data-component="catalog-category-section" data-key="id" data-attr="data-category-id:id">
        <h2 data-field="name"></h2>
        <div data-component="catalog-category-products" data-attr="data-category-products:id"></div>
    </section>
</template>
<template id="coffeepos-customer-product-row-template">
    <article class="coffeepos-customer-product-row" data-component="customer-product-row" data-key="occurrence_key" data-attr="data-product-id:id;data-occurrence-key:occurrence_key">
        <div><strong data-field="name"></strong><small class="coffeepos-customer-product-badge" data-field="badge_label"></small></div>
        <span data-field="price_display"></span>
    </article>
</template>
<template id="coffeepos-customer-cart-item-template">
    <article class="coffeepos-customer-cart-item" data-key="item_id">
        <div><strong data-field="product_name"></strong><small data-field="variation_summary"></small><small data-field="modifier_summary"></small></div>
        <span>&times;<span data-field="quantity"></span></span>
        <strong data-field="line_total_display"></strong>
    </article>
</template>
<template id="coffeepos-customer-payment-item-template">
    <div class="coffeepos-customer-payment-item" data-key="item_id">
        <div>
            <strong data-field="product_name"></strong>
            <small data-field="variation_summary"></small>
            <small data-field="modifier_summary"></small>
        </div>
        <span class="coffeepos-customer-payment-item__quantity">&times;<span data-field="quantity"></span></span>
    </div>
</template>
