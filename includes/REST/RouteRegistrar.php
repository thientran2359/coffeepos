<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

final class RouteRegistrar
{
    public const NAMESPACE = 'coffeepos/v1';

    private HealthController $healthController;

    private ProductController $productController;

    private StockController $stockController;

    private HeldCartController $heldCartController;

    private CartController $cartController;

    private CheckoutController $checkoutController;

    private OperationalOrderController $operationalOrderController;

    private ShiftController $shiftController;

    private OrderHistoryController $orderHistoryController;

    private ReportController $reportController;

    private static array $registeredRoutes = [];

    public function __construct(
        ?HealthController $healthController = null,
        ?ProductController $productController = null,
        ?StockController $stockController = null,
        ?HeldCartController $heldCartController = null,
        ?CartController $cartController = null,
        ?CheckoutController $checkoutController = null,
        ?OperationalOrderController $operationalOrderController = null,
        ?ShiftController $shiftController = null,
        ?OrderHistoryController $orderHistoryController = null,
        ?ReportController $reportController = null
    )
    {
        $this->healthController = $healthController ?? new HealthController();
        $this->productController = $productController ?? new ProductController();
        $this->stockController = $stockController ?? new StockController();
        $this->heldCartController = $heldCartController ?? new HeldCartController();
        $this->cartController = $cartController ?? new CartController();
        $this->checkoutController = $checkoutController ?? new CheckoutController();
        $this->operationalOrderController = $operationalOrderController ?? new OperationalOrderController();
        $this->shiftController = $shiftController ?? new ShiftController();
        $this->orderHistoryController = $orderHistoryController ?? new OrderHistoryController();
        $this->reportController = $reportController ?? new ReportController();
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        $this->healthController->register(self::NAMESPACE);
        $this->productController->register(self::NAMESPACE);
        $this->stockController->register(self::NAMESPACE);
        $this->heldCartController->register(self::NAMESPACE);
        $this->cartController->register(self::NAMESPACE);
        $this->checkoutController->register(self::NAMESPACE);
        $this->operationalOrderController->register(self::NAMESPACE);
        $this->shiftController->register(self::NAMESPACE);
        $this->orderHistoryController->register(self::NAMESPACE);
        $this->reportController->register(self::NAMESPACE);

        self::$registeredRoutes = [
            self::NAMESPACE . '/health',
            self::NAMESPACE . '/catalog',
            self::NAMESPACE . '/products',
            self::NAMESPACE . '/products/(?P<id>\\d+)',
            self::NAMESPACE . '/products/(?P<id>\\d+)/variation',
            self::NAMESPACE . '/products/(?P<id>\\d+)/stock',
            self::NAMESPACE . '/held-carts',
            self::NAMESPACE . '/held-carts/(?P<id>\\d+)',
            self::NAMESPACE . '/held-carts/(?P<id>\\d+)/resume',
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
            self::NAMESPACE . '/cart/order-note',
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
            self::NAMESPACE . '/orders',
            self::NAMESPACE . '/orders/(?P<id>\\d+)',
            self::NAMESPACE . '/orders/(?P<id>\\d+)/refund',
            self::NAMESPACE . '/orders/(?P<id>\\d+)/reorder',
            self::NAMESPACE . '/reports/sales',
            self::NAMESPACE . '/reports/sales/export',
        ];
    }

    public static function registeredRoutes(): array
    {
        return self::$registeredRoutes;
    }
}
