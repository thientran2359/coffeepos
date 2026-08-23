<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<section class="coffeepos-operations coffeepos-order-queue" data-component="order-queue-screen" data-state="loading">
    <header class="coffeepos-operations__header">
        <div><span class="coffeepos-eyebrow"><?php esc_html_e('Operations', 'coffeepos'); ?></span><h1><?php esc_html_e('Order Queue', 'coffeepos'); ?></h1></div>
        <div class="coffeepos-operations__tools"><span data-component="order-queue-refresh-status" role="status"><?php esc_html_e('Loading orders…', 'coffeepos'); ?></span><button type="button" class="coffeepos-btn" data-action="refresh-order-queue"><?php esc_html_e('Refresh', 'coffeepos'); ?></button></div>
    </header>
    <div class="coffeepos-operations__filters">
        <label><?php esc_html_e('Status', 'coffeepos'); ?><select data-action="filter-order-queue" data-filter="kds_state"><option value="all"><?php esc_html_e('All active', 'coffeepos'); ?></option><option value="new"><?php esc_html_e('New', 'coffeepos'); ?></option><option value="preparing"><?php esc_html_e('Preparing', 'coffeepos'); ?></option><option value="ready"><?php esc_html_e('Ready', 'coffeepos'); ?></option></select></label>
        <label><?php esc_html_e('Service', 'coffeepos'); ?><select data-action="filter-order-queue" data-filter="order_type"><option value="all"><?php esc_html_e('All', 'coffeepos'); ?></option><option value="dine_in"><?php esc_html_e('Dine-in', 'coffeepos'); ?></option><option value="takeaway"><?php esc_html_e('Takeaway', 'coffeepos'); ?></option></select></label>
    </div>
    <div class="coffeepos-operations__notice" data-component="order-queue-loading"><?php esc_html_e('Loading active orders…', 'coffeepos'); ?></div>
    <div class="coffeepos-operations__notice" data-component="order-queue-error" hidden><span data-field="order-queue-error-message"></span> <button type="button" data-action="refresh-order-queue"><?php esc_html_e('Retry', 'coffeepos'); ?></button></div>
    <div class="coffeepos-operations__notice" data-component="order-queue-empty" hidden><?php esc_html_e('No active orders.', 'coffeepos'); ?></div>
    <div class="coffeepos-order-queue-list" data-component="order-queue-list"></div>
    <?php require COFFEEPOS_PATH . 'templates/components/order-queue-templates.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/components/confirm-dialog.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/receipt/receipt.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/components/toast.php'; ?>
</section>
