<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Settings;

use CoffeePOS\Support\Capabilities;

final class Settings
{
    public const GROUP = 'coffeepos';

    public const OPTION_POS_BASE_SLUG = 'coffeepos_pos_base_slug';

    public const OPTION_POS_PAGE_ID = 'coffeepos_pos_page_id';

    public const OPTION_CUSTOMER_PAGE_ID = 'coffeepos_customer_page_id';

    public const OPTION_UNINSTALL_DELETE_DATA = 'coffeepos_uninstall_delete_data';

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_filter('option_page_capability_' . self::GROUP, [$this, 'groupCapability']);
    }

    public function groupCapability(): string
    {
        return Capabilities::MANAGE_WOOCOMMERCE;
    }

    public function registerSettings(): void
    {
        foreach (self::definitions() as $optionName => $definition) {
            register_setting(self::GROUP, $optionName, [
                'type' => $definition['type'],
                'default' => $definition['default'],
                'show_in_rest' => false,
                'sanitize_callback' => static function ($value) use ($optionName) {
                    return self::sanitizeOption($optionName, $value);
                },
            ]);
        }
    }

    public static function ensureDefaults(): void
    {
        foreach (self::definitions() as $optionName => $definition) {
            if (get_option($optionName, null) !== null) {
                continue;
            }

            add_option($optionName, $definition['default']);
        }
    }

    public static function optionNames(): array
    {
        return array_keys(self::definitions());
    }

    public static function get(string $optionName)
    {
        $definitions = self::definitions();

        if (! isset($definitions[$optionName])) {
            return null;
        }

        return get_option($optionName, $definitions[$optionName]['default']);
    }

    public static function getPosBaseSlug(): string
    {
        $slug = (string) self::get(self::OPTION_POS_BASE_SLUG);

        if ($slug === '') {
            return 'pos';
        }

        return $slug;
    }

    private static function definitions(): array
    {
        return [
            self::OPTION_POS_BASE_SLUG => [
                'type' => 'string',
                'default' => 'pos',
                'capability' => Capabilities::MANAGE_WOOCOMMERCE,
                'sanitize' => 'sanitize_title',
            ],
            self::OPTION_POS_PAGE_ID => [
                'type' => 'integer',
                'default' => 0,
                'capability' => Capabilities::MANAGE_WOOCOMMERCE,
                'sanitize' => 'absint',
            ],
            self::OPTION_CUSTOMER_PAGE_ID => [
                'type' => 'integer',
                'default' => 0,
                'capability' => Capabilities::MANAGE_WOOCOMMERCE,
                'sanitize' => 'absint',
            ],
            self::OPTION_UNINSTALL_DELETE_DATA => [
                'type' => 'boolean',
                'default' => false,
                'capability' => Capabilities::MANAGE_WOOCOMMERCE,
                'sanitize' => null,
            ],
        ];
    }

    private static function sanitizeOption(string $optionName, $value)
    {
        $definitions = self::definitions();

        if (! isset($definitions[$optionName])) {
            return $value;
        }

        $definition = $definitions[$optionName];
        $capability = (string) $definition['capability'];

        if (! current_user_can($capability)) {
            return get_option($optionName, $definition['default']);
        }

        if ($optionName === self::OPTION_UNINSTALL_DELETE_DATA) {
            return (bool) $value;
        }

        $sanitizeCallback = $definition['sanitize'];

        if (is_string($sanitizeCallback) && is_callable($sanitizeCallback)) {
            $value = $sanitizeCallback($value);
        }

        if ($optionName === self::OPTION_POS_BASE_SLUG && $value === '') {
            return $definition['default'];
        }

        return $value;
    }
}
