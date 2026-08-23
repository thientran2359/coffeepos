<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<div class="coffeepos-customer-display" data-component="customer-display" data-state="loading">
    <?php require COFFEEPOS_PATH . 'templates/customer/menu.php'; ?>
    <?php require COFFEEPOS_PATH . 'templates/customer/cart.php'; ?>
</div>
<?php require COFFEEPOS_PATH . 'templates/components/customer-display-templates.php'; ?>
