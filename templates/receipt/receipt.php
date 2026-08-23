<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<section class="coffeepos-receipt" data-component="receipt" data-state="idle" hidden aria-label="<?php esc_attr_e('Receipt', 'coffeepos'); ?>">
    <header class="coffeepos-receipt__header"><h1 data-field="receipt-store-name"></h1><p data-field="receipt-store-address"></p><h2><span><?php esc_html_e('Order', 'coffeepos'); ?></span> #<span data-field="receipt-order-number"></span></h2><p data-field="receipt-created-at"></p></header>
    <dl class="coffeepos-receipt__meta">
        <div><dt><?php esc_html_e('Cashier', 'coffeepos'); ?></dt><dd data-field="receipt-cashier"></dd></div>
        <div><dt><?php esc_html_e('Customer', 'coffeepos'); ?></dt><dd><span data-field="receipt-customer"></span> <span data-field="receipt-customer-phone"></span></dd></div>
        <div><dt><?php esc_html_e('Service', 'coffeepos'); ?></dt><dd data-field="receipt-service"></dd></div>
        <div><dt><?php esc_html_e('Payment', 'coffeepos'); ?></dt><dd data-field="receipt-payment"></dd></div>
    </dl>
    <div class="coffeepos-receipt__items" data-component="receipt-items"></div>
    <template id="coffeepos-receipt-item-template"><article class="coffeepos-receipt-item"><strong data-field="name"></strong><span><span data-field="quantity"></span> × <span data-field="unit_total.display"></span></span><strong data-field="total.display"></strong><small data-field="variation_summary"></small><small data-field="modifier_summary"></small><small data-field="quick_note_summary"></small><small data-field="note"></small></article></template>
    <dl class="coffeepos-receipt__totals"><div><dt><?php esc_html_e('Subtotal', 'coffeepos'); ?></dt><dd data-field="receipt-subtotal"></dd></div><div><dt><?php esc_html_e('Discount', 'coffeepos'); ?></dt><dd data-field="receipt-discount"></dd></div><div data-component="receipt-refunded-row"><dt><?php esc_html_e('Refunded', 'coffeepos'); ?></dt><dd data-field="receipt-refunded"></dd></div><div class="is-total"><dt><?php esc_html_e('Total', 'coffeepos'); ?></dt><dd data-field="receipt-total"></dd></div><div data-component="receipt-received-row"><dt><?php esc_html_e('Cash received', 'coffeepos'); ?></dt><dd data-field="receipt-received"></dd></div><div data-component="receipt-change-row"><dt><?php esc_html_e('Change', 'coffeepos'); ?></dt><dd data-field="receipt-change"></dd></div></dl>
    <section class="coffeepos-receipt__order-note" data-component="receipt-order-note" hidden><strong><?php esc_html_e('Order note', 'coffeepos'); ?></strong><p data-field="receipt-order-note"></p></section>
    <footer><?php esc_html_e('Thank you!', 'coffeepos'); ?></footer>
</section>
