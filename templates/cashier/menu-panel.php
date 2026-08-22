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
    <button type="button" class="coffeepos-chip" data-action="select-category" data-category-id="coffee" aria-pressed="false">
        <?php esc_html_e('Coffee', 'coffeepos'); ?>
    </button>
    <button type="button" class="coffeepos-chip" data-action="select-category" data-category-id="tea" aria-pressed="false">
        <?php esc_html_e('Tea', 'coffeepos'); ?>
    </button>
    <button type="button" class="coffeepos-chip" data-action="select-category" data-category-id="smoothie" aria-pressed="false">
        <?php esc_html_e('Smoothie', 'coffeepos'); ?>
    </button>
    <button type="button" class="coffeepos-chip" data-action="select-category" data-category-id="snacks" aria-pressed="false">
        <?php esc_html_e('Snacks', 'coffeepos'); ?>
    </button>
</nav>

<section class="coffeepos-product-grid" data-component="product-grid" data-state="normal" aria-live="polite">
    <article class="coffeepos-product-card" data-component="product-card" data-product-id="demo-espresso" data-state="normal">
        <button type="button" class="coffeepos-product-card-button" data-action="select-product">
            <span class="coffeepos-product-name"><?php esc_html_e('Espresso', 'coffeepos'); ?></span>
            <span class="coffeepos-product-price">35.000đ</span>
            <span class="coffeepos-product-meta"><?php esc_html_e('In stock', 'coffeepos'); ?></span>
        </button>
    </article>

    <article class="coffeepos-product-card" data-component="product-card" data-product-id="demo-latte" data-state="selected">
        <button type="button" class="coffeepos-product-card-button" data-action="select-product">
            <span class="coffeepos-product-name"><?php esc_html_e('Latte', 'coffeepos'); ?></span>
            <span class="coffeepos-product-price">45.000đ</span>
            <span class="coffeepos-product-meta"><?php esc_html_e('Variation available', 'coffeepos'); ?></span>
        </button>
    </article>

    <article class="coffeepos-product-card" data-component="product-card" data-product-id="demo-cold-brew" data-state="out_of_stock">
        <button type="button" class="coffeepos-product-card-button" data-action="select-product" disabled>
            <span class="coffeepos-product-name"><?php esc_html_e('Cold brew', 'coffeepos'); ?></span>
            <span class="coffeepos-product-price">50.000đ</span>
            <span class="coffeepos-product-meta"><?php esc_html_e('Out of stock', 'coffeepos'); ?></span>
        </button>
    </article>

    <div class="coffeepos-panel-state" data-component="product-grid-empty" hidden>
        <strong><?php esc_html_e('No products found', 'coffeepos'); ?></strong>
        <p><?php esc_html_e('Try another keyword or category.', 'coffeepos'); ?></p>
    </div>

    <div class="coffeepos-panel-state" data-component="product-grid-loading" hidden>
        <strong><?php esc_html_e('Loading products…', 'coffeepos'); ?></strong>
    </div>
</section>
