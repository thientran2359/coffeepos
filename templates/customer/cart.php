<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<aside class="coffeepos-customer-cart" data-component="customer-cart" data-state="idle">
    <header>
        <span class="coffeepos-eyebrow"><?php esc_html_e('Your order', 'coffeepos'); ?></span>
        <h2 data-field="customer-state-title"><?php esc_html_e('Ready when you are', 'coffeepos'); ?></h2>
        <p data-field="customer-service"></p>
        <p data-field="customer-name"></p>
        <p data-field="customer-membership"></p>
    </header>
    <div class="coffeepos-customer-cart__items" data-component="customer-cart-items"></div>
    <p data-component="customer-cart-empty"><?php esc_html_e('Your selected items will appear here.', 'coffeepos'); ?></p>
    <section class="coffeepos-customer-totals" data-component="customer-totals">
        <p><span><?php esc_html_e('Subtotal', 'coffeepos'); ?></span><strong data-field="customer-subtotal"></strong></p>
        <p><span><?php esc_html_e('Discount', 'coffeepos'); ?></span><strong data-field="customer-discount"></strong></p>
        <p class="is-total"><span><?php esc_html_e('Total', 'coffeepos'); ?></span><strong data-field="customer-total"></strong></p>
    </section>
    <section class="coffeepos-customer-payment" data-component="customer-payment" data-state="idle" hidden>
        <p data-field="customer-payment-status"></p>
        <span class="coffeepos-customer-payment__amount-label"><?php esc_html_e('Amount due', 'coffeepos'); ?></span>
        <strong data-field="customer-payment-amount"></strong>
        <div data-component="customer-vietqr" hidden><img alt="<?php esc_attr_e('VietQR payment code', 'coffeepos'); ?>"></div>
        <p data-field="customer-payment-reference"></p>
        <p data-field="customer-payment-change"></p>
        <section class="coffeepos-customer-payment-summary" aria-labelledby="coffeepos-customer-payment-summary-title">
            <h3 id="coffeepos-customer-payment-summary-title"><?php esc_html_e('Order details', 'coffeepos'); ?></h3>
            <div class="coffeepos-customer-payment-summary__items" data-component="customer-payment-items"></div>
            <div class="coffeepos-customer-payment-summary__totals">
                <p><span><?php esc_html_e('Subtotal', 'coffeepos'); ?></span><strong data-field="customer-payment-subtotal"></strong></p>
                <p><span><?php esc_html_e('Discount', 'coffeepos'); ?></span><strong data-field="customer-payment-discount"></strong></p>
                <p class="is-total"><span><?php esc_html_e('Total', 'coffeepos'); ?></span><strong data-field="customer-payment-total"></strong></p>
            </div>
        </section>
    </section>
    <section class="coffeepos-customer-thank-you" data-component="customer-thank-you" hidden>
        <strong><?php esc_html_e('Thank you!', 'coffeepos'); ?></strong>
        <p><?php esc_html_e('Your order has been received.', 'coffeepos'); ?></p>
    </section>
    <section class="coffeepos-customer-sync-state" data-component="customer-sync-state" hidden>
        <p data-field="customer-sync-message"></p>
        <button type="button" class="coffeepos-btn" data-action="retry-display-sync"><?php esc_html_e('Reconnect display', 'coffeepos'); ?></button>
    </section>
</aside>
