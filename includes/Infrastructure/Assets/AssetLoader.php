<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Assets;

use CoffeePOS\Infrastructure\Settings\Settings;

use CoffeePOS\POS\Router;
use CoffeePOS\POS\MemberPortalRouter;
use CoffeePOS\REST\RouteRegistrar;

final class AssetLoader
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueuePosAssets']);
        add_action('wp_enqueue_scripts', [$this, 'enqueueMemberPortalAssets'], PHP_INT_MAX);
        add_action('wp_footer', [$this, 'isolateMemberPortalFooterScripts'], 19);
    }

    public function enqueueMemberPortalAssets(): void
    {
        if (! MemberPortalRouter::isPortalRequest()) {
            return;
        }

        $this->clearFrontendAssetQueues();
        $version = COFFEEPOS_VERSION;
        $fontDependencies = [];
        $googleFontUrl = Settings::getGoogleFontStylesheetUrl();
        if ($googleFontUrl !== '') {
            wp_register_style('coffeepos-google-font', $googleFontUrl, [], null);
            $fontDependencies[] = 'coffeepos-google-font';
        }
        $this->registerStyle('coffeepos-core', 'core.css', $fontDependencies, $version);
        $this->registerStyle('coffeepos-base', 'base.css', ['coffeepos-core'], $version);
        $this->registerStyle('coffeepos-components', 'components.css', ['coffeepos-base'], $version);
        $this->registerStyle('coffeepos-screen-member-account', 'screens/member-account.css', ['coffeepos-components'], $version);
        wp_enqueue_style('coffeepos-screen-member-account');
        wp_add_inline_style('coffeepos-screen-member-account', sprintf(
            ':root{--coffeepos-primary:%s;--coffeepos-primary-dark:%s;--coffeepos-font-family:%s;}',
            Settings::getBrandColor(),
            Settings::getBrandDarkColor(),
            Settings::getFontFamilyCss()
        ));

        wp_register_script('coffeepos-core-app', COFFEEPOS_URL . 'assets/js/core/app.js', [], $version, true);
        wp_register_script('coffeepos-member-template-renderer', COFFEEPOS_URL . 'assets/js/ui/template-renderer.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-screen-member-account', COFFEEPOS_URL . 'assets/js/screens/member-account.js', ['coffeepos-member-template-renderer'], $version, true);
        wp_localize_script('coffeepos-screen-member-account', 'CoffeePOSMemberConfig', [
            'restBase' => esc_url_raw(rest_url(RouteRegistrar::NAMESPACE . '/member/')),
            'storeName' => Settings::getStoreName(),
            'i18n' => [
                'requestFailed' => __('The request could not be completed.', 'coffeepos'),
                'sessionExpired' => __('Your member session has expired. Please sign in again.', 'coffeepos'),
                'emptyOrders' => __('You do not have any CoffeePOS orders yet.', 'coffeepos'),
            ],
        ]);
        wp_enqueue_script('coffeepos-screen-member-account');
    }

    public function isolateMemberPortalFooterScripts(): void
    {
        if (! MemberPortalRouter::isPortalRequest()) {
            return;
        }

        global $wp_scripts;
        if (is_object($wp_scripts)) {
            $wp_scripts->queue = [];
        }
        wp_enqueue_script('coffeepos-screen-member-account');
    }

    private function clearFrontendAssetQueues(): void
    {
        global $wp_styles, $wp_scripts;
        if (is_object($wp_styles)) {
            $wp_styles->queue = [];
        }
        if (is_object($wp_scripts)) {
            $wp_scripts->queue = [];
        }
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

        $this->enqueuePosStyles($screen, $version);

        wp_register_script(
            'coffeepos-core-app',
            COFFEEPOS_URL . 'assets/js/core/app.js',
            ['wp-i18n'],
            $version,
            true
        );

        $appDependencies = ['coffeepos-core-app'];

        wp_register_script('coffeepos-sync-protocol', COFFEEPOS_URL . 'assets/js/sync/protocol.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-sync-channel', COFFEEPOS_URL . 'assets/js/sync/channel.js', ['coffeepos-sync-protocol'], $version, true);
        wp_register_script('coffeepos-sync-display-pairing', COFFEEPOS_URL . 'assets/js/sync/display-pairing.js', ['coffeepos-sync-protocol'], $version, true);

        if ($screen === 'cashier') {
            $this->registerCashierScripts($version);
            $appDependencies[] = 'coffeepos-screen-cashier';
        }

        if ($screen === 'customer') {
            $this->registerCustomerScripts($version);
            $appDependencies[] = 'coffeepos-screen-customer';
        }

        if ($screen === 'kds') {
            $this->registerKdsScripts($version);
            $appDependencies[] = 'coffeepos-screen-kds';
        }

        if ($screen === 'order-queue') {
            $this->registerOrderQueueScripts($version);
            $appDependencies[] = 'coffeepos-screen-order-queue';
        }

        if ($screen === 'shifts') {
            $this->registerShiftScripts($version);
            $appDependencies[] = 'coffeepos-screen-shifts';
        }

        if ($screen === 'order-history') {
            $this->registerOrderHistoryScripts($version);
            $appDependencies[] = 'coffeepos-screen-order-history';
        }

        if ($screen === 'reports') {
            $this->registerReportScripts($version);
            $appDependencies[] = 'coffeepos-screen-reports';
        }

        if ($screen === 'members') {
            $this->registerMembersScripts($version);
            $appDependencies[] = 'coffeepos-screen-members';
        }

        if ($screen === 'settings') {
            if (current_user_can('upload_files') && function_exists('wp_enqueue_media')) {
                wp_enqueue_media();
            }
            $this->registerSettingsScripts($version);
            $appDependencies[] = 'coffeepos-screen-settings';
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
            'displayPairingScope' => substr(hash_hmac('sha256', (string) get_current_user_id(), wp_salt('auth')), 0, 32),
            'posSessionId' => $screen === 'customer' && $pairingValid ? $pairingInput : '',
            'pairingState' => $screen === 'customer' ? ($pairingValid ? 'paired' : ($pairingInput === '' ? 'missing' : 'invalid')) : '',
            'pollIntervalMs' => $screen === 'kds' ? Settings::getKdsPollInterval() : ($screen === 'order-queue' ? Settings::getOrderQueuePollInterval() : 0),
            'storeName' => Settings::getStoreName(),
            'defaultOrderType' => (string) Settings::get(Settings::OPTION_DEFAULT_ORDER_TYPE),
            'requireDineInTable' => (bool) Settings::get(Settings::OPTION_REQUIRE_DINE_IN_TABLE),
            'shiftsEnabled' => (bool) Settings::get(Settings::OPTION_SHIFTS_ENABLED),
            'requireOpenShift' => (bool) Settings::get(Settings::OPTION_SHIFTS_ENABLED) && (bool) Settings::get(Settings::OPTION_REQUIRE_OPEN_SHIFT),
            'paymentMethods' => Settings::enabledPaymentMethods(),
            'receiptPaperWidth' => (string) Settings::get(Settings::OPTION_RECEIPT_PAPER_WIDTH),
            'autoPrintReceipt' => (bool) Settings::get(Settings::OPTION_RECEIPT_AUTO_PRINT),
            'membershipEnabled' => (bool) Settings::get(Settings::OPTION_MEMBERSHIP_ENABLED),
            'memberCreateEnabled' => (bool) Settings::get(Settings::OPTION_MEMBER_CREATE_ENABLED),
            'memberRequiredFields' => Settings::memberRequiredFields(),
            'kdsSoundEnabled' => (bool) Settings::get(Settings::OPTION_KDS_SOUND_ENABLED),
            'canRefundOrders' => current_user_can(\CoffeePOS\Support\Capabilities::REFUND_ORDERS),
            'canCancelOrders' => current_user_can(\CoffeePOS\Support\Capabilities::CANCEL_ORDERS),
            'canReorderOrders' => current_user_can(\CoffeePOS\Support\Capabilities::REORDER_ORDERS),
            'canReprintReceipts' => current_user_can(\CoffeePOS\Support\Capabilities::REPRINT_RECEIPTS),
            'canManageStock' => current_user_can(\CoffeePOS\Support\Capabilities::MANAGE_STOCK),
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

        $this->setScriptTranslations();
        wp_enqueue_script('coffeepos-app');
    }

    private function enqueuePosStyles(?string $screen, string $version): void
    {
        $fontDependencies = [];
        $googleFontUrl = Settings::getGoogleFontStylesheetUrl();
        if ($googleFontUrl !== '') {
            wp_register_style('coffeepos-google-font', $googleFontUrl, [], null);
            $fontDependencies[] = 'coffeepos-google-font';
        }
        $this->registerStyle('coffeepos-core', 'core.css', $fontDependencies, $version);
        $this->registerStyle('coffeepos-base', 'base.css', ['coffeepos-core'], $version);

        if ($screen === 'customer') {
            $this->registerStyle(
                'coffeepos-screen-customer',
                'screens/customer-display.css',
                ['coffeepos-base'],
                $version
            );
            wp_enqueue_style('coffeepos-screen-customer');
            $this->enqueueAppearanceStyles('coffeepos-screen-customer');

            return;
        }

        $this->registerStyle('coffeepos-components', 'components.css', ['coffeepos-base'], $version);

        $screenFiles = [
            'entry' => 'screens/login.css',
            'cashier' => 'screens/cashier.css',
            'kds' => 'screens/kds.css',
            'order-queue' => 'screens/order-queue.css',
            'shifts' => 'screens/shifts.css',
            'order-history' => 'screens/order-history.css',
            'reports' => 'screens/reports.css',
            'members' => 'screens/members.css',
            'settings' => 'screens/settings.css',
        ];

        if ($screen === null || ! isset($screenFiles[$screen])) {
            wp_enqueue_style('coffeepos-components');

            return;
        }

        $dependencies = ['coffeepos-components'];
        $operationsScreens = ['kds', 'order-queue', 'shifts', 'order-history', 'reports', 'members', 'settings'];
        if (in_array($screen, $operationsScreens, true)) {
            $this->registerStyle('coffeepos-operations', 'operations.css', $dependencies, $version);
            $dependencies = ['coffeepos-operations'];
        }

        $screenHandle = 'coffeepos-screen-' . $screen;
        $this->registerStyle($screenHandle, $screenFiles[$screen], $dependencies, $version);
        $lastHandle = $screenHandle;

        $managementScreens = ['shifts', 'order-history', 'reports', 'members', 'settings'];
        if (in_array($screen, $managementScreens, true)) {
            $this->registerStyle('coffeepos-management', 'management.css', [$screenHandle], $version);
            $lastHandle = 'coffeepos-management';
        }

        if (in_array($screen, ['cashier', 'order-queue', 'order-history'], true)) {
            $this->registerStyle('coffeepos-print', 'print.css', [$lastHandle], $version, 'all');
            $lastHandle = 'coffeepos-print';
        }

        wp_enqueue_style($lastHandle);
        $this->enqueueAppearanceStyles($lastHandle);
    }

    private function enqueueAppearanceStyles(string $handle): void
    {
        $appearanceCss = sprintf(
            ':root{--coffeepos-primary:%s;--coffeepos-primary-dark:%s;--coffeepos-font-family:%s;}',
            Settings::getBrandColor(),
            Settings::getBrandDarkColor(),
            Settings::getFontFamilyCss()
        );
        $customCss = Settings::getCustomCss();

        if ($customCss !== '') {
            $appearanceCss .= "\n" . $customCss;
        }

        wp_add_inline_style($handle, $appearanceCss);
    }

    private function registerStyle(string $handle, string $path, array $dependencies, string $version, string $media = 'all'): void
    {
        wp_register_style(
            $handle,
            COFFEEPOS_URL . 'assets/css/' . ltrim($path, '/'),
            $dependencies,
            $version,
            $media
        );
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
        wp_register_script('coffeepos-component-stock-modal', COFFEEPOS_URL . 'assets/js/components/stock-modal.js', ['coffeepos-api-client', 'coffeepos-ui-modal', 'coffeepos-ui-template-renderer'], $version, true);
        wp_register_script('coffeepos-component-held-carts', COFFEEPOS_URL . 'assets/js/components/held-carts.js', ['coffeepos-api-client', 'coffeepos-ui-modal', 'coffeepos-ui-template-renderer'], $version, true);
        wp_register_script('coffeepos-component-cart-context', COFFEEPOS_URL . 'assets/js/components/cart-context.js', ['coffeepos-api-client', 'coffeepos-ui-modal', 'coffeepos-ui-template-renderer'], $version, true);
        wp_register_script('coffeepos-component-coupon-selector', COFFEEPOS_URL . 'assets/js/components/coupon-selector.js', ['coffeepos-api-client', 'coffeepos-ui-template-renderer'], $version, true);
        $this->registerReceiptPrinter($version, 'coffeepos-ui-template-renderer');
        wp_register_script('coffeepos-component-checkout', COFFEEPOS_URL . 'assets/js/components/checkout.js', ['coffeepos-api-client', 'coffeepos-ui-template-renderer', 'coffeepos-component-receipt-printer'], $version, true);
        wp_register_script('coffeepos-component-cashier-sync', COFFEEPOS_URL . 'assets/js/components/cashier-sync.js', ['coffeepos-sync-channel', 'coffeepos-sync-display-pairing'], $version, true);

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
                'coffeepos-component-stock-modal',
                'coffeepos-component-held-carts',
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
            'coffeepos-sync-display-pairing',
            'coffeepos-component-customer-catalog',
            'coffeepos-component-customer-cart',
            'coffeepos-component-customer-payment',
        ], $version, true);
    }

    private function registerOperationsCommon(string $version): void
    {
        wp_register_script('coffeepos-operations-api-client', COFFEEPOS_URL . 'assets/js/api/client.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-operations-template-renderer', COFFEEPOS_URL . 'assets/js/ui/template-renderer.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-operations-toast', COFFEEPOS_URL . 'assets/js/ui/toast.js', ['coffeepos-operations-template-renderer'], $version, true);
        wp_register_script('coffeepos-operations-modal', COFFEEPOS_URL . 'assets/js/ui/modal.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-operations-polling', COFFEEPOS_URL . 'assets/js/core/polling-controller.js', ['coffeepos-core-app'], $version, true);
    }

    private function registerKdsScripts(string $version): void
    {
        $this->registerOperationsCommon($version);
        $this->registerOperationalOrderSorter($version);
        wp_register_script('coffeepos-state-kds', COFFEEPOS_URL . 'assets/js/state/kds-store.js', ['coffeepos-state-operational-order-sort'], $version, true);
        wp_register_script('coffeepos-component-kds-grid', COFFEEPOS_URL . 'assets/js/components/kds-order-grid.js', ['coffeepos-operations-template-renderer'], $version, true);
        wp_register_script('coffeepos-component-kds-timer', COFFEEPOS_URL . 'assets/js/components/kds-timer.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-component-kds-sound', COFFEEPOS_URL . 'assets/js/components/kds-sound.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-screen-kds', COFFEEPOS_URL . 'assets/js/screens/kds.js', ['coffeepos-operations-api-client', 'coffeepos-operations-toast', 'coffeepos-operations-polling', 'coffeepos-state-kds', 'coffeepos-component-kds-grid', 'coffeepos-component-kds-timer', 'coffeepos-component-kds-sound'], $version, true);
    }

    private function registerOrderQueueScripts(string $version): void
    {
        $this->registerOperationsCommon($version);
        $this->registerOperationalOrderSorter($version);
        wp_register_script('coffeepos-state-order-queue', COFFEEPOS_URL . 'assets/js/state/order-queue-store.js', ['coffeepos-state-operational-order-sort'], $version, true);
        wp_register_script('coffeepos-component-order-queue-list', COFFEEPOS_URL . 'assets/js/components/order-queue-list.js', ['coffeepos-operations-template-renderer'], $version, true);
        $this->registerReceiptPrinter($version, 'coffeepos-operations-template-renderer');
        wp_register_script('coffeepos-screen-order-queue', COFFEEPOS_URL . 'assets/js/screens/order-queue.js', ['coffeepos-operations-api-client', 'coffeepos-operations-toast', 'coffeepos-operations-modal', 'coffeepos-operations-polling', 'coffeepos-state-order-queue', 'coffeepos-component-order-queue-list', 'coffeepos-component-receipt-printer'], $version, true);
    }

    private function registerShiftScripts(string $version): void
    {
        wp_register_script('coffeepos-shifts-api-client', COFFEEPOS_URL . 'assets/js/api/client.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-screen-shifts', COFFEEPOS_URL . 'assets/js/screens/shifts.js', ['coffeepos-shifts-api-client'], $version, true);
    }

    private function registerOrderHistoryScripts(string $version): void
    {
        wp_register_script('coffeepos-history-api-client', COFFEEPOS_URL . 'assets/js/api/client.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-history-modal', COFFEEPOS_URL . 'assets/js/ui/modal.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-history-template-renderer', COFFEEPOS_URL . 'assets/js/ui/template-renderer.js', ['coffeepos-core-app'], $version, true);
        $this->registerReceiptPrinter($version, 'coffeepos-history-template-renderer');
        wp_register_script('coffeepos-screen-order-history', COFFEEPOS_URL . 'assets/js/screens/order-history.js', ['coffeepos-history-api-client', 'coffeepos-history-modal', 'coffeepos-component-receipt-printer'], $version, true);
    }

    private function registerReportScripts(string $version): void
    {
        wp_register_script('coffeepos-reports-api-client', COFFEEPOS_URL . 'assets/js/api/client.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-reports-template-renderer', COFFEEPOS_URL . 'assets/js/ui/template-renderer.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-screen-reports', COFFEEPOS_URL . 'assets/js/screens/reports.js', ['coffeepos-reports-api-client', 'coffeepos-reports-template-renderer'], $version, true);
    }

    private function registerSettingsScripts(string $version): void
    {
        wp_register_script('coffeepos-screen-settings', COFFEEPOS_URL . 'assets/js/screens/settings.js', ['coffeepos-core-app'], $version, true);
    }

    private function registerMembersScripts(string $version): void
    {
        wp_register_script('coffeepos-members-api-client', COFFEEPOS_URL . 'assets/js/api/client.js', ['coffeepos-core-app'], $version, true);
        wp_register_script('coffeepos-screen-members', COFFEEPOS_URL . 'assets/js/screens/members.js', ['coffeepos-members-api-client'], $version, true);
    }

    private function registerReceiptPrinter(string $version, string $rendererHandle): void
    {
        wp_register_script('coffeepos-component-receipt-printer', COFFEEPOS_URL . 'assets/js/components/receipt-printer.js', [$rendererHandle], $version, true);
    }

    private function registerOperationalOrderSorter(string $version): void
    {
        wp_register_script('coffeepos-state-operational-order-sort', COFFEEPOS_URL . 'assets/js/state/operational-order-sort.js', ['coffeepos-core-app'], $version, true);
    }

    private function setScriptTranslations(): void
    {
        $scripts = wp_scripts();

        foreach (array_keys($scripts->registered) as $handle) {
            if (strpos((string) $handle, 'coffeepos-') !== 0) {
                continue;
            }

            wp_set_script_translations((string) $handle, 'coffeepos', COFFEEPOS_PATH . 'languages');
        }
    }
}
