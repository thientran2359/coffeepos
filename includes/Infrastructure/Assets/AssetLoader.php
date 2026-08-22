<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Assets;

use CoffeePOS\POS\Router;
use CoffeePOS\REST\RouteRegistrar;

final class AssetLoader
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueuePosAssets']);
    }

    public function enqueuePosAssets(): void
    {
        if (! Router::isPosRequest()) {
            return;
        }

        $screen = Router::currentScreen();
        $version = COFFEEPOS_VERSION;

        wp_register_style(
            'coffeepos-app',
            COFFEEPOS_URL . 'assets/css/app.css',
            [],
            $version
        );
        wp_enqueue_style('coffeepos-app');

        wp_register_script(
            'coffeepos-core-app',
            COFFEEPOS_URL . 'assets/js/core/app.js',
            [],
            $version,
            true
        );

        $appDependencies = ['coffeepos-core-app'];

        if ($screen === 'cashier') {
            $this->registerCashierScripts($version);
            $appDependencies[] = 'coffeepos-screen-cashier';
        }

        wp_register_script(
            'coffeepos-app',
            COFFEEPOS_URL . 'assets/js/app.js',
            $appDependencies,
            $version,
            true
        );

        wp_localize_script('coffeepos-app', 'CoffeePOSConfig', [
            'screen' => $screen,
            'restBase' => esc_url_raw(rest_url(RouteRegistrar::NAMESPACE . '/')),
            'restNonce' => wp_create_nonce('wp_rest'),
        ]);

        wp_enqueue_script('coffeepos-app');
    }

    private function registerCashierScripts(string $version): void
    {
        wp_register_script(
            'coffeepos-ui-template-renderer',
            COFFEEPOS_URL . 'assets/js/ui/template-renderer.js',
            ['coffeepos-core-app'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-ui-modal',
            COFFEEPOS_URL . 'assets/js/ui/modal.js',
            ['coffeepos-core-app'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-ui-toast',
            COFFEEPOS_URL . 'assets/js/ui/toast.js',
            ['coffeepos-ui-template-renderer'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-component-category-nav',
            COFFEEPOS_URL . 'assets/js/components/category-nav.js',
            ['coffeepos-core-app'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-component-product-search',
            COFFEEPOS_URL . 'assets/js/components/product-search.js',
            ['coffeepos-core-app'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-component-product-card',
            COFFEEPOS_URL . 'assets/js/components/product-card.js',
            ['coffeepos-core-app'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-component-order-type',
            COFFEEPOS_URL . 'assets/js/components/order-type.js',
            ['coffeepos-core-app'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-screen-cashier',
            COFFEEPOS_URL . 'assets/js/screens/cashier.js',
            [
                'coffeepos-ui-modal',
                'coffeepos-ui-toast',
                'coffeepos-component-category-nav',
                'coffeepos-component-product-search',
                'coffeepos-component-product-card',
                'coffeepos-component-order-type',
            ],
            $version,
            true
        );
    }
}
