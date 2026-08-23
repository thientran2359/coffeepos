<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<section class="coffeepos-operations coffeepos-kds" data-component="kds-screen" data-state="loading">
    <header class="coffeepos-operations__header">
        <div><span class="coffeepos-eyebrow"><?php esc_html_e('Operations', 'coffeepos'); ?></span><h1><?php esc_html_e('Kitchen Display', 'coffeepos'); ?></h1></div>
        <div class="coffeepos-operations__tools">
            <span data-component="kds-refresh-status" role="status"><?php esc_html_e('Loading orders…', 'coffeepos'); ?></span>
            <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="toggle-kds-sound" aria-pressed="false"><?php esc_html_e('Sound off', 'coffeepos'); ?></button>
            <button type="button" class="coffeepos-btn" data-action="refresh-kds"><?php esc_html_e('Refresh', 'coffeepos'); ?></button>
        </div>
    </header>
    <nav class="coffeepos-operations__filters" aria-label="<?php esc_attr_e('KDS status filters', 'coffeepos'); ?>">
        <button type="button" data-action="filter-kds-state" data-state-filter="all" aria-pressed="true"><?php esc_html_e('All active', 'coffeepos'); ?></button>
        <button type="button" data-action="filter-kds-state" data-state-filter="new" aria-pressed="false"><?php esc_html_e('New', 'coffeepos'); ?></button>
        <button type="button" data-action="filter-kds-state" data-state-filter="preparing" aria-pressed="false"><?php esc_html_e('Preparing', 'coffeepos'); ?></button>
        <button type="button" data-action="filter-kds-state" data-state-filter="ready" aria-pressed="false"><?php esc_html_e('Ready', 'coffeepos'); ?></button>
    </nav>
    <div class="coffeepos-operations__notice" data-component="kds-loading"><?php esc_html_e('Loading active orders…', 'coffeepos'); ?></div>
    <div class="coffeepos-operations__notice" data-component="kds-error" hidden><span data-field="kds-error-message"></span> <button type="button" data-action="refresh-kds"><?php esc_html_e('Retry', 'coffeepos'); ?></button></div>
    <div class="coffeepos-operations__notice" data-component="kds-empty" hidden><?php esc_html_e('No active orders.', 'coffeepos'); ?></div>
    <div class="coffeepos-kds-grid" data-component="kds-order-grid"></div>
    <?php require COFFEEPOS_PATH . 'templates/components/kds-templates.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/components/toast.php'; ?>
</section>
