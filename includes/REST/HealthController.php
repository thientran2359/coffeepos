<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Core\Environment;
use CoffeePOS\Infrastructure\Database\Migrator;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class HealthController
{
    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/health', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'health'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
        ]);
    }

    public function permissionCheck()
    {
        if (Capabilities::currentUserCanAccessPos()) {
            return true;
        }

        return ErrorFactory::forbidden(
            'coffeepos_rest_forbidden',
            __('You are not allowed to access CoffeePOS REST endpoints.', 'coffeepos')
        );
    }

    public function health(WP_REST_Request $request): WP_REST_Response
    {
        $environment = new Environment();

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'plugin_version' => COFFEEPOS_VERSION,
                'woocommerce_detected' => $environment->isWooCommerceAvailable(),
                'db_schema_version' => Migrator::SCHEMA_VERSION,
                'db_installed_version' => (string) get_option(Migrator::OPTION_DB_VERSION, '0.0.0'),
                'registered_routes' => RouteRegistrar::registeredRoutes(),
            ],
        ]);
    }
}
