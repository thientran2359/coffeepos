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
    </div>

    <nav class="coffeepos-cashier-utilities" aria-label="<?php esc_attr_e('Cashier utility actions', 'coffeepos'); ?>">
        <button type="button" class="coffeepos-icon-button" data-action="open-held-carts" disabled title="<?php esc_attr_e('Held carts', 'coffeepos'); ?>" aria-label="<?php esc_attr_e('Held carts', 'coffeepos'); ?>">
            <span aria-hidden="true">H</span>
        </button>
        <button type="button" class="coffeepos-icon-button" data-action="open-customer-display" disabled title="<?php esc_attr_e('Customer display', 'coffeepos'); ?>" aria-label="<?php esc_attr_e('Customer display', 'coffeepos'); ?>">
            <span aria-hidden="true">D</span>
        </button>
        <button type="button" class="coffeepos-icon-button" data-action="open-shift" disabled title="<?php esc_attr_e('Shift', 'coffeepos'); ?>" aria-label="<?php esc_attr_e('Shift', 'coffeepos'); ?>">
            <span aria-hidden="true">S</span>
        </button>
        <button type="button" class="coffeepos-icon-button" data-action="open-settings" disabled title="<?php esc_attr_e('Settings', 'coffeepos'); ?>" aria-label="<?php esc_attr_e('Settings', 'coffeepos'); ?>">
            <span aria-hidden="true">&#9881;</span>
        </button>
    </nav>
</header>
