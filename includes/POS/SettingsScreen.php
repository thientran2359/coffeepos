<?php

declare(strict_types=1);

namespace CoffeePOS\POS;

use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Support\Capabilities;

final class SettingsScreen
{
    public function handleRequest(): array
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return ! empty($_GET['settings-updated'])
                ? ['type' => 'success', 'message' => __('Settings saved.', 'coffeepos')]
                : [];
        }

        if (! current_user_can(Capabilities::MANAGE_SETTINGS)) {
            wp_die(
                esc_html__('You are not allowed to update CoffeePOS settings.', 'coffeepos'),
                esc_html__('Forbidden', 'coffeepos'),
                ['response' => 403]
            );
        }

        $nonce = isset($_POST['coffeepos_settings_nonce'])
            ? sanitize_text_field(wp_unslash((string) $_POST['coffeepos_settings_nonce']))
            : '';

        if (! wp_verify_nonce($nonce, 'coffeepos_save_settings')) {
            return ['type' => 'error', 'message' => __('The settings form expired. Please try again.', 'coffeepos')];
        }

        $input = wp_unslash($_POST);
        $action = sanitize_key((string) ($input['settings_action'] ?? 'save'));

        if ($action === 'export') {
            $this->exportSettings();
        }

        if ($action === 'import') {
            return $this->importSettings((string) ($input['settings_import_json'] ?? ''));
        }

        $quickNotes = is_array($input[Settings::OPTION_QUICK_NOTES] ?? null)
            ? $input[Settings::OPTION_QUICK_NOTES]
            : [];
        $quickNoteError = $this->validateQuickNotes($quickNotes);
        $settingsError = $this->validateSettings($input);

        if ($quickNoteError !== '' || $settingsError !== '') {
            return [
                'type' => 'error',
                'message' => $quickNoteError !== '' ? $quickNoteError : $settingsError,
                'quick_notes' => $quickNotes,
                'submitted' => $input,
            ];
        }

        $oldSlug = Settings::getPosBaseSlug();
        Settings::update(
            Settings::OPTION_SERVICE_TABLES,
            Settings::serviceTablesFromText((string) ($input['service_tables_text'] ?? ''))
        );
        Settings::update(Settings::OPTION_VIETQR_BANK_ID, (string) ($input[Settings::OPTION_VIETQR_BANK_ID] ?? ''));
        Settings::update(Settings::OPTION_VIETQR_ACCOUNT_NUMBER, (string) ($input[Settings::OPTION_VIETQR_ACCOUNT_NUMBER] ?? ''));
        Settings::update(Settings::OPTION_VIETQR_ACCOUNT_NAME, (string) ($input[Settings::OPTION_VIETQR_ACCOUNT_NAME] ?? ''));
        Settings::update(Settings::OPTION_KDS_POLL_INTERVAL, $input[Settings::OPTION_KDS_POLL_INTERVAL] ?? 5000);
        Settings::update(Settings::OPTION_ORDER_QUEUE_POLL_INTERVAL, $input[Settings::OPTION_ORDER_QUEUE_POLL_INTERVAL] ?? 5000);
        Settings::update(Settings::OPTION_QUICK_NOTES, $quickNotes);
        Settings::update(Settings::OPTION_RECEIPT_PRINT_ORDER_NOTE, ! empty($input[Settings::OPTION_RECEIPT_PRINT_ORDER_NOTE]));
        Settings::update(Settings::OPTION_BRAND_COLOR, (string) ($input[Settings::OPTION_BRAND_COLOR] ?? ''));
        Settings::update(Settings::OPTION_FONT_FAMILY, (string) ($input[Settings::OPTION_FONT_FAMILY] ?? 'be-vietnam-pro'));
        Settings::update(Settings::OPTION_NAV_DEFAULT_COLLAPSED, ! empty($input[Settings::OPTION_NAV_DEFAULT_COLLAPSED]));
        Settings::update(Settings::OPTION_INTERFACE_DENSITY, (string) ($input[Settings::OPTION_INTERFACE_DENSITY] ?? 'normal'));
        Settings::update(Settings::OPTION_SHOW_PRODUCT_IMAGES, ! empty($input[Settings::OPTION_SHOW_PRODUCT_IMAGES]));
        Settings::update(Settings::OPTION_CUSTOM_CSS, (string) ($input[Settings::OPTION_CUSTOM_CSS] ?? ''));
        Settings::update(Settings::OPTION_STORE_NAME, (string) ($input[Settings::OPTION_STORE_NAME] ?? ''));
        Settings::update(Settings::OPTION_BRANCH_NAME, (string) ($input[Settings::OPTION_BRANCH_NAME] ?? ''));
        Settings::update(Settings::OPTION_LOGO_ID, (int) ($input[Settings::OPTION_LOGO_ID] ?? 0));
        Settings::update(Settings::OPTION_STORE_ADDRESS, (string) ($input[Settings::OPTION_STORE_ADDRESS] ?? ''));
        Settings::update(Settings::OPTION_STORE_PHONE, (string) ($input[Settings::OPTION_STORE_PHONE] ?? ''));
        Settings::update(Settings::OPTION_TIMEZONE, (string) ($input[Settings::OPTION_TIMEZONE] ?? ''));
        Settings::update(Settings::OPTION_DATE_FORMAT, (string) ($input[Settings::OPTION_DATE_FORMAT] ?? ''));
        Settings::update(Settings::OPTION_TIME_FORMAT, (string) ($input[Settings::OPTION_TIME_FORMAT] ?? ''));
        Settings::update(Settings::OPTION_DEFAULT_ORDER_TYPE, (string) ($input[Settings::OPTION_DEFAULT_ORDER_TYPE] ?? 'takeaway'));
        Settings::update(Settings::OPTION_REQUIRE_DINE_IN_TABLE, ! empty($input[Settings::OPTION_REQUIRE_DINE_IN_TABLE]));
        Settings::update(Settings::OPTION_REQUIRE_OPEN_SHIFT, ! empty($input[Settings::OPTION_REQUIRE_OPEN_SHIFT]));
        Settings::update(Settings::OPTION_CASH_ENABLED, ! empty($input[Settings::OPTION_CASH_ENABLED]));
        Settings::update(Settings::OPTION_BANK_TRANSFER_ENABLED, ! empty($input[Settings::OPTION_BANK_TRANSFER_ENABLED]));
        Settings::update(Settings::OPTION_VIETQR_TEMPLATE, (string) ($input[Settings::OPTION_VIETQR_TEMPLATE] ?? 'qronly'));
        Settings::update(Settings::OPTION_VIETQR_REFERENCE_PREFIX, (string) ($input[Settings::OPTION_VIETQR_REFERENCE_PREFIX] ?? 'POS'));
        Settings::update(Settings::OPTION_RECEIPT_PAPER_WIDTH, (string) ($input[Settings::OPTION_RECEIPT_PAPER_WIDTH] ?? '80'));
        Settings::update(Settings::OPTION_RECEIPT_AUTO_PRINT, ! empty($input[Settings::OPTION_RECEIPT_AUTO_PRINT]));
        Settings::update(Settings::OPTION_RECEIPT_FOOTER, (string) ($input[Settings::OPTION_RECEIPT_FOOTER] ?? ''));
        Settings::update(Settings::OPTION_MEMBERSHIP_ENABLED, ! empty($input[Settings::OPTION_MEMBERSHIP_ENABLED]));
        Settings::update(Settings::OPTION_MEMBERSHIP_TIERS, $input[Settings::OPTION_MEMBERSHIP_TIERS] ?? []);
        Settings::update(Settings::OPTION_MEMBER_CREATE_ENABLED, ! empty($input[Settings::OPTION_MEMBER_CREATE_ENABLED]));
        Settings::update(Settings::OPTION_MEMBER_REQUIRED_FIELDS, is_array($input[Settings::OPTION_MEMBER_REQUIRED_FIELDS] ?? null) ? $input[Settings::OPTION_MEMBER_REQUIRED_FIELDS] : ['phone']);
        Settings::update(Settings::OPTION_KDS_SOUND_ENABLED, ! empty($input[Settings::OPTION_KDS_SOUND_ENABLED]));
        Settings::update(Settings::OPTION_POS_BASE_SLUG, (string) ($input[Settings::OPTION_POS_BASE_SLUG] ?? 'pos'));
        Settings::update(Settings::OPTION_UNINSTALL_DELETE_DATA, ! empty($input[Settings::OPTION_UNINSTALL_DELETE_DATA]));

        if ($oldSlug !== Settings::getPosBaseSlug()) {
            Router::registerRewriteRules(Settings::getPosBaseSlug());
            flush_rewrite_rules(false);
        }

        wp_safe_redirect(add_query_arg('settings-updated', '1', Router::routeUrl('settings')));
        exit;
    }

    private function validateQuickNotes(array $quickNotes): string
    {
        if ($quickNotes === []) {
            return __('Configure at least one quick note. To hide all quick notes, disable every row instead.', 'coffeepos');
        }

        $seen = [];

        foreach ($quickNotes as $quickNote) {
            if (! is_array($quickNote)) {
                return __('Each quick note must be a valid settings row.', 'coffeepos');
            }

            $rawId = trim((string) ($quickNote['id'] ?? ''));
            $id = sanitize_key($rawId);
            $label = sanitize_text_field((string) ($quickNote['label'] ?? ''));

            if ($id === '' || $id !== $rawId) {
                return __('Quick note IDs may contain only lowercase letters, numbers, underscores, and hyphens.', 'coffeepos');
            }

            if ($label === '') {
                return __('Every quick note requires a label.', 'coffeepos');
            }

            if (isset($seen[$id])) {
                return sprintf(
                    /* translators: %s: duplicate quick-note ID. */
                    __('Quick note ID "%s" is duplicated. IDs must be unique.', 'coffeepos'),
                    $id
                );
            }

            $seen[$id] = true;
        }

        return '';
    }

    private function validateSettings(array $input): string
    {
        if (isset($input[Settings::OPTION_MEMBERSHIP_TIERS])) {
            try {
                MembershipSettings::validateTiers($input[Settings::OPTION_MEMBERSHIP_TIERS]);
            } catch (\Throwable $error) {
                return $error->getMessage();
            }
        }
        if (trim((string) ($input[Settings::OPTION_STORE_NAME] ?? '')) === '') {
            return __('POS store name is required.', 'coffeepos');
        }

        $timezone = trim((string) ($input[Settings::OPTION_TIMEZONE] ?? ''));
        if (! in_array($timezone, timezone_identifiers_list(), true)) {
            return __('Select a valid IANA timezone.', 'coffeepos');
        }

        $color = strtolower(trim((string) ($input[Settings::OPTION_BRAND_COLOR] ?? '')));
        if (preg_match('/^#[0-9a-f]{6}$/', $color) !== 1) {
            return __('Brand color must be a six-digit hexadecimal color.', 'coffeepos');
        }

        $density = (string) ($input[Settings::OPTION_INTERFACE_DENSITY] ?? 'normal');
        if (! in_array($density, ['compact', 'normal'], true)) {
            return __('Interface density must be Compact or Normal.', 'coffeepos');
        }

        $css = (string) ($input[Settings::OPTION_CUSTOM_CSS] ?? '');
        if (strlen($css) > 20000) {
            return __('Custom CSS cannot exceed 20 KB.', 'coffeepos');
        }

        if (Settings::customCssHasUnsafeSyntax($css)) {
            return __('Custom CSS cannot contain HTML, URL references, @import, expression, behavior, or JavaScript syntax.', 'coffeepos');
        }

        $slug = sanitize_title((string) ($input[Settings::OPTION_POS_BASE_SLUG] ?? ''));
        if ($slug === '' || $slug !== (string) ($input[Settings::OPTION_POS_BASE_SLUG] ?? '')) {
            return __('POS base URL may contain only lowercase letters, numbers, and hyphens.', 'coffeepos');
        }

        if (empty($input[Settings::OPTION_CASH_ENABLED]) && empty($input[Settings::OPTION_BANK_TRANSFER_ENABLED])) {
            return __('Enable at least one payment method.', 'coffeepos');
        }

        $logoId = (int) ($input[Settings::OPTION_LOGO_ID] ?? 0);
        if ($logoId > 0 && function_exists('wp_attachment_is_image') && ! wp_attachment_is_image($logoId)) {
            return __('The selected POS logo is not a valid image attachment.', 'coffeepos');
        }

        return '';
    }

    public function diagnostics(): array
    {
        return [
            __('CoffeePOS version', 'coffeepos') => defined('COFFEEPOS_VERSION') ? COFFEEPOS_VERSION : '',
            __('WordPress version', 'coffeepos') => function_exists('get_bloginfo') ? (string) get_bloginfo('version') : '',
            __('WooCommerce version', 'coffeepos') => defined('WC_VERSION') ? WC_VERSION : __('Unavailable', 'coffeepos'),
            __('PHP version', 'coffeepos') => PHP_VERSION,
            __('REST API', 'coffeepos') => function_exists('rest_url') ? rest_url('coffeepos/v1/') : __('Unavailable', 'coffeepos'),
            __('POS route', 'coffeepos') => Router::routeUrl(),
            __('Timezone', 'coffeepos') => (string) Settings::get(Settings::OPTION_TIMEZONE),
        ];
    }

    private function exportSettings(): void
    {
        $filename = 'coffeepos-settings-' . gmdate('Y-m-d') . '.json';
        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo wp_json_encode(Settings::exportValues(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function importSettings(string $json): array
    {
        $payload = json_decode($json, true);
        if (! is_array($payload) || (int) ($payload['schema_version'] ?? 0) !== 1 || ! is_array($payload['settings'] ?? null)) {
            return ['type' => 'error', 'message' => __('Import must be a valid CoffeePOS settings JSON export.', 'coffeepos')];
        }

        $values = array_intersect_key($payload['settings'], array_flip(Settings::importableOptionNames()));
        if ($values === []) {
            return ['type' => 'error', 'message' => __('The import contains no supported CoffeePOS settings.', 'coffeepos')];
        }

        $cashEnabled = array_key_exists(Settings::OPTION_CASH_ENABLED, $values) ? ! empty($values[Settings::OPTION_CASH_ENABLED]) : (bool) Settings::get(Settings::OPTION_CASH_ENABLED);
        $bankEnabled = array_key_exists(Settings::OPTION_BANK_TRANSFER_ENABLED, $values) ? ! empty($values[Settings::OPTION_BANK_TRANSFER_ENABLED]) : (bool) Settings::get(Settings::OPTION_BANK_TRANSFER_ENABLED);
        if (! $cashEnabled && ! $bankEnabled) {
            return ['type' => 'error', 'message' => __('The imported settings disable every payment method.', 'coffeepos')];
        }

        $candidate = array_merge(Settings::exportValues()['settings'], $values);
        $settingsError = $this->validateSettings($candidate);
        if ($settingsError !== '') {
            return ['type' => 'error', 'message' => $settingsError];
        }

        if (array_key_exists(Settings::OPTION_QUICK_NOTES, $values)) {
            $quickNotes = is_array($values[Settings::OPTION_QUICK_NOTES]) ? $values[Settings::OPTION_QUICK_NOTES] : [];
            $quickNoteError = $this->validateQuickNotes($quickNotes);
            if ($quickNoteError !== '') {
                return ['type' => 'error', 'message' => $quickNoteError];
            }
        }

        $oldSlug = Settings::getPosBaseSlug();
        foreach ($values as $optionName => $value) {
            Settings::update((string) $optionName, $value);
        }
        if ($oldSlug !== Settings::getPosBaseSlug()) {
            Router::registerRewriteRules(Settings::getPosBaseSlug());
            flush_rewrite_rules(false);
        }

        return ['type' => 'success', 'message' => __('Settings imported successfully.', 'coffeepos')];
    }
}
