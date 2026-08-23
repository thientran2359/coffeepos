<?php
/**
 * Plugin Name: CoffeePOS
 * Description: CoffeePOS foundational infrastructure for WooCommerce-powered POS workflows.
 * Version: 0.0.12
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Author: CoffeePOS
 * Text Domain: coffeepos
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('COFFEEPOS_VERSION', '0.0.12');
define('COFFEEPOS_FILE', __FILE__);
define('COFFEEPOS_PATH', plugin_dir_path(__FILE__));
define('COFFEEPOS_URL', plugin_dir_url(__FILE__));
define('COFFEEPOS_BASENAME', plugin_basename(__FILE__));

$coffeeposAutoload = COFFEEPOS_PATH . 'vendor/autoload.php';

if (! file_exists($coffeeposAutoload)) {
    add_action('admin_notices', static function (): void {
        if (! current_user_can('activate_plugins')) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('CoffeePOS requires Composer autoload files. Run "composer install" inside the plugin directory.', 'coffeepos');
        echo '</p></div>';
    });

    return;
}

require_once $coffeeposAutoload;

register_activation_hook(COFFEEPOS_FILE, ['CoffeePOS\\Core\\Lifecycle', 'activate']);
register_deactivation_hook(COFFEEPOS_FILE, ['CoffeePOS\\Core\\Lifecycle', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    (new CoffeePOS\Core\Bootstrap())->run();
});
