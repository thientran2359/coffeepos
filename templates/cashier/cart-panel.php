<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
$membershipEnabled = (bool) \CoffeePOS\Infrastructure\Settings\Settings::get(\CoffeePOS\Infrastructure\Settings\Settings::OPTION_MEMBERSHIP_ENABLED);

?>
<header class="coffeepos-cart-header">
    <div>
        <span class="coffeepos-eyebrow"><?php esc_html_e('Current order', 'coffeepos'); ?></span>
        <h2><?php esc_html_e('Cart', 'coffeepos'); ?></h2>
    </div>
    <span class="coffeepos-cart-count" data-component="cart-count" aria-label="<?php esc_attr_e('Cart item count', 'coffeepos'); ?>">0</span>
    <button type="button" class="coffeepos-icon-button coffeepos-btn-danger" data-action="clear-cart" title="<?php esc_attr_e('Clear cart', 'coffeepos'); ?>" aria-label="<?php esc_attr_e('Clear cart', 'coffeepos'); ?>" disabled>
        <span aria-hidden="true">&#215;</span>
    </button>
</header>

<section class="coffeepos-order-type" data-component="order-type" data-state="normal">
    <div class="coffeepos-order-type-options">
        <button type="button" class="coffeepos-segment" data-action="select-order-type" data-order-type="dine_in" aria-pressed="false">
            <?php esc_html_e('Dine-in', 'coffeepos'); ?>
        </button>
        <button type="button" class="coffeepos-segment is-active" data-action="select-order-type" data-order-type="takeaway" aria-pressed="true">
            <?php esc_html_e('Takeaway', 'coffeepos'); ?>
        </button>
    </div>
    <div class="coffeepos-table-placeholder" data-component="table-placeholder" data-state="hidden" hidden>
        <button type="button" class="coffeepos-table-badge" data-action="open-table" title="<?php esc_attr_e('Select or change table', 'coffeepos'); ?>">
            <span class="coffeepos-table-badge__dot" aria-hidden="true"></span>
            <span data-component="selected-table-label"><?php esc_html_e('Select table', 'coffeepos'); ?></span>
        </button>
    </div>
</section>

<div class="coffeepos-cart-context-row" data-component="cart-context-row">
    <section class="coffeepos-customer-summary" data-component="customer-summary" data-state="empty" <?php echo $membershipEnabled ? '' : 'hidden'; ?>>
        <div>
            <h3><?php esc_html_e('Customer', 'coffeepos'); ?></h3>
            <p data-component="customer-name"><?php esc_html_e('Guest customer', 'coffeepos'); ?></p>
            <small data-component="customer-phone" hidden></small>
            <small data-component="customer-membership" hidden></small>
        </div>
        <button type="button" class="coffeepos-icon-button" data-action="open-customer" title="<?php esc_attr_e('Find customer', 'coffeepos'); ?>" aria-label="<?php esc_attr_e('Find customer', 'coffeepos'); ?>">
            <span aria-hidden="true">+</span>
        </button>
        <button type="button" class="coffeepos-link-button" data-action="remove-customer" hidden>
            <?php esc_html_e('Use guest', 'coffeepos'); ?>
        </button>
    </section>

    <section class="coffeepos-coupon" data-component="coupon" data-state="empty">
        <div>
            <h3><?php esc_html_e('Coupon', 'coffeepos'); ?></h3>
            <p data-component="coupon-empty"><?php esc_html_e('No coupon applied', 'coffeepos'); ?></p>
            <p data-component="coupon-applied" hidden>
                <strong data-component="coupon-code"><?php esc_html_e('COFFEE10', 'coffeepos'); ?></strong>
                <button type="button" class="coffeepos-link-button" data-action="remove-coupon"><?php esc_html_e('Remove', 'coffeepos'); ?></button>
            </p>
        </div>
        <button type="button" class="coffeepos-icon-button" data-action="open-coupon" title="<?php esc_attr_e('Add coupon', 'coffeepos'); ?>" aria-label="<?php esc_attr_e('Add coupon', 'coffeepos'); ?>">
            <span aria-hidden="true">+</span>
        </button>
    </section>
</div>

<section class="coffeepos-cart-items" data-component="cart-items" data-state="loading" aria-live="polite">
    <div data-component="cart-item-list"></div>
    <div data-component="cart-empty" hidden>
        <?php
        $emptyTitle = __('Your cart is empty', 'coffeepos');
        $emptyDescription = __('Select a product to start an order.', 'coffeepos');
        require COFFEEPOS_PATH . 'templates/components/empty-state.php';
        ?>
    </div>
    <div data-component="cart-loading">
        <?php
        $loadingVariant = 'component';
        $loadingLabel = __('Updating cart...', 'coffeepos');
        require COFFEEPOS_PATH . 'templates/components/loading.php';
        ?>
    </div>
    <div data-component="cart-error" hidden>
        <?php
        $errorMessage = __('The cart could not be updated.', 'coffeepos');
        $errorAction = 'retry-cart';
        require COFFEEPOS_PATH . 'templates/components/error-state.php';
        ?>
    </div>
</section>

<template id="coffeepos-cart-item-template">
    <article class="coffeepos-cart-item" data-component="cart-item" data-state="normal" data-key="item_id" data-attr="data-cart-item-key:item_id">
        <div class="coffeepos-cart-item-main">
            <strong data-field="product_name"></strong>
            <p data-field="variation_summary"></p>
            <p data-field="modifier_summary"></p>
            <p data-field="note_display"></p>
            <span class="coffeepos-cart-unit-price" data-field="unit_price_display"></span>
        </div>
        <div class="coffeepos-cart-item-actions">
            <button type="button" class="coffeepos-icon-button" data-action="decrease-quantity" data-attr="data-cart-item-key:item_id" aria-label="<?php esc_attr_e('Decrease quantity', 'coffeepos'); ?>">&#8722;</button>
            <span data-component="cart-item-quantity" data-field="quantity"></span>
            <button type="button" class="coffeepos-icon-button" data-action="increase-quantity" data-attr="data-cart-item-key:item_id" aria-label="<?php esc_attr_e('Increase quantity', 'coffeepos'); ?>">+</button>
            <button type="button" class="coffeepos-icon-button" data-action="edit-cart-item" data-attr="data-cart-item-key:item_id" aria-label="<?php esc_attr_e('Edit item', 'coffeepos'); ?>">E</button>
            <button type="button" class="coffeepos-icon-button" data-action="remove-cart-item" data-attr="data-cart-item-key:item_id" aria-label="<?php esc_attr_e('Remove item', 'coffeepos'); ?>">&#215;</button>
        </div>
        <div class="coffeepos-cart-item-price"><span data-field="line_total_display"></span></div>
    </article>
</template>

<button type="button" class="coffeepos-order-note-trigger" data-component="order-note-trigger" data-action="open-order-note" data-state="empty">
    <span>
        <strong><?php esc_html_e('Order note', 'coffeepos'); ?></strong>
        <small data-component="order-note-summary"><?php esc_html_e('No order note', 'coffeepos'); ?></small>
    </span>
    <span class="coffeepos-order-note-trigger__action" aria-hidden="true">+</span>
</button>

<section class="coffeepos-cart-summary" data-component="cart-summary" data-state="normal">
    <div>
        <span><?php esc_html_e('Subtotal', 'coffeepos'); ?></span>
        <strong data-component="cart-subtotal">0 VND</strong>
    </div>
    <div>
        <span><?php esc_html_e('Discount', 'coffeepos'); ?></span>
        <strong data-component="cart-discount">0 VND</strong>
    </div>
    <div class="is-total">
        <span><?php esc_html_e('Total', 'coffeepos'); ?></span>
        <strong data-component="cart-total">0 VND</strong>
    </div>
</section>

<section class="coffeepos-checkout" data-component="checkout" data-state="disabled">
    <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="checkout" disabled>
        <?php esc_html_e('Checkout', 'coffeepos'); ?>
    </button>
</section>
