<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<template id="coffeepos-kds-order-card-template">
    <article class="coffeepos-kds-card" data-component="kds-order-card" data-key="id" data-attr="data-order-id:id;data-state:kds.state;data-revision:kds.revision;data-received-at:received_at">
        <header><div><span><?php esc_html_e('Order', 'coffeepos'); ?></span> <strong data-field="number"></strong></div><span class="coffeepos-kds-card__state" data-field="kds.state_label"></span></header>
        <div class="coffeepos-kds-card__meta"><span data-field="service_label"></span><span data-field="received_time"></span><strong data-component="kds-timer">00:00</strong></div>
        <p class="coffeepos-order-note" data-field="order_note" data-attr="hidden:hide_order_note"></p>
        <div class="coffeepos-kds-card__items" data-component="kds-order-items"></div>
        <button type="button" class="coffeepos-btn coffeepos-kds-card__action" data-action="transition-kds-order" data-attr="data-target-state:next_action.target_state"><span data-field="next_action.label"></span></button>
    </article>
</template>
<template id="coffeepos-kds-order-item-template">
    <div class="coffeepos-kds-item" data-key="order_item_id">
        <div><strong><span data-field="quantity"></span>&times; <span data-field="product_name"></span></strong><small data-field="variation_summary"></small><small data-field="modifier_summary"></small></div>
        <p class="coffeepos-kds-item__notes"><span data-field="quick_note_summary"></span><span data-field="custom_note"></span></p>
    </div>
</template>
