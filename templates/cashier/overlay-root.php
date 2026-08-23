<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

?>
<div class="coffeepos-overlay-root" data-component="overlay-root" aria-live="polite">
    <?php require COFFEEPOS_PATH . 'templates/components/product-modal.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/components/context-dialogs.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/components/coupon-selector.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/components/checkout-modal.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/components/payment-success.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/receipt/receipt.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/components/modal.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/components/confirm-dialog.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/components/drawer.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/components/toast.php'; ?>
</div>
