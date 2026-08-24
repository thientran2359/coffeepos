<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$brand = (string) ($cashierView['brand'] ?? __('CoffeePOS', 'coffeepos'));
$shift = (string) ($cashierView['shift'] ?? __('No shift context', 'coffeepos'));
$cashier = (string) ($cashierView['cashier'] ?? __('Cashier', 'coffeepos'));

?>
<header class="coffeepos-cashier-header" data-component="cashier-header" data-state="no_shift_context">
    <div class="coffeepos-cashier-brand">
        <strong><?php echo esc_html($brand); ?></strong>
        <span data-component="shift-status"><?php echo esc_html($shift); ?></span>
        <span data-component="cashier-identity"><?php echo esc_html($cashier); ?></span>
        <span class="coffeepos-display-status" data-component="customer-display-status" data-state="disconnected" role="status" aria-live="polite">
            <span class="coffeepos-display-status-dot" aria-hidden="true"></span>
            <span data-field="customer-display-status-label"><?php esc_html_e('Customer display: Not connected', 'coffeepos'); ?></span>
        </span>
    </div>

    <nav class="coffeepos-cashier-utilities" aria-label="<?php esc_attr_e('Cashier utility actions', 'coffeepos'); ?>">
        <button type="button" class="coffeepos-icon-button" data-action="open-held-carts" disabled title="<?php esc_attr_e('Held carts', 'coffeepos'); ?>" aria-label="<?php esc_attr_e('Held carts', 'coffeepos'); ?>">
            <span aria-hidden="true">H</span>
        </button>
        <button type="button" class="coffeepos-icon-button" data-action="open-customer-display" disabled title="<?php esc_attr_e('Customer display', 'coffeepos'); ?>" aria-label="<?php esc_attr_e('Customer display', 'coffeepos'); ?>">
            <span aria-hidden="true">D</span>
        </button>
    </nav>
</header>
