<?php

declare(strict_types=1);

namespace CoffeePOS\Admin;

use CoffeePOS\Core\Environment;
use CoffeePOS\Infrastructure\Database\Migrator;
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
        echo '</div>';
    }
}
