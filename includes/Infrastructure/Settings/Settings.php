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

    public const OPTION_MODIFIER_GROUPS = 'coffeepos_modifier_groups';

    public const OPTION_QUICK_NOTES = 'coffeepos_quick_notes';

    public const OPTION_SERVICE_TABLES = 'coffeepos_service_tables';
    public const OPTION_VIETQR_BANK_ID = 'coffeepos_vietqr_bank_id';
    public const OPTION_VIETQR_ACCOUNT_NUMBER = 'coffeepos_vietqr_account_number';
    public const OPTION_VIETQR_ACCOUNT_NAME = 'coffeepos_vietqr_account_name';
    public const OPTION_VIETQR_TEMPLATE = 'coffeepos_vietqr_template';
    public const OPTION_KDS_POLL_INTERVAL = 'coffeepos_kds_poll_interval_ms';
    public const OPTION_ORDER_QUEUE_POLL_INTERVAL = 'coffeepos_order_queue_poll_interval_ms';
    public const OPTION_RECEIPT_PRINT_ORDER_NOTE = 'coffeepos_receipt_print_order_note';

    public function register(): void
    {
        add_action('admin_init', [$this, 'registerSettings']);
        add_filter('option_page_capability_' . self::GROUP, [$this, 'groupCapability']);
    }

    public function groupCapability(): string
    {
        return Capabilities::MANAGE_SETTINGS;
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

        $quickNotes = get_option(self::OPTION_QUICK_NOTES, []);
        $ids = array_values(array_filter(array_map(static function ($note): string {
            return is_array($note) ? sanitize_key((string) ($note['id'] ?? '')) : '';
        }, is_array($quickNotes) ? $quickNotes : [])));

        if ($ids === [] || $ids === ['less_ice', 'no_ice', 'less_sweet', 'no_sugar', 'extra_milk', 'takeaway']) {
            update_option(self::OPTION_QUICK_NOTES, self::definitions()[self::OPTION_QUICK_NOTES]['default']);
        }
    }

    public static function optionNames(): array
    {
        return array_keys(self::definitions());
    }

    public static function shouldPrintOrderNote(): bool
    {
        return (bool) self::get(self::OPTION_RECEIPT_PRINT_ORDER_NOTE);
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

    public static function getKdsPollInterval(): int
    {
        return self::boundedPollInterval(self::get(self::OPTION_KDS_POLL_INTERVAL));
    }

    public static function getOrderQueuePollInterval(): int
    {
        return self::boundedPollInterval(self::get(self::OPTION_ORDER_QUEUE_POLL_INTERVAL));
    }

    public static function update(string $optionName, $value): void
    {
        $definitions = self::definitions();

        if (! isset($definitions[$optionName]) || ! current_user_can(Capabilities::MANAGE_SETTINGS)) {
            return;
        }

        update_option($optionName, self::sanitizeOption($optionName, $value));
    }

    public static function serviceTablesToText(): string
    {
        $tables = array_values(array_filter((array) self::get(self::OPTION_SERVICE_TABLES), static function ($table): bool {
            return is_array($table) && trim((string) ($table['label'] ?? '')) !== '';
        }));

        usort($tables, static function (array $left, array $right): int {
            $order = ((int) ($left['sort_order'] ?? 0)) <=> ((int) ($right['sort_order'] ?? 0));
            return $order !== 0 ? $order : (((int) ($left['id'] ?? 0)) <=> ((int) ($right['id'] ?? 0)));
        });

        return implode("\n", array_map(static function (array $table): string {
            return sanitize_text_field((string) ($table['label'] ?? ''));
        }, $tables));
    }

    public static function serviceTablesFromText(string $value): array
    {
        $existing = (array) self::get(self::OPTION_SERVICE_TABLES);
        $idsByLabel = [];
        $nextId = 1;

        foreach ($existing as $table) {
            if (! is_array($table)) {
                continue;
            }

            $id = absint($table['id'] ?? 0);
            $label = sanitize_text_field((string) ($table['label'] ?? ''));
            $key = self::normalizedLabel($label);

            if ($id > 0) {
                $nextId = max($nextId, $id + 1);
            }

            if ($id > 0 && $key !== '' && ! isset($idsByLabel[$key])) {
                $idsByLabel[$key] = $id;
            }
        }

        $lines = preg_split('/\R/u', $value) ?: [];
        $tables = [];
        $seenLabels = [];

        foreach (array_slice($lines, 0, 200) as $line) {
            $label = sanitize_text_field((string) $line);
            $label = function_exists('mb_substr') ? mb_substr($label, 0, 100) : substr($label, 0, 100);
            $key = self::normalizedLabel($label);

            if ($label === '' || $key === '' || isset($seenLabels[$key])) {
                continue;
            }

            $seenLabels[$key] = true;
            $id = $idsByLabel[$key] ?? $nextId++;
            $tables[] = [
                'id' => $id,
                'label' => $label,
                'enabled' => true,
                'sort_order' => count($tables) * 10 + 10,
            ];
        }

        return $tables;
    }

    private static function definitions(): array
    {
        return [
            self::OPTION_POS_BASE_SLUG => [
                'type' => 'string',
                'default' => 'pos',
                'capability' => Capabilities::MANAGE_SETTINGS,
                'sanitize' => 'sanitize_title',
            ],
            self::OPTION_POS_PAGE_ID => [
                'type' => 'integer',
                'default' => 0,
                'capability' => Capabilities::MANAGE_SETTINGS,
                'sanitize' => 'absint',
            ],
            self::OPTION_CUSTOMER_PAGE_ID => [
                'type' => 'integer',
                'default' => 0,
                'capability' => Capabilities::MANAGE_SETTINGS,
                'sanitize' => 'absint',
            ],
            self::OPTION_UNINSTALL_DELETE_DATA => [
                'type' => 'boolean',
                'default' => false,
                'capability' => Capabilities::MANAGE_SETTINGS,
                'sanitize' => null,
            ],
            self::OPTION_MODIFIER_GROUPS => [
                'type' => 'array',
                'default' => [],
                'capability' => Capabilities::MANAGE_SETTINGS,
                'sanitize' => null,
            ],
            self::OPTION_QUICK_NOTES => [
                'type' => 'array',
                'default' => [
                    ['id' => 'less_sugar', 'label' => 'Ít đường', 'enabled' => true, 'sort_order' => 10],
                    ['id' => 'extra_sugar', 'label' => 'Nhiều đường', 'enabled' => true, 'sort_order' => 20],
                    ['id' => 'less_milk', 'label' => 'Ít sữa', 'enabled' => true, 'sort_order' => 30],
                    ['id' => 'less_ice', 'label' => 'Ít đá', 'enabled' => true, 'sort_order' => 40],
                ],
                'capability' => Capabilities::MANAGE_SETTINGS,
                'sanitize' => null,
            ],
            self::OPTION_SERVICE_TABLES => [
                'type' => 'array',
                'default' => [
                    ['id' => 1, 'label' => 'Table 01', 'enabled' => true, 'sort_order' => 10],
                    ['id' => 2, 'label' => 'Table 02', 'enabled' => true, 'sort_order' => 20],
                    ['id' => 3, 'label' => 'Table 03', 'enabled' => true, 'sort_order' => 30],
                    ['id' => 4, 'label' => 'Table 04', 'enabled' => true, 'sort_order' => 40],
                    ['id' => 5, 'label' => 'Table 05', 'enabled' => true, 'sort_order' => 50],
                ],
                'capability' => Capabilities::MANAGE_SETTINGS,
                'sanitize' => null,
            ],
            self::OPTION_VIETQR_BANK_ID => [
                'type' => 'string', 'default' => '',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_key',
            ],
            self::OPTION_VIETQR_ACCOUNT_NUMBER => [
                'type' => 'string', 'default' => '',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_text_field',
            ],
            self::OPTION_VIETQR_ACCOUNT_NAME => [
                'type' => 'string', 'default' => '',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_text_field',
            ],
            self::OPTION_VIETQR_TEMPLATE => [
                'type' => 'string', 'default' => 'compact2',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_key',
            ],
            self::OPTION_KDS_POLL_INTERVAL => [
                'type' => 'integer', 'default' => 5000,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'absint',
            ],
            self::OPTION_ORDER_QUEUE_POLL_INTERVAL => [
                'type' => 'integer', 'default' => 5000,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'absint',
            ],
            self::OPTION_RECEIPT_PRINT_ORDER_NOTE => [
                'type' => 'boolean', 'default' => false,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
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

        if (in_array($optionName, [self::OPTION_UNINSTALL_DELETE_DATA, self::OPTION_RECEIPT_PRINT_ORDER_NOTE], true)) {
            return (bool) $value;
        }

        if ($optionName === self::OPTION_MODIFIER_GROUPS) {
            return self::sanitizeModifierGroups(is_array($value) ? $value : []);
        }

        if ($optionName === self::OPTION_QUICK_NOTES) {
            return self::sanitizeQuickNotes(is_array($value) ? $value : []);
        }

        if ($optionName === self::OPTION_SERVICE_TABLES) {
            return self::sanitizeServiceTables(is_array($value) ? $value : []);
        }

        $sanitizeCallback = $definition['sanitize'];

        if (is_string($sanitizeCallback) && is_callable($sanitizeCallback)) {
            $value = $sanitizeCallback($value);
        }

        if ($optionName === self::OPTION_POS_BASE_SLUG && $value === '') {
            return $definition['default'];
        }

        if (in_array($optionName, [self::OPTION_KDS_POLL_INTERVAL, self::OPTION_ORDER_QUEUE_POLL_INTERVAL], true)) {
            return self::boundedPollInterval($value);
        }

        return $value;
    }

    private static function boundedPollInterval($value): int
    {
        return max(3000, min(60000, (int) $value));
    }

    private static function normalizedLabel(string $label): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower(trim($label)) : strtolower(trim($label));
    }

    private static function sanitizeModifierGroups(array $groups): array
    {
        $sanitized = [];

        foreach ($groups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $id = sanitize_key((string) ($group['id'] ?? ''));
            $label = sanitize_text_field((string) ($group['label'] ?? ''));

            if ($id === '' || $label === '') {
                continue;
            }

            $selection = (string) ($group['selection'] ?? 'single');

            if (! in_array($selection, ['single', 'multiple'], true)) {
                $selection = 'single';
            }

            $options = [];

            foreach ((array) ($group['options'] ?? []) as $option) {
                if (! is_array($option)) {
                    continue;
                }

                $optionId = sanitize_key((string) ($option['id'] ?? ''));
                $optionLabel = sanitize_text_field((string) ($option['label'] ?? ''));

                if ($optionId !== '' && $optionLabel !== '') {
                    $options[] = ['id' => $optionId, 'label' => $optionLabel];
                }
            }

            if ($options === []) {
                continue;
            }

            $required = ! empty($group['required']);
            $minimum = max(0, (int) ($group['minimum'] ?? ($required ? 1 : 0)));
            $maximumDefault = $selection === 'single' ? 1 : count($options);
            $maximum = max($minimum, min(count($options), (int) ($group['maximum'] ?? $maximumDefault)));

            $sanitized[] = [
                'id' => $id,
                'label' => $label,
                'selection' => $selection,
                'required' => $required,
                'minimum' => $minimum,
                'maximum' => $maximum,
                'enabled' => ! array_key_exists('enabled', $group) || ! empty($group['enabled']),
                'sort_order' => (int) ($group['sort_order'] ?? 0),
                'product_ids' => array_values(array_filter(array_map('absint', (array) ($group['product_ids'] ?? [])))),
                'category_ids' => array_values(array_filter(array_map('absint', (array) ($group['category_ids'] ?? [])))),
                'options' => $options,
            ];
        }

        return $sanitized;
    }

    private static function sanitizeQuickNotes(array $notes): array
    {
        $sanitized = [];

        foreach ($notes as $note) {
            if (! is_array($note)) {
                continue;
            }

            $id = sanitize_key((string) ($note['id'] ?? ''));
            $label = sanitize_text_field((string) ($note['label'] ?? ''));

            if ($id === '' || $label === '') {
                continue;
            }

            $sanitized[] = [
                'id' => $id,
                'label' => $label,
                'enabled' => ! array_key_exists('enabled', $note) || ! empty($note['enabled']),
                'sort_order' => (int) ($note['sort_order'] ?? 0),
                'product_ids' => self::sanitizeIdList($note['product_ids'] ?? []),
                'category_ids' => self::sanitizeIdList($note['category_ids'] ?? []),
            ];
        }

        return $sanitized;
    }

    private static function sanitizeIdList($value): array
    {
        $values = is_string($value) ? preg_split('/[\s,]+/', $value) : (array) $value;

        return array_values(array_unique(array_filter(array_map('absint', $values ?: []))));
    }

    private static function sanitizeServiceTables(array $tables): array
    {
        $sanitized = [];
        $seen = [];

        foreach ($tables as $table) {
            if (! is_array($table)) {
                continue;
            }

            $id = absint($table['id'] ?? 0);
            $label = sanitize_text_field((string) ($table['label'] ?? ''));

            if ($id <= 0 || $label === '' || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;
            $sanitized[] = [
                'id' => $id,
                'label' => $label,
                'enabled' => ! array_key_exists('enabled', $table) || ! empty($table['enabled']),
                'sort_order' => (int) ($table['sort_order'] ?? 0),
            ];
        }

        return $sanitized;
    }
}
