<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Infrastructure\Templates\TemplateLoader;

$context = TemplateLoader::context();
$notice = is_array($context['settings_notice'] ?? null) ? $context['settings_notice'] : [];
$quickNotes = isset($notice['quick_notes']) && is_array($notice['quick_notes'])
    ? $notice['quick_notes']
    : (array) Settings::get(Settings::OPTION_QUICK_NOTES);
$submitted = isset($notice['submitted']) && is_array($notice['submitted'])
    ? $notice['submitted']
    : (isset($notice['appearance']) && is_array($notice['appearance']) ? $notice['appearance'] : []);
$settingValue = static function (string $optionName) use ($submitted) {
    return array_key_exists($optionName, $submitted) ? $submitted[$optionName] : Settings::get($optionName);
};
$logoId = (int) $settingValue(Settings::OPTION_LOGO_ID);
$logoUrl = $logoId > 0 && function_exists('wp_get_attachment_image_url')
    ? (string) (wp_get_attachment_image_url($logoId, 'medium') ?: '')
    : '';
$diagnostics = is_array($context['settings_diagnostics'] ?? null) ? $context['settings_diagnostics'] : [];
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

        <section class="coffeepos-settings__card" aria-labelledby="coffeepos-settings-general-title">
            <div class="coffeepos-settings__card-heading"><div>
                <h2 id="coffeepos-settings-general-title"><?php esc_html_e('General', 'coffeepos'); ?></h2>
                <p><?php esc_html_e('CoffeePOS identity is independent from the WordPress site title and WooCommerce store address.', 'coffeepos'); ?></p>
            </div></div>
            <div class="coffeepos-settings__grid">
                <label class="coffeepos-settings__field"><span><?php esc_html_e('POS store name', 'coffeepos'); ?></span><input type="text" maxlength="120" required name="<?php echo esc_attr(Settings::OPTION_STORE_NAME); ?>" value="<?php echo esc_attr((string) $settingValue(Settings::OPTION_STORE_NAME)); ?>"></label>
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Branch name', 'coffeepos'); ?></span><input type="text" maxlength="120" name="<?php echo esc_attr(Settings::OPTION_BRANCH_NAME); ?>" value="<?php echo esc_attr((string) $settingValue(Settings::OPTION_BRANCH_NAME)); ?>"></label>
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Phone number', 'coffeepos'); ?></span><input type="tel" maxlength="40" name="<?php echo esc_attr(Settings::OPTION_STORE_PHONE); ?>" value="<?php echo esc_attr((string) $settingValue(Settings::OPTION_STORE_PHONE)); ?>"></label>
            </div>
            <label class="coffeepos-settings__field"><span><?php esc_html_e('Address', 'coffeepos'); ?></span><textarea rows="3" maxlength="500" name="<?php echo esc_attr(Settings::OPTION_STORE_ADDRESS); ?>"><?php echo esc_textarea((string) $settingValue(Settings::OPTION_STORE_ADDRESS)); ?></textarea></label>
            <div class="coffeepos-settings__grid">
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Timezone', 'coffeepos'); ?></span><input type="text" list="coffeepos-timezones" required name="<?php echo esc_attr(Settings::OPTION_TIMEZONE); ?>" value="<?php echo esc_attr((string) $settingValue(Settings::OPTION_TIMEZONE)); ?>"><datalist id="coffeepos-timezones"><option value="Asia/Ho_Chi_Minh"><option value="Asia/Bangkok"><option value="UTC"></datalist></label>
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Date format', 'coffeepos'); ?></span><select name="<?php echo esc_attr(Settings::OPTION_DATE_FORMAT); ?>"><?php foreach (['d/m/Y' => 'DD/MM/YYYY', 'm/d/Y' => 'MM/DD/YYYY', 'Y-m-d' => 'YYYY-MM-DD'] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected((string) $settingValue(Settings::OPTION_DATE_FORMAT), $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Time format', 'coffeepos'); ?></span><select name="<?php echo esc_attr(Settings::OPTION_TIME_FORMAT); ?>"><option value="H:i" <?php selected((string) $settingValue(Settings::OPTION_TIME_FORMAT), 'H:i'); ?>>24-hour (14:30)</option><option value="g:i a" <?php selected((string) $settingValue(Settings::OPTION_TIME_FORMAT), 'g:i a'); ?>>12-hour (2:30 pm)</option></select></label>
            </div>
            <div class="coffeepos-settings__logo" data-component="settings-logo">
                <input type="hidden" data-component="settings-logo-id" name="<?php echo esc_attr(Settings::OPTION_LOGO_ID); ?>" value="<?php echo esc_attr((string) $settingValue(Settings::OPTION_LOGO_ID)); ?>">
                <img data-component="settings-logo-preview" src="<?php echo esc_url($logoUrl); ?>" alt="" <?php echo $logoUrl === '' ? 'hidden' : ''; ?>>
                <div><strong><?php esc_html_e('POS logo', 'coffeepos'); ?></strong><p><?php esc_html_e('Used on Login, Customer Display and receipt.', 'coffeepos'); ?></p><button type="button" class="coffeepos-btn" data-action="select-settings-logo"><?php esc_html_e('Select logo', 'coffeepos'); ?></button> <button type="button" class="coffeepos-btn" data-action="remove-settings-logo" <?php echo $logoUrl === '' ? 'hidden' : ''; ?>><?php esc_html_e('Remove', 'coffeepos'); ?></button></div>
            </div>
        </section>

        <section class="coffeepos-settings__card" aria-labelledby="coffeepos-settings-appearance-title">
            <div class="coffeepos-settings__card-heading"><div>
                <h2 id="coffeepos-settings-appearance-title"><?php esc_html_e('Appearance', 'coffeepos'); ?></h2>
                <p><?php esc_html_e('Customize CoffeePOS screens without changing the WordPress theme or storefront.', 'coffeepos'); ?></p>
            </div></div>
            <div class="coffeepos-settings__grid">
                <label class="coffeepos-settings__field">
                    <span><?php esc_html_e('Brand color', 'coffeepos'); ?></span>
                    <input class="coffeepos-settings__color" type="color" name="<?php echo esc_attr(Settings::OPTION_BRAND_COLOR); ?>" value="<?php echo esc_attr((string) $settingValue(Settings::OPTION_BRAND_COLOR)); ?>">
                </label>
                <label class="coffeepos-settings__field">
                    <span><?php esc_html_e('Interface font', 'coffeepos'); ?></span>
                    <select name="<?php echo esc_attr(Settings::OPTION_FONT_FAMILY); ?>">
                        <?php foreach (Settings::fontChoices() as $fontValue => $fontChoice) : ?>
                            <option value="<?php echo esc_attr($fontValue); ?>" <?php selected((string) $settingValue(Settings::OPTION_FONT_FAMILY), $fontValue); ?>><?php echo esc_html($fontChoice['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small><?php esc_html_e('The bundled default works offline. Google Fonts choices require an internet connection on each POS display.', 'coffeepos'); ?></small>
                </label>
                <label class="coffeepos-settings__field">
                    <span><?php esc_html_e('Interface density', 'coffeepos'); ?></span>
                    <select name="<?php echo esc_attr(Settings::OPTION_INTERFACE_DENSITY); ?>">
                        <option value="normal" <?php selected((string) $settingValue(Settings::OPTION_INTERFACE_DENSITY), 'normal'); ?>><?php esc_html_e('Normal', 'coffeepos'); ?></option>
                        <option value="compact" <?php selected((string) $settingValue(Settings::OPTION_INTERFACE_DENSITY), 'compact'); ?>><?php esc_html_e('Compact', 'coffeepos'); ?></option>
                    </select>
                </label>
                <div class="coffeepos-settings__checks">
                    <label class="coffeepos-settings__check"><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_NAV_DEFAULT_COLLAPSED); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_NAV_DEFAULT_COLLAPSED); ?>" value="1" <?php checked(! empty($settingValue(Settings::OPTION_NAV_DEFAULT_COLLAPSED))); ?>><span><?php esc_html_e('Collapse staff menu by default', 'coffeepos'); ?></span></label>
                    <label class="coffeepos-settings__check"><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_SHOW_PRODUCT_IMAGES); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_SHOW_PRODUCT_IMAGES); ?>" value="1" <?php checked(! empty($settingValue(Settings::OPTION_SHOW_PRODUCT_IMAGES))); ?>><span><?php esc_html_e('Show product images', 'coffeepos'); ?></span></label>
                </div>
            </div>
            <label class="coffeepos-settings__field coffeepos-settings__custom-css" for="coffeepos-custom-css">
                <span><?php esc_html_e('Custom CSS', 'coffeepos'); ?></span>
                <textarea id="coffeepos-custom-css" name="<?php echo esc_attr(Settings::OPTION_CUSTOM_CSS); ?>" rows="10" maxlength="20000" spellcheck="false" placeholder="#coffeepos-app {&#10;    --coffeepos-radius: 12px;&#10;}"><?php echo esc_textarea((string) $settingValue(Settings::OPTION_CUSTOM_CSS)); ?></textarea>
                <small><?php esc_html_e('Loaded after CoffeePOS styles on /pos/* only. URL references, @import, HTML, expression, behavior, and JavaScript syntax are blocked. Invalid CSS may disrupt the POS layout.', 'coffeepos'); ?></small>
            </label>
        </section>

        <section class="coffeepos-settings__card" aria-labelledby="coffeepos-settings-sales-title">
            <div class="coffeepos-settings__card-heading"><div><h2 id="coffeepos-settings-sales-title"><?php esc_html_e('Sales', 'coffeepos'); ?></h2><p><?php esc_html_e('Defaults and requirements enforced by both Cashier and the server.', 'coffeepos'); ?></p></div></div>
            <div class="coffeepos-settings__grid">
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Default service', 'coffeepos'); ?></span><select name="<?php echo esc_attr(Settings::OPTION_DEFAULT_ORDER_TYPE); ?>"><option value="takeaway" <?php selected((string) $settingValue(Settings::OPTION_DEFAULT_ORDER_TYPE), 'takeaway'); ?>><?php esc_html_e('Takeaway', 'coffeepos'); ?></option><option value="dine_in" <?php selected((string) $settingValue(Settings::OPTION_DEFAULT_ORDER_TYPE), 'dine_in'); ?>><?php esc_html_e('Dine-in', 'coffeepos'); ?></option></select></label>
                <div class="coffeepos-settings__checks">
                    <label class="coffeepos-settings__check"><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_REQUIRE_DINE_IN_TABLE); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_REQUIRE_DINE_IN_TABLE); ?>" value="1" <?php checked(! empty($settingValue(Settings::OPTION_REQUIRE_DINE_IN_TABLE))); ?>><span><?php esc_html_e('Require a table for Dine-in', 'coffeepos'); ?></span></label>
                    <label class="coffeepos-settings__check"><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_REQUIRE_OPEN_SHIFT); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_REQUIRE_OPEN_SHIFT); ?>" value="1" <?php checked(! empty($settingValue(Settings::OPTION_REQUIRE_OPEN_SHIFT))); ?>><span><?php esc_html_e('Require an open shift before checkout', 'coffeepos'); ?></span></label>
                </div>
            </div>
        </section>

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
            <div class="coffeepos-settings__card-heading"><div><h2 id="coffeepos-settings-vietqr-title"><?php esc_html_e('Payments', 'coffeepos'); ?></h2><p><?php esc_html_e('At least one payment method must remain enabled. VietQR is shown only on Customer Display.', 'coffeepos'); ?></p></div></div>
            <div class="coffeepos-settings__checks coffeepos-settings__checks--inline">
                <label class="coffeepos-settings__check"><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_CASH_ENABLED); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_CASH_ENABLED); ?>" value="1" <?php checked(! empty($settingValue(Settings::OPTION_CASH_ENABLED))); ?>><span><?php esc_html_e('Enable Cash', 'coffeepos'); ?></span></label>
                <label class="coffeepos-settings__check"><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_BANK_TRANSFER_ENABLED); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_BANK_TRANSFER_ENABLED); ?>" value="1" <?php checked(! empty($settingValue(Settings::OPTION_BANK_TRANSFER_ENABLED))); ?>><span><?php esc_html_e('Enable Bank Transfer', 'coffeepos'); ?></span></label>
            </div>
            <div class="coffeepos-settings__grid">
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Bank ID', 'coffeepos'); ?></span><input type="text" name="<?php echo esc_attr(Settings::OPTION_VIETQR_BANK_ID); ?>" value="<?php echo esc_attr((string) $settingValue(Settings::OPTION_VIETQR_BANK_ID)); ?>"></label>
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Account number', 'coffeepos'); ?></span><input type="text" name="<?php echo esc_attr(Settings::OPTION_VIETQR_ACCOUNT_NUMBER); ?>" value="<?php echo esc_attr((string) $settingValue(Settings::OPTION_VIETQR_ACCOUNT_NUMBER)); ?>"></label>
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Account holder', 'coffeepos'); ?></span><input type="text" name="<?php echo esc_attr(Settings::OPTION_VIETQR_ACCOUNT_NAME); ?>" value="<?php echo esc_attr((string) $settingValue(Settings::OPTION_VIETQR_ACCOUNT_NAME)); ?>"></label>
                <label class="coffeepos-settings__field"><span><?php esc_html_e('VietQR template', 'coffeepos'); ?></span><select name="<?php echo esc_attr(Settings::OPTION_VIETQR_TEMPLATE); ?>"><?php foreach (['qronly' => 'QR only', 'compact' => 'Compact', 'compact2' => 'Compact 2'] as $value => $label) : ?><option value="<?php echo esc_attr($value); ?>" <?php selected((string) $settingValue(Settings::OPTION_VIETQR_TEMPLATE), $value); ?>><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Transfer reference prefix', 'coffeepos'); ?></span><input type="text" maxlength="12" name="<?php echo esc_attr(Settings::OPTION_VIETQR_REFERENCE_PREFIX); ?>" value="<?php echo esc_attr((string) $settingValue(Settings::OPTION_VIETQR_REFERENCE_PREFIX)); ?>" placeholder="POS"></label>
            </div>
        </section>

        <section class="coffeepos-settings__card" aria-labelledby="coffeepos-settings-operations-title">
            <div class="coffeepos-settings__card-heading"><div><h2 id="coffeepos-settings-operations-title"><?php esc_html_e('Operational screens', 'coffeepos'); ?></h2><p><?php esc_html_e('Polling intervals are limited to 3000–60000 milliseconds.', 'coffeepos'); ?></p></div></div>
            <div class="coffeepos-settings__grid">
                <label class="coffeepos-settings__field"><span><?php esc_html_e('KDS polling interval (ms)', 'coffeepos'); ?></span><input type="number" min="3000" max="60000" step="1000" name="<?php echo esc_attr(Settings::OPTION_KDS_POLL_INTERVAL); ?>" value="<?php echo esc_attr((string) Settings::getKdsPollInterval()); ?>"></label>
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Order Queue polling interval (ms)', 'coffeepos'); ?></span><input type="number" min="3000" max="60000" step="1000" name="<?php echo esc_attr(Settings::OPTION_ORDER_QUEUE_POLL_INTERVAL); ?>" value="<?php echo esc_attr((string) Settings::getOrderQueuePollInterval()); ?>"></label>
                <label class="coffeepos-settings__check"><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_KDS_SOUND_ENABLED); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_KDS_SOUND_ENABLED); ?>" value="1" <?php checked(! empty($settingValue(Settings::OPTION_KDS_SOUND_ENABLED))); ?>><span><?php esc_html_e('Allow new-order sound on KDS', 'coffeepos'); ?></span></label>
            </div>
        </section>

        <section class="coffeepos-settings__card" aria-labelledby="coffeepos-settings-notes-title">
            <div class="coffeepos-settings__card-heading">
                <div><h2 id="coffeepos-settings-notes-title"><?php esc_html_e('Item quick notes', 'coffeepos'); ?></h2><p><?php esc_html_e('Keep saved IDs stable. Product and category IDs are comma-separated.', 'coffeepos'); ?></p></div>
                <button type="button" class="coffeepos-btn" data-action="add-quick-note"><?php esc_html_e('Add quick note', 'coffeepos'); ?></button>
            </div>
            <div class="coffeepos-settings__table-wrap"><table class="coffeepos-settings__table">
                <thead><tr><th><?php esc_html_e('ID', 'coffeepos'); ?></th><th><?php esc_html_e('Label', 'coffeepos'); ?></th><th><?php esc_html_e('Product IDs', 'coffeepos'); ?></th><th><?php esc_html_e('Category IDs', 'coffeepos'); ?></th><th><?php esc_html_e('Order', 'coffeepos'); ?></th><th><?php esc_html_e('Enabled', 'coffeepos'); ?></th><th><?php esc_html_e('Actions', 'coffeepos'); ?></th></tr></thead>
                <tbody data-component="quick-note-rows">
                <?php foreach ($quickNotes as $index => $note) : if (! is_array($note)) { continue; } $prefix = Settings::OPTION_QUICK_NOTES . '[' . (int) $index . ']'; ?>
                    <tr data-component="quick-note-row" data-existing="true">
                        <td><input type="text" readonly name="<?php echo esc_attr($prefix . '[id]'); ?>" value="<?php echo esc_attr((string) ($note['id'] ?? '')); ?>"></td>
                        <td><input type="text" required name="<?php echo esc_attr($prefix . '[label]'); ?>" value="<?php echo esc_attr((string) ($note['label'] ?? '')); ?>"></td>
                        <td><input type="text" name="<?php echo esc_attr($prefix . '[product_ids]'); ?>" value="<?php echo esc_attr(implode(', ', array_map('absint', (array) ($note['product_ids'] ?? [])))); ?>"></td>
                        <td><input type="text" name="<?php echo esc_attr($prefix . '[category_ids]'); ?>" value="<?php echo esc_attr(implode(', ', array_map('absint', (array) ($note['category_ids'] ?? [])))); ?>"></td>
                        <td><input class="is-order" type="number" name="<?php echo esc_attr($prefix . '[sort_order]'); ?>" value="<?php echo esc_attr((string) ($note['sort_order'] ?? 0)); ?>"></td>
                        <td><input type="hidden" name="<?php echo esc_attr($prefix . '[enabled]'); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr($prefix . '[enabled]'); ?>" value="1" <?php checked(! empty($note['enabled'])); ?>></td>
                        <td><button type="button" class="coffeepos-btn coffeepos-settings__remove-row" data-action="remove-quick-note"><?php esc_html_e('Remove', 'coffeepos'); ?></button></td>
                    </tr>
                <?php endforeach; ?>
                    <tr data-component="quick-note-empty" <?php echo $quickNotes !== [] ? 'hidden' : ''; ?>><td colspan="7"><?php esc_html_e('No quick notes configured.', 'coffeepos'); ?></td></tr>
                </tbody>
            </table></div>
            <template id="coffeepos-quick-note-row-template">
                <tr data-component="quick-note-row" data-existing="false">
                    <td><input type="text" required pattern="[a-z0-9_-]+" maxlength="64" data-setting-field="id" placeholder="less_hot"></td>
                    <td><input type="text" required maxlength="120" data-setting-field="label" placeholder="<?php esc_attr_e('Less hot', 'coffeepos'); ?>"></td>
                    <td><input type="text" data-setting-field="product_ids" placeholder="12, 34"></td>
                    <td><input type="text" data-setting-field="category_ids" placeholder="5, 8"></td>
                    <td><input class="is-order" type="number" data-setting-field="sort_order" value="50"></td>
                    <td><input type="hidden" data-setting-field="enabled" value="0"><input type="checkbox" data-setting-field="enabled" value="1" checked></td>
                    <td><button type="button" class="coffeepos-btn coffeepos-settings__remove-row" data-action="remove-quick-note"><?php esc_html_e('Remove', 'coffeepos'); ?></button></td>
                </tr>
            </template>
        </section>

        <section class="coffeepos-settings__card" aria-labelledby="coffeepos-settings-receipt-title">
            <div class="coffeepos-settings__card-heading"><div><h2 id="coffeepos-settings-receipt-title"><?php esc_html_e('Receipt', 'coffeepos'); ?></h2><p><?php esc_html_e('Store identity comes from General. Core order totals and payment information are always printed.', 'coffeepos'); ?></p></div></div>
            <div class="coffeepos-settings__grid">
                <label class="coffeepos-settings__field"><span><?php esc_html_e('Paper width', 'coffeepos'); ?></span><select name="<?php echo esc_attr(Settings::OPTION_RECEIPT_PAPER_WIDTH); ?>"><option value="58" <?php selected((string) $settingValue(Settings::OPTION_RECEIPT_PAPER_WIDTH), '58'); ?>>58 mm</option><option value="80" <?php selected((string) $settingValue(Settings::OPTION_RECEIPT_PAPER_WIDTH), '80'); ?>>80 mm</option></select></label>
                <div class="coffeepos-settings__checks">
                    <label class="coffeepos-settings__check"><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_RECEIPT_AUTO_PRINT); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_RECEIPT_AUTO_PRINT); ?>" value="1" <?php checked(! empty($settingValue(Settings::OPTION_RECEIPT_AUTO_PRINT))); ?>><span><?php esc_html_e('Automatically open print after successful checkout', 'coffeepos'); ?></span></label>
                    <label class="coffeepos-settings__check"><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_RECEIPT_PRINT_ORDER_NOTE); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_RECEIPT_PRINT_ORDER_NOTE); ?>" value="1" <?php checked(! empty($settingValue(Settings::OPTION_RECEIPT_PRINT_ORDER_NOTE))); ?>><span><?php esc_html_e('Print the private order note on receipts', 'coffeepos'); ?></span></label>
                </div>
            </div>
            <label class="coffeepos-settings__field"><span><?php esc_html_e('Receipt footer', 'coffeepos'); ?></span><textarea rows="3" maxlength="500" name="<?php echo esc_attr(Settings::OPTION_RECEIPT_FOOTER); ?>"><?php echo esc_textarea((string) $settingValue(Settings::OPTION_RECEIPT_FOOTER)); ?></textarea></label>
        </section>

        <section class="coffeepos-settings__card" aria-labelledby="coffeepos-settings-membership-title">
            <div class="coffeepos-settings__card-heading"><div><h2 id="coffeepos-settings-membership-title"><?php esc_html_e('Customer & Membership', 'coffeepos'); ?></h2><p><?php esc_html_e('Phone is always required and remains privacy-masked on Customer Display.', 'coffeepos'); ?></p></div></div>
            <div class="coffeepos-settings__checks coffeepos-settings__checks--inline">
                <label class="coffeepos-settings__check"><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_MEMBERSHIP_ENABLED); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_MEMBERSHIP_ENABLED); ?>" value="1" <?php checked(! empty($settingValue(Settings::OPTION_MEMBERSHIP_ENABLED))); ?>><span><?php esc_html_e('Enable customer/member lookup', 'coffeepos'); ?></span></label>
                <label class="coffeepos-settings__check"><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_MEMBER_CREATE_ENABLED); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_MEMBER_CREATE_ENABLED); ?>" value="1" <?php checked(! empty($settingValue(Settings::OPTION_MEMBER_CREATE_ENABLED))); ?>><span><?php esc_html_e('Allow cashier to create members', 'coffeepos'); ?></span></label>
            </div>
            <?php $requiredFields = array_values(array_intersect(['phone', 'name', 'email'], (array) $settingValue(Settings::OPTION_MEMBER_REQUIRED_FIELDS))); ?>
            <fieldset class="coffeepos-settings__fieldset"><legend><?php esc_html_e('Required fields when creating a member', 'coffeepos'); ?></legend>
                <label class="coffeepos-settings__check"><input type="checkbox" checked disabled><span><?php esc_html_e('Phone', 'coffeepos'); ?></span></label><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_MEMBER_REQUIRED_FIELDS); ?>[]" value="phone">
                <label class="coffeepos-settings__check"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_MEMBER_REQUIRED_FIELDS); ?>[]" value="name" <?php checked(in_array('name', $requiredFields, true)); ?>><span><?php esc_html_e('Name', 'coffeepos'); ?></span></label>
                <label class="coffeepos-settings__check"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_MEMBER_REQUIRED_FIELDS); ?>[]" value="email" <?php checked(in_array('email', $requiredFields, true)); ?>><span><?php esc_html_e('Email', 'coffeepos'); ?></span></label>
            </fieldset>
            <?php
            $tierRows = (array) $settingValue(Settings::OPTION_MEMBERSHIP_TIERS);
            $tierRows = array_merge($tierRows, array_fill(0, min(2, 30 - count($tierRows)), ['code' => '', 'label' => '', 'minimum' => '', 'coupon' => '']));
            ?>
            <div class="coffeepos-settings__card-heading coffeepos-settings__subheading"><div><h3><?php esc_html_e('Membership tiers', 'coffeepos'); ?></h3><p><?php esc_html_e('Only paid CoffeePOS orders count, after refunds. Empty rows are ignored.', 'coffeepos'); ?></p></div></div>
            <div class="coffeepos-settings__tier-list">
                <?php foreach ($tierRows as $index => $tierRow) : ?>
                    <fieldset class="coffeepos-settings__tier-row">
                        <?php foreach (['code' => __('Tier code', 'coffeepos'), 'label' => __('Tier name', 'coffeepos'), 'minimum' => __('Minimum spend', 'coffeepos'), 'coupon' => __('Coupon', 'coffeepos')] as $field => $fieldLabel) : ?>
                            <label class="coffeepos-settings__field"><span><?php echo esc_html($fieldLabel); ?></span><input type="text" name="<?php echo esc_attr(Settings::OPTION_MEMBERSHIP_TIERS . '[' . $index . '][' . $field . ']'); ?>" value="<?php echo esc_attr((string) ($tierRow[$field] ?? '')); ?>"></label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>
        </section>

        <details class="coffeepos-settings__card coffeepos-settings__advanced">
            <summary><strong><?php esc_html_e('Advanced', 'coffeepos'); ?></strong><span><?php esc_html_e('Routing, data portability and diagnostics', 'coffeepos'); ?></span></summary>
            <div class="coffeepos-settings__advanced-content">
                <div class="coffeepos-settings__grid">
                    <label class="coffeepos-settings__field"><span><?php esc_html_e('POS base URL', 'coffeepos'); ?></span><input type="text" pattern="[a-z0-9-]+" maxlength="40" name="<?php echo esc_attr(Settings::OPTION_POS_BASE_SLUG); ?>" value="<?php echo esc_attr((string) $settingValue(Settings::OPTION_POS_BASE_SLUG)); ?>"><small><?php echo esc_html(home_url('/')); ?><strong data-component="pos-slug-preview"><?php echo esc_html(Settings::getPosBaseSlug()); ?></strong>/</small></label>
                    <label class="coffeepos-settings__check"><input type="hidden" name="<?php echo esc_attr(Settings::OPTION_UNINSTALL_DELETE_DATA); ?>" value="0"><input type="checkbox" name="<?php echo esc_attr(Settings::OPTION_UNINSTALL_DELETE_DATA); ?>" value="1" <?php checked(! empty($settingValue(Settings::OPTION_UNINSTALL_DELETE_DATA))); ?>><span><?php esc_html_e('Delete CoffeePOS data when uninstalling the plugin', 'coffeepos'); ?></span></label>
                </div>
                <div class="coffeepos-settings__import-export">
                    <button type="submit" class="coffeepos-btn" name="settings_action" value="export" formnovalidate><?php esc_html_e('Export settings', 'coffeepos'); ?></button>
                    <label class="coffeepos-settings__field"><span><?php esc_html_e('Import settings JSON', 'coffeepos'); ?></span><textarea name="settings_import_json" rows="5" placeholder='{"schema_version":1,"settings":{...}}'></textarea></label>
                    <button type="submit" class="coffeepos-btn" name="settings_action" value="import" formnovalidate><?php esc_html_e('Import settings', 'coffeepos'); ?></button>
                </div>
                <div class="coffeepos-settings__diagnostics"><h3><?php esc_html_e('System diagnostics', 'coffeepos'); ?></h3><dl><?php foreach ($diagnostics as $label => $value) : ?><div><dt><?php echo esc_html((string) $label); ?></dt><dd><?php echo esc_html((string) $value); ?></dd></div><?php endforeach; ?></dl></div>
            </div>
        </details>

        <div class="coffeepos-settings__actions"><button class="coffeepos-btn coffeepos-btn-primary" type="submit"><?php esc_html_e('Save settings', 'coffeepos'); ?></button></div>
    </form>
</section>
