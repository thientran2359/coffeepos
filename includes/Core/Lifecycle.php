<?php

declare(strict_types=1);

namespace CoffeePOS\Core;

use CoffeePOS\Infrastructure\Database\Migrator;
use CoffeePOS\Infrastructure\Database\Schema;
use CoffeePOS\Infrastructure\Logging\Logger;
use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\POS\Router;
use CoffeePOS\Support\Capabilities;

final class Lifecycle
{
    public static function activate(): void
    {
        $environment = new Environment();

        if (! $environment->isPhpVersionSupported()) {
            deactivate_plugins(COFFEEPOS_BASENAME);

            wp_die(
                sprintf(
                    /* translators: %s: minimum PHP version. */
                    esc_html__('CoffeePOS requires PHP %s or higher.', 'coffeepos'),
                    esc_html($environment->minimumPhpVersion())
                )
            );
        }

        Settings::ensureDefaults();
        Capabilities::register();

        $migrator = new Migrator();
        $migrator->migrate();

        Router::registerRewriteRules(Settings::getPosBaseSlug());
        flush_rewrite_rules();
        update_option('coffeepos_rewrite_version', Router::rewriteVersion());

        update_option('coffeepos_installed_version', COFFEEPOS_VERSION);

        Logger::info('CoffeePOS activated.', [
            'version' => COFFEEPOS_VERSION,
            'woocommerce_available' => $environment->isWooCommerceAvailable(),
        ]);
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();

        Logger::info('CoffeePOS deactivated.');
    }

    public static function uninstall(bool $deleteData): void
    {
        if (! $deleteData) {
            return;
        }

        global $wpdb;

        foreach (Schema::tableNames($wpdb->prefix) as $tableName) {
            $sanitizedTable = esc_sql($tableName);
            $wpdb->query("DROP TABLE IF EXISTS `{$sanitizedTable}`");
        }

        foreach (Settings::optionNames() as $optionName) {
            delete_option($optionName);
        }

        delete_option(Migrator::OPTION_DB_VERSION);
        delete_metadata('user', 0, '_coffeepos_tier_override', '', true);
        delete_option('coffeepos_installed_version');
        delete_option('coffeepos_rewrite_version');
    }
}
