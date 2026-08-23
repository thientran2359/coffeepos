<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<template id="coffeepos-order-queue-card-template">
    <article class="coffeepos-queue-card" data-component="order-queue-card" data-key="id" data-attr="data-order-id:id;data-state:kds.state;data-revision:kds.revision">
        <header><div><span><?php esc_html_e('Order', 'coffeepos'); ?></span> <strong data-field="number"></strong></div><span class="coffeepos-queue-card__state" data-field="kds.state_label"></span></header>
        <dl><div><dt><?php esc_html_e('Customer', 'coffeepos'); ?></dt><dd data-field="customer.display_name"></dd></div><div><dt><?php esc_html_e('Service', 'coffeepos'); ?></dt><dd data-field="service_label"></dd></div><div><dt><?php esc_html_e('Received', 'coffeepos'); ?></dt><dd data-field="received_time"></dd></div><div><dt><?php esc_html_e('Total', 'coffeepos'); ?></dt><dd data-field="total.display"></dd></div></dl>
        <p class="coffeepos-order-note" data-field="order_note" data-attr="hidden:hide_order_note"></p>
        <div class="coffeepos-queue-card__actions">
            <button type="button" class="coffeepos-btn" data-action="complete-order" data-attr="hidden:actions.hide_complete"><?php esc_html_e('Complete', 'coffeepos'); ?></button>
            <button type="button" class="coffeepos-btn coffeepos-btn-danger" data-action="cancel-order" data-attr="hidden:actions.hide_cancel"><?php esc_html_e('Cancel', 'coffeepos'); ?></button>
            <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="reprint-order" data-attr="hidden:actions.hide_reprint"><?php esc_html_e('Reprint', 'coffeepos'); ?></button>
        </div>
    </article>
</template>
