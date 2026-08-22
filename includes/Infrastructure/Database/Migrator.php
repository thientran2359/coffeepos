<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Database;

use CoffeePOS\Infrastructure\Logging\Logger;

final class Migrator
{
    public const OPTION_DB_VERSION = 'coffeepos_db_version';

    public const SCHEMA_VERSION = '0.0.1';

    public function maybeMigrate(): void
    {
        $currentVersion = (string) get_option(self::OPTION_DB_VERSION, '0.0.0');

        if (version_compare($currentVersion, self::SCHEMA_VERSION, '>=')) {
            return;
        }

        $this->migrate();
    }

    public function migrate(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach (Schema::tableDefinitions($wpdb->prefix, $wpdb->get_charset_collate()) as $definition) {
            dbDelta($definition);
        }

        update_option(self::OPTION_DB_VERSION, self::SCHEMA_VERSION);

        Logger::info('CoffeePOS migrations completed.', [
            'schema_version' => self::SCHEMA_VERSION,
        ]);
    }

    public function diagnostics(): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'installed_version' => (string) get_option(self::OPTION_DB_VERSION, '0.0.0'),
        ];
    }
}
