<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$cashierView = [
    'brand' => __('CoffeePOS', 'coffeepos'),
    'shift' => __('No shift open', 'coffeepos'),
    'cashier' => is_user_logged_in() ? (string) wp_get_current_user()->display_name : __('Cashier', 'coffeepos'),
];

?>
<section class="coffeepos-cashier" data-component="cashier-screen">
    <?php require COFFEEPOS_PATH . 'templates/cashier/header.php'; ?>

    <div class="coffeepos-cashier-layout" data-component="cashier-layout">
        <section class="coffeepos-menu-panel" data-component="menu-panel" data-state="normal">
            <?php require COFFEEPOS_PATH . 'templates/cashier/menu-panel.php'; ?>
        </section>

        <aside class="coffeepos-cart-panel" data-component="cart-panel" data-state="loading">
            <?php require COFFEEPOS_PATH . 'templates/cashier/cart-panel.php'; ?>
        </aside>
    </div>

    <?php require COFFEEPOS_PATH . 'templates/cashier/overlay-root.php'; ?>
</section>
