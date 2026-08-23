<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<section class="coffeepos-receipt" data-component="receipt" hidden>
    <h1 data-field="receipt-store-name"></h1>
    <p data-field="receipt-store-address"></p>
    <h2><span><?php esc_html_e('Order', 'coffeepos'); ?></span> <span data-field="receipt-order-number"></span></h2>
    <div data-component="receipt-items"></div>
    <template id="coffeepos-receipt-item-template">
        <article class="coffeepos-receipt-item">
            <span data-field="name"></span> &times; <span data-field="quantity"></span>
            <strong data-field="total"></strong>
            <small data-field="note"></small>
        </article>
    </template>
    <p class="coffeepos-receipt-total"><?php esc_html_e('Total', 'coffeepos'); ?>: <strong data-field="receipt-total"></strong></p>
</section>
