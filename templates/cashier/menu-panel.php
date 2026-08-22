<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

?>
<div class="coffeepos-menu-toolbar" data-component="menu-toolbar">
    <div class="coffeepos-menu-search" data-component="search" data-state="idle">
        <label class="screen-reader-text" for="coffeepos-product-search"><?php esc_html_e('Search products', 'coffeepos'); ?></label>
        <span class="coffeepos-search-icon" aria-hidden="true">&#128269;</span>
        <input
            id="coffeepos-product-search"
            type="search"
            data-component="product-search"
            placeholder="<?php esc_attr_e('Search products', 'coffeepos'); ?>"
            autocomplete="off"
            aria-controls="coffeepos-product-search-results"
            aria-expanded="false"
            disabled
        >
        <button type="button" class="coffeepos-icon-button coffeepos-search-clear" data-action="clear-search" title="<?php esc_attr_e('Clear search', 'coffeepos'); ?>" aria-label="<?php esc_attr_e('Clear search', 'coffeepos'); ?>" hidden>
            <span aria-hidden="true">&#215;</span>
        </button>
        <div id="coffeepos-product-search-results" class="coffeepos-search-results" data-component="product-search-panel" data-state="closed" hidden>
            <div class="coffeepos-search-status" data-component="search-results-loading" hidden>
                <?php esc_html_e('Searching...', 'coffeepos'); ?>
            </div>
            <div class="coffeepos-search-status" data-component="search-results-empty" hidden>
                <?php esc_html_e('No matching products', 'coffeepos'); ?>
            </div>
            <div data-component="product-search-results" role="listbox"></div>
        </div>
    </div>

    <nav class="coffeepos-category-nav" data-component="category-nav" data-state="loading" aria-label="<?php esc_attr_e('Product categories', 'coffeepos'); ?>">
        <button type="button" class="coffeepos-category-button is-active" data-action="scroll-category" data-category-id="all" aria-pressed="true" disabled>
            <?php esc_html_e('All', 'coffeepos'); ?>
        </button>
        <div class="coffeepos-category-list" data-component="category-list"></div>
    </nav>
</div>

<section class="coffeepos-catalog-scroll" data-component="catalog-scroll" data-state="loading" aria-live="polite" tabindex="0">
    <div class="coffeepos-catalog-section-list" data-component="catalog-section-list"></div>

    <div data-component="catalog-loading">
        <?php
        $loadingVariant = 'panel';
        $loadingLabel = __('Loading catalog...', 'coffeepos');
        require COFFEEPOS_PATH . 'templates/components/loading.php';
        ?>
    </div>

    <div data-component="catalog-empty" hidden>
        <?php
        $emptyTitle = __('No products available', 'coffeepos');
        $emptyDescription = __('The catalog is empty.', 'coffeepos');
        require COFFEEPOS_PATH . 'templates/components/empty-state.php';
        ?>
    </div>

    <div data-component="catalog-error" hidden>
        <?php
        $errorMessage = __('The catalog could not be loaded.', 'coffeepos');
        $errorAction = 'retry-catalog';
        require COFFEEPOS_PATH . 'templates/components/error-state.php';
        ?>
    </div>
</section>

<template id="coffeepos-category-button-template">
    <button type="button" class="coffeepos-category-button" data-action="scroll-category" data-key="id" data-attr="data-category-id:id" data-field="name" aria-pressed="false"></button>
</template>

<template id="coffeepos-catalog-category-template">
    <section class="coffeepos-catalog-category" data-component="catalog-category-section" data-key="id" data-attr="data-category-id:id">
        <header class="coffeepos-category-heading">
            <h2 data-field="name"></h2>
            <span data-component="catalog-category-count"></span>
        </header>
        <div class="coffeepos-product-grid" data-component="catalog-category-products"></div>
    </section>
</template>

<template id="coffeepos-product-card-template">
    <article class="coffeepos-product-card" data-component="product-card" data-key="occurrence_key" data-attr="data-product-id:id;data-occurrence-key:occurrence_key">
        <button type="button" class="coffeepos-product-card-button" data-action="select-product">
            <span class="coffeepos-product-visual" aria-hidden="true">
                <img class="coffeepos-product-image" data-component="product-image" data-attr="src:image_url;alt:name">
                <span class="coffeepos-product-initial"></span>
            </span>
            <span class="coffeepos-product-body">
                <span class="coffeepos-product-topline">
                    <strong class="coffeepos-product-name" data-field="name"></strong>
                    <span class="coffeepos-product-badge" data-field="badge_label"></span>
                </span>
                <span class="coffeepos-product-price" data-field="price_display"></span>
                <span class="coffeepos-product-meta" data-component="product-stock-label"></span>
                <span class="coffeepos-product-meta" data-component="product-variation-indicator"></span>
            </span>
        </button>
    </article>
</template>

<template id="coffeepos-product-search-result-template">
    <button type="button" class="coffeepos-search-result" role="option" data-action="locate-product" data-key="occurrence_key" data-attr="data-category-id:category_id;data-product-id:product_id;data-occurrence-key:occurrence_key">
        <span data-field="name"></span>
        <small data-field="category_name"></small>
    </button>
</template>
