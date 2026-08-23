<?php

declare(strict_types=1);

namespace CoffeePOS\Admin;

use CoffeePOS\Core\Environment;
use CoffeePOS\Infrastructure\Database\Migrator;
use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\REST\RouteRegistrar;
use CoffeePOS\Support\Capabilities;

final class AdminBootstrap
{
    private Environment $environment;

    private Migrator $migrator;

    public function __construct(?Environment $environment = null, ?Migrator $migrator = null)
    {
        $this->environment = $environment ?? new Environment();
        $this->migrator = $migrator ?? new Migrator();
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('CoffeePOS', 'coffeepos'),
            __('CoffeePOS', 'coffeepos'),
            Capabilities::MANAGE_WOOCOMMERCE,
            'coffeepos',
            [$this, 'renderOverview']
        );
    }

    public function renderOverview(): void
    {
        if (! current_user_can(Capabilities::MANAGE_WOOCOMMERCE)) {
            wp_die(
                esc_html__('You are not allowed to access this page.', 'coffeepos'),
                esc_html__('Forbidden', 'coffeepos'),
                ['response' => 403]
            );
        }

        $diagnostics = [
            'plugin_version' => COFFEEPOS_VERSION,
            'woocommerce_detected' => $this->environment->isWooCommerceAvailable(),
            'db' => $this->migrator->diagnostics(),
            'routes' => RouteRegistrar::registeredRoutes(),
        ];

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('CoffeePOS Foundation', 'coffeepos') . '</h1>';
        echo '<p>' . esc_html__('Phase-00 foundation is active. Business features are intentionally not implemented in this phase.', 'coffeepos') . '</p>';
        echo '<h2>' . esc_html__('Diagnostics', 'coffeepos') . '</h2>';
        echo '<pre>' . esc_html(wp_json_encode($diagnostics, JSON_PRETTY_PRINT)) . '</pre>';
        echo '<hr><h2>' . esc_html__('VietQR checkout', 'coffeepos') . '</h2>';
        echo '<p>' . esc_html__('Used to generate the pre-order QR shown to Cashier and Customer Display. No order is created until the cashier confirms receipt.', 'coffeepos') . '</p>';
        echo '<form method="post" action="options.php">';
        settings_fields(Settings::GROUP);
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->textSetting(Settings::OPTION_VIETQR_BANK_ID, __('Bank ID', 'coffeepos'), __('Use the bank identifier accepted by vietqr.app.', 'coffeepos'));
        $this->textSetting(Settings::OPTION_VIETQR_ACCOUNT_NUMBER, __('Account number', 'coffeepos'), __('Letters, numbers, spaces and separators are sanitized before QR generation.', 'coffeepos'));
        $this->textSetting(Settings::OPTION_VIETQR_ACCOUNT_NAME, __('Account holder', 'coffeepos'), __('Displayed as the VietQR holder value.', 'coffeepos'));
        echo '</tbody></table>';
        submit_button(__('Save VietQR settings', 'coffeepos'));
        echo '</form>';
        echo '<hr><h2>' . esc_html__('Operational screens', 'coffeepos') . '</h2>';
        echo '<form method="post" action="options.php">';
        settings_fields(Settings::GROUP);
        echo '<table class="form-table" role="presentation"><tbody>';
        $this->numberSetting(Settings::OPTION_KDS_POLL_INTERVAL, __('KDS polling interval (ms)', 'coffeepos'));
        $this->numberSetting(Settings::OPTION_ORDER_QUEUE_POLL_INTERVAL, __('Order Queue polling interval (ms)', 'coffeepos'));
        echo '</tbody></table>';
        submit_button(__('Save operational settings', 'coffeepos'));
        echo '</form>';
        echo '</div>';
    }

    private function textSetting(string $option, string $label, string $description): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($option) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input class="regular-text" type="text" id="' . esc_attr($option) . '" name="' . esc_attr($option) . '" value="' . esc_attr((string) Settings::get($option)) . '">';
        echo '<p class="description">' . esc_html($description) . '</p></td></tr>';
    }

    private function numberSetting(string $option, string $label): void
    {
        echo '<tr><th scope="row"><label for="' . esc_attr($option) . '">' . esc_html($label) . '</label></th><td>';
        echo '<input type="number" min="3000" max="60000" step="1000" id="' . esc_attr($option) . '" name="' . esc_attr($option) . '" value="' . esc_attr((string) Settings::get($option)) . '">';
        echo '<p class="description">' . esc_html__('Allowed range: 3000–60000 ms.', 'coffeepos') . '</p></td></tr>';
    }
}
