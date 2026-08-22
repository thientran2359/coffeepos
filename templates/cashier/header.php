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
        <button type="button" class="coffeepos-btn" data-action="open-held-carts" disabled>
            <?php esc_html_e('Held carts', 'coffeepos'); ?>
        </button>
        <button type="button" class="coffeepos-btn" data-action="open-customer-display" disabled>
            <?php esc_html_e('Customer display', 'coffeepos'); ?>
        </button>
        <button type="button" class="coffeepos-btn" data-action="open-shift" disabled>
            <?php esc_html_e('Shift', 'coffeepos'); ?>
        </button>
        <button type="button" class="coffeepos-btn" data-action="open-settings" disabled>
            <?php esc_html_e('Settings', 'coffeepos'); ?>
        </button>
    </nav>
</header>
