<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="coffeepos-modal coffeepos-product-modal" data-component="product-modal" data-state="closed" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-product-modal"></div>
    <section class="coffeepos-modal-dialog coffeepos-product-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-product-modal-title" tabindex="-1">
        <header class="coffeepos-modal-header">
            <div>
                <span class="coffeepos-eyebrow" data-component="product-modal-mode"><?php esc_html_e('Add item', 'coffeepos'); ?></span>
                <h3 id="coffeepos-product-modal-title" data-component="product-modal-title"></h3>
            </div>
            <button type="button" class="coffeepos-icon-button" data-action="close-product-modal" aria-label="<?php esc_attr_e('Close product configuration', 'coffeepos'); ?>">&#215;</button>
        </header>

        <div class="coffeepos-product-modal__loading" data-component="product-modal-loading">
            <?php
            $loadingVariant = 'component';
            $loadingLabel = __('Loading product...', 'coffeepos');
            require COFFEEPOS_PATH . 'templates/components/loading.php';
            ?>
        </div>

        <div class="coffeepos-product-modal__error" data-component="product-modal-error" data-state="error" role="alert" hidden>
            <strong><?php esc_html_e('Product configuration unavailable', 'coffeepos'); ?></strong>
            <p data-component="product-modal-error-message"></p>
        </div>

        <div class="coffeepos-product-modal__content" data-component="product-modal-content" hidden>
            <div class="coffeepos-product-modal__summary">
                <span><?php esc_html_e('Current price', 'coffeepos'); ?></span>
                <strong data-component="product-modal-price"></strong>
            </div>

            <section class="coffeepos-product-config-section" data-component="variation-section" hidden>
                <h4><?php esc_html_e('Variations', 'coffeepos'); ?></h4>
                <div data-component="variation-selector"></div>
                <p class="coffeepos-field-error" data-component="variation-error" role="status" hidden></p>
            </section>

            <section class="coffeepos-product-config-section" data-component="modifier-section" hidden>
                <h4><?php esc_html_e('Options', 'coffeepos'); ?></h4>
                <div data-component="modifier-selector"></div>
            </section>

            <section class="coffeepos-field coffeepos-product-note-field">
                <label for="coffeepos-product-custom-note"><?php esc_html_e('Item note', 'coffeepos'); ?></label>
                <small><?php esc_html_e('Quick-note labels appear on separate lines. Add any other preparation instructions here.', 'coffeepos'); ?></small>
                <div class="coffeepos-product-note-field__quick-notes" data-component="quick-notes-section" hidden>
                    <div class="coffeepos-option-list" data-component="quick-notes"></div>
                </div>
                <textarea id="coffeepos-product-custom-note" data-component="product-custom-note" rows="3" maxlength="500"></textarea>
            </section>

            <div class="coffeepos-quantity-control" data-component="quantity-control">
                <span><?php esc_html_e('Quantity', 'coffeepos'); ?></span>
                <div>
                    <button type="button" class="coffeepos-icon-button" data-action="decrease-quantity" aria-label="<?php esc_attr_e('Decrease quantity', 'coffeepos'); ?>">&#8722;</button>
                    <output data-component="configuration-quantity">1</output>
                    <button type="button" class="coffeepos-icon-button" data-action="increase-quantity" aria-label="<?php esc_attr_e('Increase quantity', 'coffeepos'); ?>">+</button>
                </div>
            </div>

            <footer class="coffeepos-modal-actions">
                <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="close-product-modal"><?php esc_html_e('Cancel', 'coffeepos'); ?></button>
                <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="add-to-cart" data-component="submit-product-configuration" disabled>
                    <?php esc_html_e('Add to cart', 'coffeepos'); ?>
                </button>
            </footer>
        </div>
    </section>
</div>

<template id="coffeepos-variation-group-template">
    <fieldset class="coffeepos-option-group" data-key="name" data-attr="data-attribute-name:name">
        <legend data-field="label"></legend>
        <div class="coffeepos-option-list" data-component="variation-option-list"></div>
    </fieldset>
</template>

<template id="coffeepos-variation-option-template">
    <button type="button" class="coffeepos-option-button" data-action="select-variation" data-key="value" data-attr="data-attribute-name:attribute_name;data-attribute-value:value" aria-pressed="false">
        <span data-field="label"></span>
    </button>
</template>

<template id="coffeepos-modifier-group-template">
    <fieldset class="coffeepos-option-group" data-component="modifier-group" data-key="id" data-attr="data-modifier-group-id:id;data-selection:selection;data-minimum:minimum;data-maximum:maximum">
        <legend data-field="label"></legend>
        <p class="coffeepos-option-rule" data-field="rule_label"></p>
        <div class="coffeepos-option-list" data-component="modifier-option-list"></div>
    </fieldset>
</template>

<template id="coffeepos-modifier-option-template">
    <button type="button" class="coffeepos-option-button" data-action="select-modifier" data-key="id" data-attr="data-modifier-group-id:group_id;data-modifier-option-id:id" aria-pressed="false">
        <span data-field="label"></span>
    </button>
</template>

<template id="coffeepos-quick-note-template">
    <button type="button" class="coffeepos-option-button" data-action="toggle-quick-note" data-key="id" data-attr="data-quick-note-id:id" aria-pressed="false">
        <span data-field="label"></span>
    </button>
</template>
