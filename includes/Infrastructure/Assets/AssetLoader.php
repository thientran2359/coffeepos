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

        wp_register_style(
            'coffeepos-app',
            COFFEEPOS_URL . 'assets/css/app.css',
            [],
            COFFEEPOS_VERSION
        );
        wp_enqueue_style('coffeepos-app');

        wp_register_script(
            'coffeepos-app',
            COFFEEPOS_URL . 'assets/js/app.js',
            [],
            COFFEEPOS_VERSION,
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
