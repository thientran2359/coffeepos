<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="coffeepos-modal" data-component="product-modal" data-state="closed" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-product-modal"></div>
    <section class="coffeepos-modal-dialog coffeepos-product-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-product-modal-title">
        <header class="coffeepos-product-modal-header">
            <h3 id="coffeepos-product-modal-title" data-component="product-modal-title"></h3>
            <button type="button" class="coffeepos-btn-icon" data-action="close-product-modal" aria-label="<?php esc_attr_e('Close product configuration', 'coffeepos'); ?>">×</button>
        </header>
        <p data-component="product-modal-price"></p>
        <p class="coffeepos-modal-error" data-component="product-modal-error" hidden></p>
        <div data-component="variation-selector"></div>
        <div data-component="modifier-selector"></div>
        <div data-component="quick-notes"></div>
        <label class="coffeepos-field">
            <span><?php esc_html_e('Note', 'coffeepos'); ?></span>
            <textarea data-component="product-custom-note" data-value="custom_note" rows="3"></textarea>
        </label>
        <div class="coffeepos-quantity-control" data-component="quantity-control">
            <span><?php esc_html_e('Quantity', 'coffeepos'); ?></span>
            <button type="button" class="coffeepos-btn-icon" data-action="decrease-modal-quantity" aria-label="<?php esc_attr_e('Decrease quantity', 'coffeepos'); ?>">−</button>
            <output data-component="modal-quantity">1</output>
            <button type="button" class="coffeepos-btn-icon" data-action="increase-modal-quantity" aria-label="<?php esc_attr_e('Increase quantity', 'coffeepos'); ?>">+</button>
        </div>
        <div class="coffeepos-modal-actions">
            <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="close-product-modal"><?php esc_html_e('Cancel', 'coffeepos'); ?></button>
            <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="submit-product-modal" disabled data-component="submit-product-modal"><?php esc_html_e('Add to cart', 'coffeepos'); ?></button>
        </div>
    </section>
</div>

<template id="coffeepos-variation-group-template">
    <fieldset class="coffeepos-option-group" data-key="name" data-attr="data-attribute-name:name">
        <legend data-field="name"></legend>
        <div class="coffeepos-option-list" data-component="variation-option-list"></div>
    </fieldset>
</template>

<template id="coffeepos-variation-option-template">
    <button type="button" class="coffeepos-chip" data-action="select-variation-option" data-key="value" data-attr="data-attribute-name:attribute_name;data-attribute-value:value" data-field="value" aria-pressed="false"></button>
</template>
