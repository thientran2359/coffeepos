<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Infrastructure\Templates\TemplateLoader;

$context = TemplateLoader::context();
$notice = is_array($context['settings_notice'] ?? null) ? $context['settings_notice'] : [];
$quickNotes = (array) Settings::get(Settings::OPTION_QUICK_NOTES);
?>
<section class="coffeepos-operations coffeepos-settings" data-component="settings-screen">
    <header class="coffeepos-operations__header">
        <div>
            <span class="coffeepos-eyebrow"><?php esc_html_e('Management', 'coffeepos'); ?></span>
            <h1><?php esc_html_e('Settings', 'coffeepos'); ?></h1>
        </div>
        <div class="coffeepos-operations__tools"><a class="coffeepos-btn" href="<?php echo esc_url(\CoffeePOS\POS\Router::routeUrl('cashier')); ?>"><?php esc_html_e('Back to Cashier', 'coffeepos'); ?></a></div>
    </header>

    <?php if ($notice !== []) : ?>
        <p class="coffeepos-settings__notice is-<?php echo esc_attr((string) ($notice['type'] ?? 'info')); ?>" role="<?php echo ($notice['type'] ?? '') === 'error' ? 'alert' : 'status'; ?>">
            <?php echo esc_html((string) ($notice['message'] ?? '')); ?>
        </p>
    <?php endif; ?>

    <form class="coffeepos-settings__form" method="post" action="<?php echo esc_url(\CoffeePOS\POS\Router::routeUrl('settings')); ?>" data-component="settings-form">
        <?php wp_nonce_field('coffeepos_save_settings', 'coffeepos_settings_nonce'); ?>

        <section class="coffeepos-settings__card" aria-labelledby="coffeepos-settings-tables-title">
            <div class="coffeepos-settings__card-heading"><div>
                <h2 id="coffeepos-settings-tables-title"><?php esc_html_e('Dine-in tables', 'coffeepos'); ?></h2>
                <p><?php esc_html_e('Enter one table name per line. Empty lines and duplicate names are ignored.', 'coffeepos'); ?></p>
            </div></div>
            <label class="coffeepos-settings__field" for="coffeepos-service-tables">
                <span><?php esc_html_e('Table list', 'coffeepos'); ?></span>
                <textarea id="coffeepos-service-tables" name="service_tables_text" rows="9" placeholder="<?php echo esc_attr("Table 01\nTable 02\nTerrace 01"); ?>"><?php echo esc_textarea(Settings::serviceTablesToText()); ?></textarea>
                <small><?php esc_html_e('The displayed order follows the line order. Cashier loads this list when Dine-in is selected.', 'coffeepos'); ?></small>
            </label>
        </section>

        <section class="coffeepos-settings__card" aria-labelledby="coffeepos-settings-vietqr-title">
            <div class="coffeepos-settings__card-heading"><div><h2 id="coffeepos-settings-vietqr-title"><?php esc_html_e('VietQR checkout', 'coffeepos'); ?></h2><p><?php esc_html_e('Used for the bank-transfer QR shown on Customer Display.', 'coffeepos'); ?></p></div></div>
            <div class="coffeepos-settings__grid">
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Bank ID', 'coffeepos'); ?></span><input type="text" name="<?php echo esc_attr(Settings::OPTION_VIETQR_BANK_ID); ?>" value="<?php echo esc_attr((string) Settings::get(Settings::OPTION_VIETQR_BANK_ID)); ?>"></label>
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Account number', 'coffeepos'); ?></span><input type="text" name="<?php echo esc_attr(Settings::OPTION_VIETQR_ACCOUNT_NUMBER); ?>" value="<?php echo esc_attr((string) Settings::get(Settings::OPTION_VIETQR_ACCOUNT_NUMBER)); ?>"></label>
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Account holder', 'coffeepos'); ?></span><input type="text" name="<?php echo esc_attr(Settings::OPTION_VIETQR_ACCOUNT_NAME); ?>" value="<?php echo esc_attr((string) Settings::get(Settings::OPTION_VIETQR_ACCOUNT_NAME)); ?>"></label>
            </div>
        </section>

        <section class="coffeepos-settings__card" aria-labelledby="coffeepos-settings-operations-title">
            <div class="coffeepos-settings__card-heading"><div><h2 id="coffeepos-settings-operations-title"><?php esc_html_e('Operational screens', 'coffeepos'); ?></h2><p><?php esc_html_e('Polling intervals are limited to 3000–60000 milliseconds.', 'coffeepos'); ?></p></div></div>
            <div class="coffeepos-settings__grid">
                <label class="coffeepos-settings__field"><span><?php esc_html_e('KDS polling interval (ms)', 'coffeepos'); ?></span><input type="number" min="3000" max="60000" step="1000" name="<?php echo esc_attr(Settings::OPTION_KDS_POLL_INTERVAL); ?>" value="<?php echo esc_attr((string) Settings::getKdsPollInterval()); ?>"></label>
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Order Queue polling interval (ms)', 'coffeepos'); ?></span><input type="number" min="3000" max="60000" step="1000" name="<?php echo esc_attr(Settings::OPTION_ORDER_QUEUE_POLL_INTERVAL); ?>" value="<?php echo esc_attr((string) Settings::getOrderQueuePollInterval()); ?>"></label>
            </div>
        </section>

        <section class="coffeepos-settings__card" aria-labelledby="coffeepos-settings-notes-title">
            <div class="coffeepos-settings__card-heading"><div><h2 id="coffeepos-settings-notes-title"><?php esc_html_e('Item quick notes', 'coffeepos'); ?></h2><p><?php esc_html_e('Keep IDs stable. Product and category IDs are comma-separated.', 'coffeepos'); ?></p></div></div>
            <div class="coffeepos-settings__table-wrap"><table class="coffeepos-settings__table">
                <thead><tr><th><?php esc_html_e('ID', 'coffeepos'); ?></th><th><?php esc_html_e('Label', 'coffeepos'); ?></th><th><?php esc_html_e('Product IDs', 'coffeepos'); ?></th><th><?php esc_html_e('Category IDs', 'coffeepos'); ?></th><th><?php esc_html_e('Order', 'coffeepos'); ?></th><th><?php esc_html_e('Enabled', 'coffeepos'); ?></th></tr></thead>
                <tbody>
                <?php foreach ($quickNotes as $index => $note) : if (! is_array($note)) { continue; } $prefix = Settings::OPTION_QUICK_NOTES . '[' . (int) $index . ']'; ?>
                    <tr>
                        <td><input type="text" readonly name="<?php echo esc_attr($prefix . '[id]'); ?>" value="<?php echo esc_attr((string) ($note['id'] ?? '')); ?>"></td>
                        <td><input type="text" required name="<?php echo esc_attr($prefix . '[label]'); ?>" value="<?php echo esc_attr((string) ($note['label'] ?? '')); ?>"></td>
                        <td><input type="text" name="<?php echo esc_attr($prefix . '[product_ids]'); ?>" value="<?php echo esc_attr(implode(', ', array_map('absint', (array) ($note['product_ids'] ?? [])))); ?>"></td>
                        <td><input type="text" name="<?php echo esc_attr($prefix . '[category_ids]'); ?>" value="<?php echo esc_attr(implode(', ', array_map('absint', (array) ($note['category_ids'] ?? [])))); ?>"></td>
                        <td><input class="is-order" type="number" name="<?php echo esc_attr($prefix . '[sort_order]'); ?>" value="<?php echo esc_attr((string) ($note['sort_order'] ?? 0)); ?>"></td>
                        <td><input type="hidden" name="<?php echo esc_attr($prefix . '[enabled]'); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr($prefix . '[enabled]'); ?>" value="1" <?php checked(! empty($note['enabled'])); ?>></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
        </section>

        <section class="coffeepos-settings__card" aria-labelledby="coffeepos-settings-receipt-title">
            <div class="coffeepos-settings__card-heading"><div><h2 id="coffeepos-settings-receipt-title"><?php esc_html_e('Receipt', 'coffeepos'); ?></h2></div></div>
            <label class="coffeepos-settings__check"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_RECEIPT_PRINT_ORDER_NOTE); ?>" value="1" <?php checked(Settings::shouldPrintOrderNote()); ?>><span><?php esc_html_e('Print the private order note on receipts', 'coffeepos'); ?></span></label>
        </section>

        <div class="coffeepos-settings__actions"><button class="coffeepos-btn coffeepos-btn-primary" type="submit"><?php esc_html_e('Save settings', 'coffeepos'); ?></button></div>
    </form>
</section>
