<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

final class RouteRegistrar
{
    public const NAMESPACE = 'coffeepos/v1';

    private HealthController $healthController;

    private static array $registeredRoutes = [];

    public function __construct(?HealthController $healthController = null)
    {
        $this->healthController = $healthController ?? new HealthController();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $this->healthController->register(self::NAMESPACE);

        self::$registeredRoutes = [
            self::NAMESPACE . '/health',
        ];
    }

    public static function registeredRoutes(): array
    {
        return self::$registeredRoutes;
    }
}
