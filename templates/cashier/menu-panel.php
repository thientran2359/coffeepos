<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

?>
<div class="coffeepos-menu-search" data-component="search" data-state="empty">
    <label class="screen-reader-text" for="coffeepos-product-search"><?php esc_html_e('Search products', 'coffeepos'); ?></label>
    <input
        id="coffeepos-product-search"
        type="search"
        data-component="product-search"
        data-action="search-products"
        placeholder="<?php esc_attr_e('Search products…', 'coffeepos'); ?>"
        autocomplete="off"
    >
    <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="clear-search">
        <?php esc_html_e('Clear', 'coffeepos'); ?>
    </button>
</div>

<nav class="coffeepos-category-nav" data-component="category-nav" data-state="normal" aria-label="<?php esc_attr_e('Product categories', 'coffeepos'); ?>">
    <button type="button" class="coffeepos-chip is-active" data-action="select-category" data-category-id="all" aria-pressed="true">
        <?php esc_html_e('All', 'coffeepos'); ?>
    </button>
    <div data-component="category-list"></div>
</nav>

<section class="coffeepos-product-grid" data-component="product-grid" data-state="normal" aria-live="polite">
    <div data-component="product-grid-list"></div>
    <div class="coffeepos-panel-state" data-component="product-grid-empty" hidden>
        <strong><?php esc_html_e('No products found', 'coffeepos'); ?></strong>
        <p><?php esc_html_e('Try another keyword or category.', 'coffeepos'); ?></p>
    </div>

    <div class="coffeepos-panel-state" data-component="product-grid-loading" hidden>
        <strong><?php esc_html_e('Loading products…', 'coffeepos'); ?></strong>
    </div>
</section>

<template id="coffeepos-category-button-template">
    <button type="button" class="coffeepos-chip" data-action="select-category" data-key="id" data-attr="data-category-id:id" data-field="name" aria-pressed="false"></button>
</template>

<template id="coffeepos-product-card-template">
    <article class="coffeepos-product-card" data-component="product-card" data-key="id" data-attr="data-product-id:id;data-state:state">
        <button type="button" class="coffeepos-product-card-button" data-action="select-product" data-attr="disabled:is_out_of_stock">
            <span class="coffeepos-product-name" data-field="name"></span>
            <span class="coffeepos-product-price" data-field="price_display"></span>
            <span class="coffeepos-product-meta" data-field="stock_label"></span>
        </button>
    </article>
</template>
