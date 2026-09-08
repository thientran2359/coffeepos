<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\Product\StockManagementService;
use CoffeePOS\Infrastructure\Logging\Logger;
use CoffeePOS\Integration\WooCommerce\WooCommerceStockManagementGateway;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Server;

final class StockController
{
    private StockManagementService $service;

    public function __construct(?StockManagementService $service = null)
    {
        $this->service = $service ?? new StockManagementService(new WooCommerceStockManagementGateway());
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/products/(?P<id>\d+)/stock', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'projection'], 'permission_callback' => [$this, 'permissionCheck']],
            ['methods' => WP_REST_Server::EDITABLE, 'callback' => [$this, 'update'], 'permission_callback' => [$this, 'permissionCheck']],
        ]);
    }

    public function permissionCheck()
    {
        if (current_user_can(Capabilities::MANAGE_STOCK)) {
            return true;
        }

        return ErrorFactory::forbidden('coffeepos_stock_forbidden', __('You are not allowed to update product stock.', 'coffeepos'));
    }

    public function projection(WP_REST_Request $request)
    {
        try {
            return RestResponder::success(['stock' => $this->service->projection((int) $request->get_param('id'))]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function update(WP_REST_Request $request)
    {
        try {
            $payload = $request->get_json_params();
            $payload = is_array($payload) ? $payload : [];
            $payload['reason'] = sanitize_textarea_field((string) ($payload['reason'] ?? ''));
            $result = $this->service->update((int) $request->get_param('id'), $payload);

            Logger::info('Product stock adjusted from Cashier.', [
                'product_id' => (int) $request->get_param('id'),
                'target_id' => (int) ($payload['target_id'] ?? 0),
                'user_id' => get_current_user_id(),
                'reason' => $result['reason'],
            ]);

            unset($result['reason']);

            return RestResponder::success($result);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }
}
