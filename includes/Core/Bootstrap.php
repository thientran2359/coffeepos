<?php

declare(strict_types=1);

namespace CoffeePOS\Core;

use CoffeePOS\Admin\AdminBootstrap;
use CoffeePOS\Infrastructure\Assets\AssetLoader;
use CoffeePOS\Infrastructure\Database\Migrator;
use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\POS\Router;
use CoffeePOS\REST\RouteRegistrar;
use CoffeePOS\Support\Capabilities;

final class Bootstrap
{
    private Environment $environment;

    private Settings $settings;

    private Migrator $migrator;

    private RouteRegistrar $routeRegistrar;

    private Router $router;

    private AssetLoader $assetLoader;

    private AdminBootstrap $adminBootstrap;

    public function __construct(
        ?Environment $environment = null,
        ?Settings $settings = null,
        ?Migrator $migrator = null,
        ?RouteRegistrar $routeRegistrar = null,
        ?Router $router = null,
        ?AssetLoader $assetLoader = null,
        ?AdminBootstrap $adminBootstrap = null
    ) {
        $this->environment = $environment ?? new Environment();
        $this->settings = $settings ?? new Settings();
        $this->migrator = $migrator ?? new Migrator();
        $this->routeRegistrar = $routeRegistrar ?? new RouteRegistrar();
        $this->router = $router ?? new Router();
        $this->assetLoader = $assetLoader ?? new AssetLoader();
        $this->adminBootstrap = $adminBootstrap ?? new AdminBootstrap($this->environment, $this->migrator);
    }

    public function run(): void
    {
        if (! $this->environment->isWordPressLoaded()) {
            return;
        }

        if (! $this->environment->isPhpVersionSupported()) {
            $this->notice(
                sprintf(
                    /* translators: %s: minimum PHP version. */
                    __('CoffeePOS requires PHP %s or higher.', 'coffeepos'),
                    $this->environment->minimumPhpVersion()
                )
            );

            return;
        }

        $this->settings->register();
        $this->adminBootstrap->register();

        if (! $this->environment->isWooCommerceAvailable()) {
            $this->notice(__('CoffeePOS requires WooCommerce to be installed and active.', 'coffeepos'));

            return;
        }

        if (! $this->environment->isWooCommerceVersionSupported()) {
            $this->notice(
                sprintf(
                    /* translators: %s: minimum WooCommerce version. */
                    __('CoffeePOS requires WooCommerce %s or higher.', 'coffeepos'),
                    $this->environment->minimumWooCommerceVersion()
                )
            );

            return;
        }

        add_action('init', [Capabilities::class, 'register'], 5);
        add_action('init', [$this->migrator, 'maybeMigrate'], 6);

        $this->routeRegistrar->register();
        $this->router->register();
        $this->assetLoader->register();
    }

    private function notice(string $message): void
    {
        add_action('admin_notices', static function () use ($message): void {
            if (! current_user_can('activate_plugins')) {
                return;
            }

            echo '<div class="notice notice-error"><p>';
            echo esc_html($message);
            echo '</p></div>';
        });
    }
}
