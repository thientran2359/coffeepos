<?php

declare(strict_types=1);

namespace CoffeePOS\Core;

final class Environment
{
    private const MINIMUM_PHP_VERSION = '7.4';

    public function isWordPressLoaded(): bool
    {
        return function_exists('add_action');
    }

    public function isPhpVersionSupported(): bool
    {
        return version_compare(PHP_VERSION, self::MINIMUM_PHP_VERSION, '>=');
    }

    public function isWooCommerceAvailable(): bool
    {
        return class_exists('WooCommerce');
    }

    public function isWooCommerceVersionSupported(): bool
    {
        $minimumVersion = $this->minimumWooCommerceVersion();

        if ($minimumVersion === '') {
            return true;
        }

        $currentVersion = $this->wooCommerceVersion();

        if ($currentVersion === '') {
            return false;
        }

        return version_compare($currentVersion, $minimumVersion, '>=');
    }

    public function minimumPhpVersion(): string
    {
        return self::MINIMUM_PHP_VERSION;
    }

    public function minimumWooCommerceVersion(): string
    {
        return '';
    }

    public function wooCommerceVersion(): string
    {
        if (defined('WC_VERSION') && is_string(WC_VERSION)) {
            return WC_VERSION;
        }

        return '';
    }
}
