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
        $quickNotes = is_array($input[Settings::OPTION_QUICK_NOTES] ?? null)
            ? $input[Settings::OPTION_QUICK_NOTES]
            : [];
        $quickNoteError = $this->validateQuickNotes($quickNotes);

        if ($quickNoteError !== '') {
            return [
                'type' => 'error',
                'message' => $quickNoteError,
                'quick_notes' => $quickNotes,
            ];
        }

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
}
