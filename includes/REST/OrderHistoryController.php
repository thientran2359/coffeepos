<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\Cart\CartService;
use CoffeePOS\Application\Cart\CartSessionService;
use CoffeePOS\Application\Cart\CartValidationService;
use CoffeePOS\Application\OrderHistory\OrderHistoryService;
use CoffeePOS\Application\Operations\OperationalOrderService;
use CoffeePOS\Application\Product\ProductConfigurationService;
use CoffeePOS\Application\Product\ProductService;
use CoffeePOS\Application\Product\VariationService;
use CoffeePOS\Infrastructure\Concurrency\MySqlLockProvider;
use CoffeePOS\Infrastructure\Settings\SettingsProductConfigurationProvider;
use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Integration\WooCommerce\WooCommerceCartSessionStore;
use CoffeePOS\Integration\WooCommerce\WooCommerceMoneyFormatter;
use CoffeePOS\Integration\WooCommerce\WooCommerceOperationalOrderGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceOrderHistoryGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceProductGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceStockGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceVariationGateway;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Server;

final class OrderHistoryController
{
    private OrderHistoryService $service;

    public function __construct(?OrderHistoryService $service = null)
    {
        $product = new ProductService(new WooCommerceProductGateway());
        $variation = new VariationService(new WooCommerceVariationGateway());
        $configuration = new ProductConfigurationService($product, $variation, new SettingsProductConfigurationProvider());
        $carts = new CartSessionService(new WooCommerceCartSessionStore(), new CartService(new CartValidationService(), new WooCommerceStockGateway()), $product, $variation, $configuration, new WooCommerceMoneyFormatter());
        $this->service = $service ?? new OrderHistoryService(new WooCommerceOrderHistoryGateway(), $carts, new OperationalOrderService(new WooCommerceOperationalOrderGateway()), new MySqlLockProvider(), Settings::getTimezone());
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/orders', [['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'orders'], 'permission_callback' => [$this, 'permissionCheck']]]);
        register_rest_route($namespace, '/orders/(?P<id>\d+)', [['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'detail'], 'permission_callback' => [$this, 'permissionCheck']]]);
        register_rest_route($namespace, '/orders/(?P<id>\d+)/refund', [['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'refund'], 'permission_callback' => [$this, 'refundPermissionCheck']]]);
        register_rest_route($namespace, '/orders/(?P<id>\d+)/reorder', [['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'reorder'], 'permission_callback' => [$this, 'reorderPermissionCheck']]]);
    }

    public function permissionCheck() { return $this->check(Capabilities::VIEW_ORDER_HISTORY, __('You are not allowed to access order history.', 'coffeepos')); }
    public function refundPermissionCheck() { return $this->check(Capabilities::REFUND_ORDERS, __('You are not allowed to refund orders.', 'coffeepos')); }
    public function reorderPermissionCheck() { return $this->check(Capabilities::REORDER_ORDERS, __('You are not allowed to reorder orders.', 'coffeepos')); }
    public function orders(WP_REST_Request $request) { return $this->respond(function () use ($request): array { return $this->service->list($request->get_params()); }); }
    public function detail(WP_REST_Request $request) { return $this->respond(function () use ($request): array { return ['order' => $this->service->detail(absint($request['id']))]; }); }
    public function refund(WP_REST_Request $request) { return $this->respond(function () use ($request): array { $p=$this->payload($request); return ['order'=>$this->service->refund(absint($request['id']),(string)($p['amount']??''),(string)($p['reason']??''),(string)($p['client_operation_id']??''),get_current_user_id())]; }); }
    public function reorder(WP_REST_Request $request) { return $this->respond(function () use ($request): array { $p=$this->payload($request); return $this->service->reorder(absint($request['id']),(string)($p['client_operation_id']??'')); }); }
    private function payload(WP_REST_Request $request): array { $json=$request->get_json_params(); return array_merge($request->get_params(),is_array($json)?$json:[]); }
    private function respond(callable $callback) { try{return RestResponder::success($callback());}catch(\Throwable $e){return RestResponder::fromThrowable($e);} }
    private function check(string $capability, string $message) { return current_user_can($capability) ? true : ErrorFactory::forbidden('coffeepos_action_forbidden', $message); }
}
