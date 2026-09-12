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

    public const OPTION_MEMBER_ACCOUNT_PAGE_ID = 'coffeepos_member_account_page_id';

    public const OPTION_UNINSTALL_DELETE_DATA = 'coffeepos_uninstall_delete_data';

    public const OPTION_MODIFIER_GROUPS = 'coffeepos_modifier_groups';

    public const OPTION_QUICK_NOTES = 'coffeepos_quick_notes';

    public const OPTION_SERVICE_TABLES = 'coffeepos_service_tables';
    public const OPTION_VIETQR_BANK_ID = 'coffeepos_vietqr_bank_id';
    public const OPTION_VIETQR_ACCOUNT_NUMBER = 'coffeepos_vietqr_account_number';
    public const OPTION_VIETQR_ACCOUNT_NAME = 'coffeepos_vietqr_account_name';
    public const OPTION_VIETQR_TEMPLATE = 'coffeepos_vietqr_template';
    public const OPTION_KDS_POLL_INTERVAL = 'coffeepos_kds_poll_interval_ms';
    public const OPTION_KDS_ENABLED = 'coffeepos_kds_enabled';
    public const OPTION_SHIFTS_ENABLED = 'coffeepos_shifts_enabled';
    public const OPTION_ORDER_QUEUE_POLL_INTERVAL = 'coffeepos_order_queue_poll_interval_ms';
    public const OPTION_RECEIPT_PRINT_ORDER_NOTE = 'coffeepos_receipt_print_order_note';
    public const OPTION_BRAND_COLOR = 'coffeepos_brand_color';
    public const OPTION_FONT_FAMILY = 'coffeepos_font_family';
    public const OPTION_NAV_DEFAULT_COLLAPSED = 'coffeepos_nav_default_collapsed';
    public const OPTION_INTERFACE_DENSITY = 'coffeepos_interface_density';
    public const OPTION_SHOW_PRODUCT_IMAGES = 'coffeepos_show_product_images';
    public const OPTION_CUSTOM_CSS = 'coffeepos_custom_css';
    public const OPTION_STORE_NAME = 'coffeepos_store_name';
    public const OPTION_BRANCH_NAME = 'coffeepos_branch_name';
    public const OPTION_LOGO_ID = 'coffeepos_logo_id';
    public const OPTION_STORE_ADDRESS = 'coffeepos_store_address';
    public const OPTION_STORE_PHONE = 'coffeepos_store_phone';
    public const OPTION_TIMEZONE = 'coffeepos_timezone';
    public const OPTION_DATE_FORMAT = 'coffeepos_date_format';
    public const OPTION_TIME_FORMAT = 'coffeepos_time_format';
    public const OPTION_DEFAULT_ORDER_TYPE = 'coffeepos_default_order_type';
    public const OPTION_REQUIRE_DINE_IN_TABLE = 'coffeepos_require_dine_in_table';
    public const OPTION_REQUIRE_OPEN_SHIFT = 'coffeepos_require_open_shift';
    public const OPTION_CASH_ENABLED = 'coffeepos_cash_enabled';
    public const OPTION_BANK_TRANSFER_ENABLED = 'coffeepos_bank_transfer_enabled';
    public const OPTION_VIETQR_REFERENCE_PREFIX = 'coffeepos_vietqr_reference_prefix';
    public const OPTION_RECEIPT_PAPER_WIDTH = 'coffeepos_receipt_paper_width';
    public const OPTION_RECEIPT_AUTO_PRINT = 'coffeepos_receipt_auto_print';
    public const OPTION_RECEIPT_FOOTER = 'coffeepos_receipt_footer';
    public const OPTION_MEMBERSHIP_ENABLED = 'coffeepos_membership_enabled';
    public const OPTION_MEMBERSHIP_TIERS = 'coffeepos_membership_tiers';
    public const OPTION_MEMBER_CREATE_ENABLED = 'coffeepos_member_create_enabled';
    public const OPTION_MEMBER_REQUIRED_FIELDS = 'coffeepos_member_required_fields';
    public const OPTION_KDS_SOUND_ENABLED = 'coffeepos_kds_sound_enabled';

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

        $membershipTiers = get_option(self::OPTION_MEMBERSHIP_TIERS, []);
        if (is_array($membershipTiers) && $membershipTiers === []) {
            update_option(self::OPTION_MEMBERSHIP_TIERS, MembershipTierConfiguration::defaultTiers());
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

    public static function getBrandColor(): string
    {
        $color = strtolower((string) self::get(self::OPTION_BRAND_COLOR));

        return preg_match('/^#[0-9a-f]{6}$/', $color) === 1 ? $color : '#12715b';
    }

    public static function getBrandDarkColor(): string
    {
        $color = ltrim(self::getBrandColor(), '#');
        $channels = [];

        for ($offset = 0; $offset < 6; $offset += 2) {
            $channels[] = max(0, min(255, (int) round(hexdec(substr($color, $offset, 2)) * 0.72)));
        }

        return sprintf('#%02x%02x%02x', $channels[0], $channels[1], $channels[2]);
    }

    public static function fontChoices(): array
    {
        return [
            'be-vietnam-pro' => ['label' => 'Be Vietnam Pro (' . __('Bundled', 'coffeepos') . ')', 'family' => 'Be Vietnam Pro', 'google' => ''],
            'google-roboto' => ['label' => 'Roboto — Google Fonts', 'family' => 'Roboto', 'google' => 'Roboto'],
            'google-open-sans' => ['label' => 'Open Sans — Google Fonts', 'family' => 'Open Sans', 'google' => 'Open+Sans'],
            'google-noto-sans' => ['label' => 'Noto Sans — Google Fonts', 'family' => 'Noto Sans', 'google' => 'Noto+Sans'],
            'google-montserrat' => ['label' => 'Montserrat — Google Fonts', 'family' => 'Montserrat', 'google' => 'Montserrat'],
            'google-inter' => ['label' => 'Inter — Google Fonts', 'family' => 'Inter', 'google' => 'Inter'],
            'google-nunito-sans' => ['label' => 'Nunito Sans — Google Fonts', 'family' => 'Nunito Sans', 'google' => 'Nunito+Sans'],
        ];
    }

    public static function getFontFamilyCss(): string
    {
        $choices = self::fontChoices();
        $choice = $choices[(string) self::get(self::OPTION_FONT_FAMILY)] ?? $choices['be-vietnam-pro'];

        return sprintf('"%s", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif', $choice['family']);
    }

    public static function getGoogleFontStylesheetUrl(): string
    {
        $choices = self::fontChoices();
        $choice = $choices[(string) self::get(self::OPTION_FONT_FAMILY)] ?? $choices['be-vietnam-pro'];

        return $choice['google'] === ''
            ? ''
            : 'https://fonts.googleapis.com/css2?family=' . $choice['google'] . ':wght@400;500;600;700;800&display=swap';
    }

    public static function isStaffNavCollapsed(): bool
    {
        return (bool) self::get(self::OPTION_NAV_DEFAULT_COLLAPSED);
    }

    public static function getInterfaceDensity(): string
    {
        $density = (string) self::get(self::OPTION_INTERFACE_DENSITY);

        return in_array($density, ['compact', 'normal'], true) ? $density : 'normal';
    }

    public static function shouldShowProductImages(): bool
    {
        return (bool) self::get(self::OPTION_SHOW_PRODUCT_IMAGES);
    }

    public static function getCustomCss(): string
    {
        return (string) self::get(self::OPTION_CUSTOM_CSS);
    }

    public static function getStoreName(): string
    {
        $name = trim((string) self::get(self::OPTION_STORE_NAME));

        return $name !== '' ? $name : 'CoffeePOS';
    }

    public static function getLogoUrl(): string
    {
        $logoId = (int) self::get(self::OPTION_LOGO_ID);
        $url = $logoId > 0 && function_exists('wp_get_attachment_image_url')
            ? wp_get_attachment_image_url($logoId, 'medium')
            : false;

        return is_string($url) ? $url : '';
    }

    public static function getTimezone(): \DateTimeZone
    {
        try {
            return new \DateTimeZone((string) self::get(self::OPTION_TIMEZONE));
        } catch (\Throwable $throwable) {
            return new \DateTimeZone('Asia/Ho_Chi_Minh');
        }
    }

    public static function formatTimestamp(int $timestamp): string
    {
        $format = (string) self::get(self::OPTION_DATE_FORMAT) . ' ' . (string) self::get(self::OPTION_TIME_FORMAT);
        $date = new \DateTimeImmutable('@' . $timestamp);

        return $date->setTimezone(self::getTimezone())->format(trim($format));
    }

    public static function formatTime(int $timestamp): string
    {
        $date = new \DateTimeImmutable('@' . $timestamp);

        return $date->setTimezone(self::getTimezone())->format((string) self::get(self::OPTION_TIME_FORMAT));
    }

    public static function enabledPaymentMethods(): array
    {
        $methods = [];
        if ((bool) self::get(self::OPTION_CASH_ENABLED)) {
            $methods[] = 'cash';
        }
        if ((bool) self::get(self::OPTION_BANK_TRANSFER_ENABLED)) {
            $methods[] = 'bank_transfer';
        }

        return $methods !== [] ? $methods : ['cash'];
    }

    public static function memberRequiredFields(): array
    {
        $fields = array_values(array_intersect(['phone', 'name', 'email'], (array) self::get(self::OPTION_MEMBER_REQUIRED_FIELDS)));

        return array_values(array_unique(array_merge(['phone'], $fields)));
    }

    public static function exportValues(): array
    {
        $values = [];
        foreach (self::importableOptionNames() as $optionName) {
            $values[$optionName] = self::get($optionName);
        }

        return ['schema_version' => 1, 'settings' => $values];
    }

    public static function importableOptionNames(): array
    {
        return array_values(array_diff(self::optionNames(), [
            self::OPTION_POS_PAGE_ID,
            self::OPTION_CUSTOMER_PAGE_ID,
            self::OPTION_MEMBER_ACCOUNT_PAGE_ID,
        ]));
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
            self::OPTION_STORE_NAME => [
                'type' => 'string', 'default' => 'CoffeePOS',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_text_field',
            ],
            self::OPTION_BRANCH_NAME => [
                'type' => 'string', 'default' => '',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_text_field',
            ],
            self::OPTION_LOGO_ID => [
                'type' => 'integer', 'default' => 0,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'absint',
            ],
            self::OPTION_STORE_ADDRESS => [
                'type' => 'string', 'default' => '',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_textarea_field',
            ],
            self::OPTION_STORE_PHONE => [
                'type' => 'string', 'default' => '',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_text_field',
            ],
            self::OPTION_TIMEZONE => [
                'type' => 'string', 'default' => 'Asia/Ho_Chi_Minh',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_text_field',
            ],
            self::OPTION_DATE_FORMAT => [
                'type' => 'string', 'default' => 'd/m/Y',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_text_field',
            ],
            self::OPTION_TIME_FORMAT => [
                'type' => 'string', 'default' => 'H:i',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_text_field',
            ],
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
            self::OPTION_MEMBER_ACCOUNT_PAGE_ID => [
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
                    ['id' => 'less_sugar', 'label' => __('Less sugar', 'coffeepos'), 'enabled' => true, 'sort_order' => 10],
                    ['id' => 'extra_sugar', 'label' => __('Extra sugar', 'coffeepos'), 'enabled' => true, 'sort_order' => 20],
                    ['id' => 'less_milk', 'label' => __('Less milk', 'coffeepos'), 'enabled' => true, 'sort_order' => 30],
                    ['id' => 'less_ice', 'label' => __('Less ice', 'coffeepos'), 'enabled' => true, 'sort_order' => 40],
                ],
                'capability' => Capabilities::MANAGE_SETTINGS,
                'sanitize' => null,
            ],
            self::OPTION_SERVICE_TABLES => [
                'type' => 'array',
                'default' => [
                    ['id' => 1, 'label' => __('Table 01', 'coffeepos'), 'enabled' => true, 'sort_order' => 10],
                    ['id' => 2, 'label' => __('Table 02', 'coffeepos'), 'enabled' => true, 'sort_order' => 20],
                    ['id' => 3, 'label' => __('Table 03', 'coffeepos'), 'enabled' => true, 'sort_order' => 30],
                    ['id' => 4, 'label' => __('Table 04', 'coffeepos'), 'enabled' => true, 'sort_order' => 40],
                    ['id' => 5, 'label' => __('Table 05', 'coffeepos'), 'enabled' => true, 'sort_order' => 50],
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
            self::OPTION_KDS_ENABLED => [
                'type' => 'boolean', 'default' => true,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_SHIFTS_ENABLED => [
                'type' => 'boolean', 'default' => true,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_ORDER_QUEUE_POLL_INTERVAL => [
                'type' => 'integer', 'default' => 5000,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'absint',
            ],
            self::OPTION_RECEIPT_PRINT_ORDER_NOTE => [
                'type' => 'boolean', 'default' => false,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_BRAND_COLOR => [
                'type' => 'string', 'default' => '#12715b',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_FONT_FAMILY => [
                'type' => 'string', 'default' => 'be-vietnam-pro',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_NAV_DEFAULT_COLLAPSED => [
                'type' => 'boolean', 'default' => true,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_INTERFACE_DENSITY => [
                'type' => 'string', 'default' => 'normal',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_SHOW_PRODUCT_IMAGES => [
                'type' => 'boolean', 'default' => true,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_CUSTOM_CSS => [
                'type' => 'string', 'default' => '',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_DEFAULT_ORDER_TYPE => [
                'type' => 'string', 'default' => 'takeaway',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_key',
            ],
            self::OPTION_REQUIRE_DINE_IN_TABLE => [
                'type' => 'boolean', 'default' => true,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_REQUIRE_OPEN_SHIFT => [
                'type' => 'boolean', 'default' => true,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_CASH_ENABLED => [
                'type' => 'boolean', 'default' => true,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_BANK_TRANSFER_ENABLED => [
                'type' => 'boolean', 'default' => true,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_VIETQR_REFERENCE_PREFIX => [
                'type' => 'string', 'default' => 'POS',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_RECEIPT_PAPER_WIDTH => [
                'type' => 'string', 'default' => '80',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_key',
            ],
            self::OPTION_RECEIPT_AUTO_PRINT => [
                'type' => 'boolean', 'default' => false,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_RECEIPT_FOOTER => [
                'type' => 'string', 'default' => 'Thank you!',
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => 'sanitize_textarea_field',
            ],
            self::OPTION_MEMBERSHIP_ENABLED => [
                'type' => 'boolean', 'default' => true,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_MEMBERSHIP_TIERS => [
                'type' => 'array', 'default' => MembershipTierConfiguration::defaultTiers(),
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_MEMBER_CREATE_ENABLED => [
                'type' => 'boolean', 'default' => true,
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_MEMBER_REQUIRED_FIELDS => [
                'type' => 'array', 'default' => ['phone', 'name'],
                'capability' => Capabilities::MANAGE_SETTINGS, 'sanitize' => null,
            ],
            self::OPTION_KDS_SOUND_ENABLED => [
                'type' => 'boolean', 'default' => true,
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

        if ($optionName === self::OPTION_MEMBERSHIP_TIERS) {
            return MembershipTierConfiguration::validateTiers($value);
        }

        if ($optionName === self::OPTION_MEMBER_ACCOUNT_PAGE_ID) {
            $pageId = absint($value);

            return $pageId === 0 || self::isValidMemberAccountPageId($pageId) ? $pageId : 0;
        }

        if (in_array($optionName, [
            self::OPTION_UNINSTALL_DELETE_DATA,
            self::OPTION_RECEIPT_PRINT_ORDER_NOTE,
            self::OPTION_NAV_DEFAULT_COLLAPSED,
            self::OPTION_SHOW_PRODUCT_IMAGES,
            self::OPTION_REQUIRE_DINE_IN_TABLE,
            self::OPTION_REQUIRE_OPEN_SHIFT,
            self::OPTION_KDS_ENABLED,
            self::OPTION_SHIFTS_ENABLED,
            self::OPTION_CASH_ENABLED,
            self::OPTION_BANK_TRANSFER_ENABLED,
            self::OPTION_RECEIPT_AUTO_PRINT,
            self::OPTION_MEMBERSHIP_ENABLED,
            self::OPTION_MEMBER_CREATE_ENABLED,
            self::OPTION_KDS_SOUND_ENABLED,
        ], true)) {
            return (bool) $value;
        }

        if ($optionName === self::OPTION_BRAND_COLOR) {
            $color = strtolower(trim((string) $value));

            return preg_match('/^#[0-9a-f]{6}$/', $color) === 1 ? $color : $definition['default'];
        }

        if ($optionName === self::OPTION_FONT_FAMILY) {
            return isset(self::fontChoices()[(string) $value]) ? (string) $value : $definition['default'];
        }

        if ($optionName === self::OPTION_INTERFACE_DENSITY) {
            return in_array($value, ['compact', 'normal'], true) ? $value : $definition['default'];
        }

        if ($optionName === self::OPTION_CUSTOM_CSS) {
            return self::sanitizeCustomCss((string) $value);
        }

        if ($optionName === self::OPTION_TIMEZONE) {
            $timezone = trim((string) $value);

            return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : $definition['default'];
        }

        if ($optionName === self::OPTION_DATE_FORMAT) {
            return in_array($value, ['d/m/Y', 'm/d/Y', 'Y-m-d'], true) ? $value : $definition['default'];
        }

        if ($optionName === self::OPTION_TIME_FORMAT) {
            return in_array($value, ['H:i', 'g:i a'], true) ? $value : $definition['default'];
        }

        if ($optionName === self::OPTION_DEFAULT_ORDER_TYPE) {
            return in_array($value, ['dine_in', 'takeaway'], true) ? $value : $definition['default'];
        }

        if ($optionName === self::OPTION_VIETQR_TEMPLATE) {
            return in_array($value, ['qronly', 'compact', 'compact2'], true) ? $value : $definition['default'];
        }

        if ($optionName === self::OPTION_VIETQR_REFERENCE_PREFIX) {
            $prefix = strtoupper((string) preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value));

            return substr($prefix, 0, 12) ?: $definition['default'];
        }

        if ($optionName === self::OPTION_RECEIPT_PAPER_WIDTH) {
            return in_array((string) $value, ['58', '80'], true) ? (string) $value : $definition['default'];
        }

        if ($optionName === self::OPTION_MEMBER_REQUIRED_FIELDS) {
            $fields = array_values(array_intersect(['phone', 'name', 'email'], is_array($value) ? $value : []));

            return array_values(array_unique(array_merge(['phone'], $fields)));
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

    private static function sanitizeCustomCss(string $value): string
    {
        $value = trim(str_replace(["\r\n", "\r", "\0"], ["\n", "\n", ''], $value));

        if (strlen($value) > 20000 || self::customCssHasUnsafeSyntax($value)) {
            return '';
        }

        return $value;
    }

    public static function customCssHasUnsafeSyntax(string $value): bool
    {
        return preg_match('/[<>]|@import\b|url\s*\(|expression\s*\(|(?:^|[;{])\s*behavior\s*:|-moz-binding\s*:|javascript\s*:/i', $value) === 1;
    }

    public static function isValidMemberAccountPageId(int $pageId): bool
    {
        if ($pageId <= 0 || ! function_exists('get_post')) {
            return false;
        }

        $page = get_post($pageId);
        if (! is_object($page) || (string) ($page->post_type ?? '') !== 'page' || (string) ($page->post_status ?? '') !== 'publish') {
            return false;
        }

        $conflicts = [
            (int) get_option(self::OPTION_POS_PAGE_ID, 0),
            (int) get_option(self::OPTION_CUSTOMER_PAGE_ID, 0),
            (int) get_option('page_for_posts', 0),
        ];

        return ! in_array($pageId, array_filter($conflicts), true);
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
        $seen = [];

        foreach ($notes as $note) {
            if (! is_array($note)) {
                continue;
            }

            $id = sanitize_key((string) ($note['id'] ?? ''));
            $label = sanitize_text_field((string) ($note['label'] ?? ''));

            if ($id === '' || $label === '' || isset($seen[$id])) {
                continue;
            }

            $seen[$id] = true;

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
