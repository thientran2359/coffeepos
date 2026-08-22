<?php
/**
 * CoffeePOS uninstall bootstrap.
 */

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$autoload = __DIR__ . '/vendor/autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
}

if (! class_exists('CoffeePOS\\Core\\Lifecycle')) {
    return;
}

$shouldDeleteData = (bool) get_option('coffeepos_uninstall_delete_data', false);

CoffeePOS\Core\Lifecycle::uninstall($shouldDeleteData);
