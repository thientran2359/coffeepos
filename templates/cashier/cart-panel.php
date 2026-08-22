<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

?>
<header class="coffeepos-cart-header">
    <h2><?php esc_html_e('Cart', 'coffeepos'); ?></h2>
    <span class="coffeepos-cart-count" data-component="cart-count">0</span>
    <button type="button" class="coffeepos-btn coffeepos-btn-danger" data-action="clear-cart">
        <?php esc_html_e('Clear', 'coffeepos'); ?>
    </button>
</header>

<section class="coffeepos-order-type" data-component="order-type" data-state="normal">
    <h3><?php esc_html_e('Order type', 'coffeepos'); ?></h3>
    <div class="coffeepos-order-type-options">
        <button type="button" class="coffeepos-chip" data-action="select-order-type" data-order-type="dine_in" aria-pressed="false">
            <?php esc_html_e('Dine-in', 'coffeepos'); ?>
        </button>
        <button type="button" class="coffeepos-chip is-active" data-action="select-order-type" data-order-type="takeaway" aria-pressed="true">
            <?php esc_html_e('Takeaway', 'coffeepos'); ?>
        </button>
    </div>
    <div class="coffeepos-table-placeholder" data-component="table-placeholder" data-state="hidden" hidden>
        <?php esc_html_e('Select table', 'coffeepos'); ?>
    </div>
</section>

<section class="coffeepos-customer-summary" data-component="customer-summary" data-state="empty">
    <h3><?php esc_html_e('Customer', 'coffeepos'); ?></h3>
    <p data-component="customer-name"><?php esc_html_e('Guest customer', 'coffeepos'); ?></p>
    <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="open-customer">
        <?php esc_html_e('Find customer', 'coffeepos'); ?>
    </button>
</section>

<section class="coffeepos-cart-items" data-component="cart-items" data-state="empty" aria-live="polite">
    <div data-component="cart-item-list"></div>
    <div class="coffeepos-empty-state" data-component="cart-empty" data-state="empty">
        <strong><?php esc_html_e('Your cart is empty', 'coffeepos'); ?></strong>
        <p><?php esc_html_e('Select a product to start an order.', 'coffeepos'); ?></p>
    </div>
</section>

<template id="coffeepos-cart-item-template">
    <article class="coffeepos-cart-item" data-component="cart-item" data-key="item_id" data-attr="data-item-id:item_id">
        <div class="coffeepos-cart-item-main">
            <strong data-field="product_name"></strong>
            <p data-field="configuration_summary"></p>
            <p data-field="note_display"></p>
        </div>
        <div class="coffeepos-cart-item-actions">
            <button type="button" class="coffeepos-btn-icon" data-action="decrease-quantity" data-attr="data-item-id:item_id" aria-label="<?php esc_attr_e('Decrease quantity', 'coffeepos'); ?>">−</button>
            <span data-component="cart-item-quantity" data-field="quantity"></span>
            <button type="button" class="coffeepos-btn-icon" data-action="increase-quantity" data-attr="data-item-id:item_id" aria-label="<?php esc_attr_e('Increase quantity', 'coffeepos'); ?>">+</button>
            <button type="button" class="coffeepos-btn-icon" data-action="edit-cart-item" data-attr="data-item-id:item_id" aria-label="<?php esc_attr_e('Edit item', 'coffeepos'); ?>">✎</button>
            <button type="button" class="coffeepos-btn-icon" data-action="remove-cart-item" data-attr="data-item-id:item_id" aria-label="<?php esc_attr_e('Remove item', 'coffeepos'); ?>">×</button>
        </div>
        <div class="coffeepos-cart-item-price"><span data-field="line_total_display"></span></div>
    </article>
</template>

<section class="coffeepos-coupon" data-component="coupon" data-state="empty">
    <h3><?php esc_html_e('Coupon', 'coffeepos'); ?></h3>
    <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="open-coupon">
        <?php esc_html_e('Add coupon', 'coffeepos'); ?>
    </button>
    <p class="coffeepos-placeholder"><?php esc_html_e('No coupon applied', 'coffeepos'); ?></p>
</section>

<section class="coffeepos-cart-summary" data-component="cart-summary" data-state="normal">
    <div>
        <span><?php esc_html_e('Subtotal', 'coffeepos'); ?></span>
        <strong>0đ</strong>
    </div>
    <div>
        <span><?php esc_html_e('Discount', 'coffeepos'); ?></span>
        <strong>0đ</strong>
    </div>
    <div class="is-total">
        <span><?php esc_html_e('Total', 'coffeepos'); ?></span>
        <strong>0đ</strong>
    </div>
</section>

<section class="coffeepos-checkout" data-component="checkout" data-state="disabled">
    <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="checkout" disabled>
        <?php esc_html_e('Checkout', 'coffeepos'); ?>
    </button>
</section>
