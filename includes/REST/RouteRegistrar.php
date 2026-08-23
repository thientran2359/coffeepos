<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

final class RouteRegistrar
{
    public const NAMESPACE = 'coffeepos/v1';

    private HealthController $healthController;

    private ProductController $productController;

    private CartController $cartController;

    private CheckoutController $checkoutController;

    private OperationalOrderController $operationalOrderController;

    private ShiftController $shiftController;

    private static array $registeredRoutes = [];

    public function __construct(
        ?HealthController $healthController = null,
        ?ProductController $productController = null,
        ?CartController $cartController = null,
        ?CheckoutController $checkoutController = null,
        ?OperationalOrderController $operationalOrderController = null,
        ?ShiftController $shiftController = null
    )
    {
        $this->healthController = $healthController ?? new HealthController();
        $this->productController = $productController ?? new ProductController();
        $this->cartController = $cartController ?? new CartController();
        $this->checkoutController = $checkoutController ?? new CheckoutController();
        $this->operationalOrderController = $operationalOrderController ?? new OperationalOrderController();
        $this->shiftController = $shiftController ?? new ShiftController();
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
        $this->checkoutController->register(self::NAMESPACE);
        $this->operationalOrderController->register(self::NAMESPACE);
        $this->shiftController->register(self::NAMESPACE);

        self::$registeredRoutes = [
            self::NAMESPACE . '/health',
            self::NAMESPACE . '/catalog',
            self::NAMESPACE . '/products',
            self::NAMESPACE . '/products/(?P<id>\\d+)',
            self::NAMESPACE . '/products/(?P<id>\\d+)/variation',
            self::NAMESPACE . '/categories',
            self::NAMESPACE . '/cart/session',
            self::NAMESPACE . '/cart',
            self::NAMESPACE . '/cart/validate',
            self::NAMESPACE . '/cart/items',
            self::NAMESPACE . '/cart/items/(?P<item_id>[a-f0-9]{64})',
            self::NAMESPACE . '/customers/lookup',
            self::NAMESPACE . '/customers',
            self::NAMESPACE . '/tables',
            self::NAMESPACE . '/cart/customer',
            self::NAMESPACE . '/cart/service-context',
            self::NAMESPACE . '/coupons/applicable',
            self::NAMESPACE . '/cart/coupon',
            self::NAMESPACE . '/orders/checkout',
            self::NAMESPACE . '/orders/(?P<id>\\d+)/payment',
            self::NAMESPACE . '/orders/(?P<id>\\d+)/receipt',
            self::NAMESPACE . '/kds/orders',
            self::NAMESPACE . '/kds/orders/(?P<id>\\d+)/transition',
            self::NAMESPACE . '/order-queue/orders',
            self::NAMESPACE . '/orders/(?P<id>\\d+)/complete',
            self::NAMESPACE . '/orders/(?P<id>\\d+)/cancel',
            self::NAMESPACE . '/shifts/current',
            self::NAMESPACE . '/shifts/open',
            self::NAMESPACE . '/shifts/(?P<id>\\d+)/close',
            self::NAMESPACE . '/shifts/history',
        ];
    }

    public static function registeredRoutes(): array
    {
        return self::$registeredRoutes;
    }
}
