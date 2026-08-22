<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

final class RouteRegistrar
{
    public const NAMESPACE = 'coffeepos/v1';

    private HealthController $healthController;

    private ProductController $productController;

    private CartController $cartController;

    private static array $registeredRoutes = [];

    public function __construct(
        ?HealthController $healthController = null,
        ?ProductController $productController = null,
        ?CartController $cartController = null
    )
    {
        $this->healthController = $healthController ?? new HealthController();
        $this->productController = $productController ?? new ProductController();
        $this->cartController = $cartController ?? new CartController();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $this->healthController->register(self::NAMESPACE);
        $this->productController->register(self::NAMESPACE);
        $this->cartController->register(self::NAMESPACE);

        self::$registeredRoutes = [
            self::NAMESPACE . '/health',
            self::NAMESPACE . '/products',
            self::NAMESPACE . '/products/(?P<id>\\d+)',
            self::NAMESPACE . '/categories',
            self::NAMESPACE . '/cart/validate',
            self::NAMESPACE . '/cart/items',
            self::NAMESPACE . '/cart/items/(?P<item_id>[a-f0-9]{64})',
            self::NAMESPACE . '/cart',
        ];
    }

    public static function registeredRoutes(): array
    {
        return self::$registeredRoutes;
    }
}
