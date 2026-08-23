<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
$statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
?>
<section class="coffeepos-history" data-component="order-history-screen" data-state="loading">
    <header class="coffeepos-operations-header">
        <div><p class="coffeepos-eyebrow"><?php esc_html_e('CoffeePOS operations', 'coffeepos'); ?></p><h1><?php esc_html_e('Order History', 'coffeepos'); ?></h1></div>
        <a class="coffeepos-button coffeepos-button--secondary" href="<?php echo esc_url(home_url('/' . trim(\CoffeePOS\Infrastructure\Settings\Settings::getPosBaseSlug(), '/') . '/cashier/')); ?>"><?php esc_html_e('Back to Cashier', 'coffeepos'); ?></a>
    </header>

    <form class="coffeepos-history-filters" data-component="history-filters">
        <label><?php esc_html_e('From', 'coffeepos'); ?><input type="date" name="date_from"></label>
        <label><?php esc_html_e('To', 'coffeepos'); ?><input type="date" name="date_to"></label>
        <label><?php esc_html_e('Status', 'coffeepos'); ?><select name="status"><option value="all"><?php esc_html_e('All statuses', 'coffeepos'); ?></option><?php foreach ($statuses as $key => $label) : ?><option value="<?php echo esc_attr(preg_replace('/^wc-/', '', (string) $key)); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
        <label><?php esc_html_e('Order type', 'coffeepos'); ?><select name="order_type"><option value="all"><?php esc_html_e('All types', 'coffeepos'); ?></option><option value="takeaway"><?php esc_html_e('Takeaway', 'coffeepos'); ?></option><option value="dine_in"><?php esc_html_e('Dine-in', 'coffeepos'); ?></option></select></label>
        <label class="coffeepos-history-search"><?php esc_html_e('Search', 'coffeepos'); ?><input type="search" name="search" placeholder="<?php esc_attr_e('Order, customer, product…', 'coffeepos'); ?>"></label>
        <button class="coffeepos-button coffeepos-button--primary" type="submit"><?php esc_html_e('Apply filters', 'coffeepos'); ?></button>
    </form>

    <p class="coffeepos-inline-error" data-component="history-error" hidden></p>
    <div class="coffeepos-history-summary"><strong data-field="history-count">0</strong> <?php esc_html_e('orders found', 'coffeepos'); ?></div>
    <div class="coffeepos-history-list" data-component="history-list"></div>
    <p class="coffeepos-history-empty" data-component="history-empty" hidden><?php esc_html_e('No CoffeePOS orders match these filters.', 'coffeepos'); ?></p>
    <nav class="coffeepos-history-pagination" aria-label="<?php esc_attr_e('Order history pages', 'coffeepos'); ?>"><button type="button" data-action="previous-page"><?php esc_html_e('Previous', 'coffeepos'); ?></button><span data-field="page-status"></span><button type="button" data-action="next-page"><?php esc_html_e('Next', 'coffeepos'); ?></button></nav>

    <template data-template="history-order-card"><article class="coffeepos-history-order-card">
        <button type="button" class="coffeepos-history-order-main" data-action="view-order"><span><strong data-field="number"></strong><small data-field="created"></small></span><span><strong data-field="customer"></strong><small data-field="service"></small></span><span><span class="coffeepos-status-badge" data-field="status"></span><strong data-field="total"></strong></span></button>
    </article></template>

    <dialog class="coffeepos-history-dialog" data-component="history-detail-dialog" aria-labelledby="coffeepos-history-detail-title">
        <header><div><p data-field="detail-status"></p><h2 id="coffeepos-history-detail-title" data-field="detail-number"></h2></div><button type="button" data-action="close-detail" aria-label="<?php esc_attr_e('Close order detail', 'coffeepos'); ?>">×</button></header>
        <div class="coffeepos-history-detail-meta"><p><span><?php esc_html_e('Created', 'coffeepos'); ?></span><strong data-field="detail-created"></strong></p><p><span><?php esc_html_e('Customer', 'coffeepos'); ?></span><strong data-field="detail-customer"></strong></p><p><span><?php esc_html_e('Service', 'coffeepos'); ?></span><strong data-field="detail-service"></strong></p><p><span><?php esc_html_e('Payment', 'coffeepos'); ?></span><strong data-field="detail-payment"></strong></p></div>
        <div class="coffeepos-history-detail-items" data-component="detail-items"></div>
        <section class="coffeepos-order-note" data-component="history-order-note" hidden><strong><?php esc_html_e('Order note', 'coffeepos'); ?></strong><p data-field="detail-order-note"></p></section>
        <dl class="coffeepos-history-totals"><div><dt><?php esc_html_e('Subtotal', 'coffeepos'); ?></dt><dd data-field="detail-subtotal"></dd></div><div><dt><?php esc_html_e('Discount', 'coffeepos'); ?></dt><dd data-field="detail-discount"></dd></div><div><dt><?php esc_html_e('Refunded', 'coffeepos'); ?></dt><dd data-field="detail-refunded"></dd></div><div class="is-total"><dt><?php esc_html_e('Total', 'coffeepos'); ?></dt><dd data-field="detail-total"></dd></div></dl>
        <p class="coffeepos-inline-error" data-component="detail-error" hidden></p>
        <footer><button type="button" data-action="reprint-order"><?php esc_html_e('Print receipt', 'coffeepos'); ?></button><button type="button" data-action="reorder-order"><?php esc_html_e('Quick reorder', 'coffeepos'); ?></button><button type="button" data-action="refund-order"><?php esc_html_e('Refund', 'coffeepos'); ?></button><button type="button" class="coffeepos-button--danger" data-action="cancel-history-order"><?php esc_html_e('Cancel order', 'coffeepos'); ?></button></footer>
    </dialog>
    <template data-template="history-detail-item"><article class="coffeepos-history-detail-item"><div><strong data-field="name"></strong><small data-field="quick_notes"></small><small data-field="note"></small></div><span data-field="quantity"></span><strong data-field="total"></strong></article></template>

    <dialog class="coffeepos-history-dialog coffeepos-refund-dialog" data-component="refund-dialog" aria-labelledby="coffeepos-refund-title"><form method="dialog" data-component="refund-form"><header><h2 id="coffeepos-refund-title"><?php esc_html_e('Refund order', 'coffeepos'); ?></h2><button type="button" data-action="close-refund" aria-label="<?php esc_attr_e('Close refund', 'coffeepos'); ?>">×</button></header><p><?php esc_html_e('Refunds are recorded by WooCommerce. Return money to the customer using the original offline payment method.', 'coffeepos'); ?></p><label><?php esc_html_e('Refund amount', 'coffeepos'); ?><input name="amount" inputmode="decimal" required></label><small data-field="refund-maximum"></small><label><?php esc_html_e('Reason', 'coffeepos'); ?><textarea name="reason" maxlength="500"></textarea></label><p class="coffeepos-inline-error" data-component="refund-error" hidden></p><footer><button type="button" data-action="close-refund"><?php esc_html_e('Cancel', 'coffeepos'); ?></button><button class="coffeepos-button--danger" type="submit"><?php esc_html_e('Create refund', 'coffeepos'); ?></button></footer></form></dialog>

    <?php require COFFEEPOS_PATH . 'templates/receipt/receipt.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/components/confirm-dialog.php'; ?>
</section>
