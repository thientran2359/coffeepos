<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="coffeepos-modal coffeepos-stock-modal" data-component="stock-modal" data-state="closed" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-stock-modal"></div>
    <section class="coffeepos-modal-dialog coffeepos-stock-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-stock-modal-title" tabindex="-1">
        <header class="coffeepos-modal-header">
            <div>
                <span class="coffeepos-eyebrow"><?php esc_html_e('Inventory', 'coffeepos'); ?></span>
                <h3 id="coffeepos-stock-modal-title" data-component="stock-modal-title"><?php esc_html_e('Update stock', 'coffeepos'); ?></h3>
            </div>
            <button type="button" class="coffeepos-icon-button" data-action="close-stock-modal" aria-label="<?php esc_attr_e('Close stock editor', 'coffeepos'); ?>">&#215;</button>
        </header>

        <div class="coffeepos-stock-modal__loading" data-component="stock-modal-loading">
            <?php $loadingVariant = 'component'; $loadingLabel = __('Loading stock...', 'coffeepos'); require COFFEEPOS_PATH . 'templates/components/loading.php'; ?>
        </div>

        <div class="coffeepos-stock-modal__error" data-component="stock-modal-error" role="alert" hidden>
            <strong><?php esc_html_e('Stock editor unavailable', 'coffeepos'); ?></strong>
            <p data-component="stock-modal-error-message"></p>
        </div>

        <form class="coffeepos-stock-modal__content" data-component="stock-modal-content" hidden>
            <label class="coffeepos-field" data-component="stock-target-field" hidden>
                <span><?php esc_html_e('Product option', 'coffeepos'); ?></span>
                <select data-component="stock-target"></select>
            </label>

            <div class="coffeepos-stock-current">
                <span><?php esc_html_e('Current stock', 'coffeepos'); ?></span>
                <strong data-component="stock-current-value"></strong>
            </div>

            <label class="coffeepos-field" data-component="stock-quantity-field" hidden>
                <span><?php esc_html_e('New stock quantity', 'coffeepos'); ?></span>
                <input type="number" min="0" step="1" inputmode="numeric" data-component="stock-quantity">
            </label>

            <fieldset class="coffeepos-stock-status" data-component="stock-status-field" hidden>
                <legend><?php esc_html_e('New stock status', 'coffeepos'); ?></legend>
                <label><input type="radio" name="coffeepos_stock_status" value="instock"> <?php esc_html_e('In stock', 'coffeepos'); ?></label>
                <label><input type="radio" name="coffeepos_stock_status" value="outofstock"> <?php esc_html_e('Out of stock', 'coffeepos'); ?></label>
            </fieldset>

            <label class="coffeepos-field">
                <span><?php esc_html_e('Reason', 'coffeepos'); ?></span>
                <small><?php esc_html_e('Required for the inventory audit log.', 'coffeepos'); ?></small>
                <textarea rows="2" maxlength="200" data-component="stock-reason" placeholder="<?php esc_attr_e('Example: New delivery or sold out at counter', 'coffeepos'); ?>" required></textarea>
            </label>

            <p class="coffeepos-field-error" data-component="stock-validation-error" role="alert" hidden></p>

            <footer class="coffeepos-modal-actions">
                <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="close-stock-modal"><?php esc_html_e('Cancel', 'coffeepos'); ?></button>
                <button type="submit" class="coffeepos-btn coffeepos-btn-primary" data-component="stock-submit"><?php esc_html_e('Update stock', 'coffeepos'); ?></button>
            </footer>
        </form>
    </section>
</div>

<template id="coffeepos-stock-target-template">
    <option data-key="id" data-attr="value:id" data-field="name"></option>
</template>
