<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$cashierView = [
    'brand' => __('CoffeePOS', 'coffeepos'),
    'shift' => __('No shift context', 'coffeepos'),
    'cashier' => __('Cashier', 'coffeepos'),
];

?>
<section class="coffeepos-cashier" data-screen="cashier">
    <?php require COFFEEPOS_PATH . 'templates/cashier/header.php'; ?>

    <div class="coffeepos-cashier-layout">
        <section class="coffeepos-menu-panel" data-component="menu-panel" data-state="normal">
            <?php require COFFEEPOS_PATH . 'templates/cashier/menu-panel.php'; ?>
        </section>

        <aside class="coffeepos-cart-panel" data-component="cart-panel" data-state="empty">
            <?php require COFFEEPOS_PATH . 'templates/cashier/cart-panel.php'; ?>
        </aside>
    </div>

    <?php require COFFEEPOS_PATH . 'templates/cashier/overlay-root.php'; ?>
</section>
