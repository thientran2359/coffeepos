<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Assets;

use CoffeePOS\Infrastructure\Settings\Settings;

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
        $pairingInput = isset($_GET['pos_session_id'])
            ? sanitize_text_field(wp_unslash((string) $_GET['pos_session_id']))
            : '';
        $pairingValid = preg_match('/^[a-zA-Z0-9\-]{16,64}$/', $pairingInput) === 1;

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

        wp_register_script('coffeepos-sync-protocol', COFFEEPOS_URL . 'assets/js/sync/protocol.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-sync-channel', COFFEEPOS_URL . 'assets/js/sync/channel.js', ['coffeepos-sync-protocol'], $version, true);

        if ($screen === 'cashier') {
            $this->registerCashierScripts($version);
            $appDependencies[] = 'coffeepos-screen-cashier';
        }

        if ($screen === 'customer') {
            $this->registerCustomerScripts($version);
            $appDependencies[] = 'coffeepos-screen-customer';
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
            'currencyDecimals' => function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2,
            'customerDisplayUrl' => esc_url_raw(home_url('/' . trim(Settings::getPosBaseSlug(), '/') . '/customer/')),
            'posSessionId' => $screen === 'customer' && $pairingValid ? $pairingInput : '',
            'pairingState' => $screen === 'customer' ? ($pairingValid ? 'paired' : ($pairingInput === '' ? 'missing' : 'invalid')) : '',
            'i18n' => [
                'addItem' => __('Add item', 'coffeepos'),
                'editItem' => __('Edit item', 'coffeepos'),
                'addToCart' => __('Add to cart', 'coffeepos'),
                'updateItem' => __('Update item', 'coffeepos'),
                'saving' => __('Saving...', 'coffeepos'),
                'inStock' => __('In stock', 'coffeepos'),
                'outOfStock' => __('Out of stock', 'coffeepos'),
                'optionsAvailable' => __('Options available', 'coffeepos'),
                'productLoadError' => __('Product could not be loaded.', 'coffeepos'),
                'variationUnavailable' => __('This variation is unavailable.', 'coffeepos'),
                'cartUpdateError' => __('The cart could not be updated.', 'coffeepos'),
            ],
        ]);

        wp_enqueue_script('coffeepos-app');
    }

    private function registerCashierScripts(string $version): void
    {
        wp_register_script('coffeepos-api-client', COFFEEPOS_URL . 'assets/js/api/client.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-state-cashier', COFFEEPOS_URL . 'assets/js/state/cashier-store.js', ['coffeepos-core-app'], $version, true);
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

        wp_register_script('coffeepos-component-catalog-renderer', COFFEEPOS_URL . 'assets/js/components/catalog-renderer.js', ['coffeepos-ui-template-renderer'], $version, true);
        wp_register_script('coffeepos-component-cart-panel', COFFEEPOS_URL . 'assets/js/components/cart-panel.js', ['coffeepos-ui-template-renderer'], $version, true);
        wp_register_script('coffeepos-component-product-modal', COFFEEPOS_URL . 'assets/js/components/product-modal.js', ['coffeepos-api-client', 'coffeepos-ui-modal', 'coffeepos-ui-template-renderer'], $version, true);
        wp_register_script('coffeepos-component-cart-context', COFFEEPOS_URL . 'assets/js/components/cart-context.js', ['coffeepos-api-client', 'coffeepos-ui-modal', 'coffeepos-ui-template-renderer'], $version, true);
        wp_register_script('coffeepos-component-coupon-selector', COFFEEPOS_URL . 'assets/js/components/coupon-selector.js', ['coffeepos-api-client', 'coffeepos-ui-template-renderer'], $version, true);
        wp_register_script('coffeepos-component-checkout', COFFEEPOS_URL . 'assets/js/components/checkout.js', ['coffeepos-api-client', 'coffeepos-ui-template-renderer'], $version, true);
        wp_register_script('coffeepos-component-cashier-sync', COFFEEPOS_URL . 'assets/js/components/cashier-sync.js', ['coffeepos-sync-channel'], $version, true);

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
                'coffeepos-api-client',
                'coffeepos-state-cashier',
                'coffeepos-ui-modal',
                'coffeepos-ui-toast',
                'coffeepos-component-catalog-renderer',
                'coffeepos-component-category-nav',
                'coffeepos-component-product-search',
                'coffeepos-component-product-card',
                'coffeepos-component-cart-panel',
                'coffeepos-component-product-modal',
                'coffeepos-component-cart-context',
                'coffeepos-component-coupon-selector',
                'coffeepos-component-checkout',
                'coffeepos-component-cashier-sync',
                'coffeepos-component-order-type',
            ],
            $version,
            true
        );
    }

    private function registerCustomerScripts(string $version): void
    {
        wp_register_script('coffeepos-customer-api-client', COFFEEPOS_URL . 'assets/js/api/client.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-customer-template-renderer', COFFEEPOS_URL . 'assets/js/ui/template-renderer.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-state-customer', COFFEEPOS_URL . 'assets/js/state/customer-display-store.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-component-customer-catalog', COFFEEPOS_URL . 'assets/js/components/customer-catalog.js', ['coffeepos-customer-template-renderer'], $version, true);
        wp_register_script('coffeepos-component-customer-cart', COFFEEPOS_URL . 'assets/js/components/customer-cart.js', ['coffeepos-customer-template-renderer'], $version, true);
        wp_register_script('coffeepos-component-customer-payment', COFFEEPOS_URL . 'assets/js/components/customer-payment.js', ['coffeepos-sync-protocol'], $version, true);
        wp_register_script('coffeepos-screen-customer', COFFEEPOS_URL . 'assets/js/screens/customer.js', [
            'coffeepos-customer-api-client',
            'coffeepos-customer-template-renderer',
            'coffeepos-state-customer',
            'coffeepos-sync-channel',
            'coffeepos-component-customer-catalog',
            'coffeepos-component-customer-cart',
            'coffeepos-component-customer-payment',
        ], $version, true);
    }
}
