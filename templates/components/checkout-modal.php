<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
$paymentMethods = \CoffeePOS\Infrastructure\Settings\Settings::enabledPaymentMethods();
?>
<div class="coffeepos-modal" data-component="checkout-modal" data-state="closed" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-checkout"></div>
    <section class="coffeepos-modal-dialog coffeepos-checkout-dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-checkout-title">
        <header class="coffeepos-modal-header">
            <h3 id="coffeepos-checkout-title"><?php esc_html_e('Checkout', 'coffeepos'); ?></h3>
            <button type="button" class="coffeepos-icon-button" data-action="close-checkout" aria-label="<?php esc_attr_e('Close', 'coffeepos'); ?>">&times;</button>
        </header>
        <div class="coffeepos-checkout-total"><span><?php esc_html_e('Total', 'coffeepos'); ?></span><strong data-field="payment-total"></strong></div>
        <div class="coffeepos-order-type-options" role="tablist">
            <?php if (in_array('cash', $paymentMethods, true)) : ?><button type="button" class="coffeepos-segment" data-action="select-payment-method" data-payment-method="cash" aria-pressed="false"><?php esc_html_e('Cash', 'coffeepos'); ?></button><?php endif; ?>
            <?php if (in_array('bank_transfer', $paymentMethods, true)) : ?><button type="button" class="coffeepos-segment" data-action="select-payment-method" data-payment-method="bank_transfer" aria-pressed="false"><?php esc_html_e('Bank transfer', 'coffeepos'); ?></button><?php endif; ?>
        </div>
        <section data-component="cash-payment" hidden>
            <label for="coffeepos-cash-received"><?php esc_html_e('Received amount', 'coffeepos'); ?></label>
            <input id="coffeepos-cash-received" inputmode="decimal" data-field="cash-received" autocomplete="off">
            <div class="coffeepos-quick-cash">
                <?php foreach ([10000, 20000, 50000, 100000, 200000, 500000] as $amount) : ?>
                    <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="cash-quick-amount" data-amount="<?php echo esc_attr((string) $amount); ?>">+<?php echo esc_html((string) ($amount / 1000)); ?>k</button>
                <?php endforeach; ?>
                <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="cash-exact"><?php esc_html_e('Exact', 'coffeepos'); ?></button>
            </div>
            <p data-field="cash-change" role="status"></p>
        </section>
        <section data-component="bank-transfer-payment" data-state="initializing" hidden>
            <p><?php esc_html_e('The order remains pending until a trusted provider verifies payment.', 'coffeepos'); ?></p>
            <div data-component="vietqr" hidden><img alt="<?php esc_attr_e('VietQR payment code', 'coffeepos'); ?>"></div>
            <p><strong data-field="payment-reference"></strong></p>
            <p data-field="payment-status"></p>
            <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="refresh-payment-status" hidden><?php esc_html_e('Refresh status', 'coffeepos'); ?></button>
            <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="start-fresh-order" hidden><?php esc_html_e('Start new order', 'coffeepos'); ?></button>
        </section>
        <p data-component="checkout-error" class="coffeepos-form-error" role="alert" hidden></p>
        <div class="coffeepos-modal-actions">
            <button type="button" class="coffeepos-btn" data-action="close-checkout"><?php esc_html_e('Cancel', 'coffeepos'); ?></button>
            <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="submit-checkout"><?php esc_html_e('Complete checkout', 'coffeepos'); ?></button>
        </div>
    </section>
</div>
