<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<div class="coffeepos-modal" data-component="payment-success" data-state="closed" hidden>
    <div class="coffeepos-modal-backdrop"></div>
    <section class="coffeepos-modal-dialog coffeepos-success-dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-success-title">
        <h3 id="coffeepos-success-title"><?php esc_html_e('Payment successful', 'coffeepos'); ?></h3>
        <p><?php esc_html_e('Order', 'coffeepos'); ?> <strong data-field="success-order-number"></strong></p>
        <p><?php esc_html_e('Total', 'coffeepos'); ?> <strong data-field="success-total"></strong></p>
        <p data-field="success-change"></p>
        <div class="coffeepos-modal-actions">
            <button type="button" class="coffeepos-btn" data-action="print-receipt"><?php esc_html_e('Print receipt', 'coffeepos'); ?></button>
            <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="start-new-order"><?php esc_html_e('Start new order', 'coffeepos'); ?></button>
        </div>
    </section>
</div>
