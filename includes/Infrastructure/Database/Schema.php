<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Database;

final class Schema
{
    public static function tableNames(string $wpPrefix): array
    {
        return [
            $wpPrefix . 'coffeepos_shifts',
            $wpPrefix . 'coffeepos_suspended_carts',
        ];
    }

    public static function tableDefinitions(string $wpPrefix, string $charsetCollate): array
    {
        $shiftsTable = $wpPrefix . 'coffeepos_shifts';
        $suspendedCartsTable = $wpPrefix . 'coffeepos_suspended_carts';

        return [
            "CREATE TABLE {$shiftsTable} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(20) NOT NULL,
                opened_at DATETIME NOT NULL,
                opening_cash DECIMAL(20,6) NOT NULL DEFAULT 0,
                opening_note TEXT NULL,
                closed_at DATETIME NULL,
                actual_cash DECIMAL(20,6) NULL,
                closing_note TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY status (status),
                KEY opened_at (opened_at)
            ) {$charsetCollate};",
            "CREATE TABLE {$suspendedCartsTable} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                user_id BIGINT UNSIGNED NOT NULL,
                label VARCHAR(191) NOT NULL,
                cart_payload LONGTEXT NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                PRIMARY KEY  (id),
                KEY user_id (user_id),
                KEY created_at (created_at)
            ) {$charsetCollate};",
        ];
    }
}
