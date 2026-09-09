<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="coffeepos-modal coffeepos-held-carts-modal" data-component="held-carts-modal" data-state="closed" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-held-carts"></div>
    <section class="coffeepos-modal-dialog coffeepos-held-carts-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-held-carts-title" tabindex="-1">
        <header class="coffeepos-modal-header">
            <div>
                <span class="coffeepos-eyebrow"><?php esc_html_e('Suspended orders', 'coffeepos'); ?></span>
                <h3 id="coffeepos-held-carts-title"><?php esc_html_e('Held carts', 'coffeepos'); ?></h3>
            </div>
            <button type="button" class="coffeepos-icon-button" data-action="close-held-carts" aria-label="<?php esc_attr_e('Close held carts', 'coffeepos'); ?>">&#215;</button>
        </header>

        <div class="coffeepos-held-carts-toolbar">
            <p><?php esc_html_e('Save the current cart and continue with a new customer.', 'coffeepos'); ?></p>
            <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="open-hold-cart" data-component="hold-current-cart"><?php esc_html_e('Hold cart', 'coffeepos'); ?></button>
        </div>

        <div class="coffeepos-held-carts-state" data-component="held-carts-loading">
            <?php $loadingVariant = 'component'; $loadingLabel = __('Loading held carts...', 'coffeepos'); require COFFEEPOS_PATH . 'templates/components/loading.php'; ?>
        </div>
        <div class="coffeepos-held-carts-state" data-component="held-carts-error" role="alert" hidden>
            <p data-component="held-carts-error-message"></p>
            <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="retry-held-carts"><?php esc_html_e('Try again', 'coffeepos'); ?></button>
        </div>
        <div class="coffeepos-held-carts-state" data-component="held-carts-empty" hidden>
            <strong><?php esc_html_e('No held carts', 'coffeepos'); ?></strong>
            <p><?php esc_html_e('Held orders will appear here.', 'coffeepos'); ?></p>
        </div>
        <div class="coffeepos-held-carts-list" data-component="held-carts-list" hidden></div>
    </section>
</div>

<div class="coffeepos-modal coffeepos-hold-cart-modal" data-component="hold-cart-modal" data-state="closed" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-hold-cart"></div>
    <section class="coffeepos-modal-dialog coffeepos-hold-cart-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-hold-cart-title" tabindex="-1">
        <header class="coffeepos-modal-header">
            <div>
                <span class="coffeepos-eyebrow"><?php esc_html_e('Current order', 'coffeepos'); ?></span>
                <h3 id="coffeepos-hold-cart-title"><?php esc_html_e('Hold cart', 'coffeepos'); ?></h3>
            </div>
            <button type="button" class="coffeepos-icon-button" data-action="close-hold-cart" aria-label="<?php esc_attr_e('Close hold cart', 'coffeepos'); ?>">&#215;</button>
        </header>
        <form class="coffeepos-hold-cart-form" data-component="hold-cart-form">
            <label class="coffeepos-field">
                <span><?php esc_html_e('Cart label', 'coffeepos'); ?></span>
                <small><?php esc_html_e('Use a customer name, table, or another short identifier.', 'coffeepos'); ?></small>
                <input type="text" maxlength="191" autocomplete="off" data-component="hold-cart-label" required>
            </label>
            <p class="coffeepos-field-error" data-component="hold-cart-error" role="alert" hidden></p>
            <footer class="coffeepos-modal-actions">
                <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="close-hold-cart"><?php esc_html_e('Cancel', 'coffeepos'); ?></button>
                <button type="submit" class="coffeepos-btn coffeepos-btn-primary" data-component="hold-cart-submit"><?php esc_html_e('Hold cart', 'coffeepos'); ?></button>
            </footer>
        </form>
    </section>
</div>

<template id="coffeepos-held-cart-template">
    <article class="coffeepos-held-cart" data-key="id" data-attr="data-held-cart-id:id">
        <div class="coffeepos-held-cart__heading">
            <strong data-field="label"></strong>
            <time data-field="created_at_display"></time>
        </div>
        <div class="coffeepos-held-cart__meta">
            <span data-field="customer_label"></span>
            <span><b data-field="total_quantity"></b> <?php esc_html_e('items', 'coffeepos'); ?></span>
            <span data-component="held-cart-service"></span>
        </div>
        <div class="coffeepos-held-cart__actions">
            <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="resume-held-cart"><?php esc_html_e('Resume', 'coffeepos'); ?></button>
            <button type="button" class="coffeepos-btn coffeepos-btn-danger" data-action="delete-held-cart"><?php esc_html_e('Delete', 'coffeepos'); ?></button>
        </div>
    </article>
</template>
