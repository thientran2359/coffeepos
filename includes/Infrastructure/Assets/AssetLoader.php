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

        wp_register_script(
            'coffeepos-core-config',
            COFFEEPOS_URL . 'assets/js/core/config.js',
            ['coffeepos-core-app'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-core-state',
            COFFEEPOS_URL . 'assets/js/core/state.js',
            ['coffeepos-core-app'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-core-http',
            COFFEEPOS_URL . 'assets/js/core/http.js',
            ['coffeepos-core-config'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-ui-toast',
            COFFEEPOS_URL . 'assets/js/ui/toast.js',
            ['coffeepos-core-app'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-ui-modal',
            COFFEEPOS_URL . 'assets/js/ui/modal.js',
            ['coffeepos-core-state'],
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
            'coffeepos-component-search',
            COFFEEPOS_URL . 'assets/js/components/search.js',
            ['coffeepos-core-state'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-component-order-type',
            COFFEEPOS_URL . 'assets/js/components/order-type.js',
            ['coffeepos-core-state'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-component-cart-panel',
            COFFEEPOS_URL . 'assets/js/components/cart-panel.js',
            ['coffeepos-core-state'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-screen-cashier',
            COFFEEPOS_URL . 'assets/js/screens/cashier.js',
            [
                'coffeepos-ui-toast',
                'coffeepos-ui-modal',
                'coffeepos-component-category-nav',
                'coffeepos-component-search',
                'coffeepos-component-order-type',
                'coffeepos-component-cart-panel',
                'coffeepos-core-http',
            ],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-core-bootstrap',
            COFFEEPOS_URL . 'assets/js/core/bootstrap.js',
            ['coffeepos-core-config', 'coffeepos-screen-cashier'],
            $version,
            true
        );

        wp_register_script(
            'coffeepos-app',
            COFFEEPOS_URL . 'assets/js/app.js',
            ['coffeepos-core-bootstrap'],
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
}
