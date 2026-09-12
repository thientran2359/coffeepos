<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\Operations\OperationalOrderService;
use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Integration\WooCommerce\WooCommerceOperationalOrderGateway;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Server;

final class OperationalOrderController
{
    private OperationalOrderService $orders;

    public function __construct(?OperationalOrderService $orders = null)
    {
        $this->orders = $orders ?? new OperationalOrderService(
            new WooCommerceOperationalOrderGateway(),
            (bool) Settings::get(Settings::OPTION_KDS_ENABLED)
        );
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/kds/orders', [['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'kdsOrders'], 'permission_callback' => [$this, 'kdsPermissionCheck']]]);
        register_rest_route($namespace, '/kds/orders/(?P<id>\d+)/transition', [['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'transition'], 'permission_callback' => [$this, 'kdsPermissionCheck']]]);
        register_rest_route($namespace, '/order-queue/orders', [['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'queueOrders'], 'permission_callback' => [$this, 'queuePermissionCheck']]]);
        register_rest_route($namespace, '/orders/(?P<id>\d+)/complete', [['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'complete'], 'permission_callback' => [$this, 'queuePermissionCheck']]]);
        register_rest_route($namespace, '/orders/(?P<id>\d+)/cancel', [['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'cancel'], 'permission_callback' => [$this, 'cancelPermissionCheck']]]);
    }

    public function kdsPermissionCheck()
    {
        if (! (bool) Settings::get(Settings::OPTION_KDS_ENABLED)) {
            return ErrorFactory::forbidden('coffeepos_feature_disabled', __('Kitchen Display is disabled in CoffeePOS settings.', 'coffeepos'));
        }
        return $this->check(Capabilities::ACCESS_KDS);
    }

    public function queuePermissionCheck()
    {
        if (! (bool) Settings::get(Settings::OPTION_KDS_ENABLED) && current_user_can(Capabilities::ACCESS_CASHIER)) {
            return true;
        }
        return $this->check(Capabilities::ACCESS_ORDER_QUEUE);
    }

    public function cancelPermissionCheck()
    {
        return $this->check(Capabilities::CANCEL_ORDERS);
    }

    public function kdsOrders(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            $raw = $request->get_param('states');
            $states = is_array($raw) ? $raw : preg_split('/,/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
            return ['server_time' => gmdate('c'), 'poll_interval_ms' => Settings::getKdsPollInterval(), 'orders' => $this->orders->listKds(array_map('sanitize_key', is_array($states) ? $states : []), $this->limit($request))];
        });
    }

    public function queueOrders(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            return ['server_time' => gmdate('c'), 'poll_interval_ms' => Settings::getOrderQueuePollInterval(), 'orders' => $this->orders->listQueue(sanitize_key((string) ($request->get_param('kds_state') ?: 'all')), sanitize_key((string) ($request->get_param('order_type') ?: 'all')), $this->limit($request))];
        });
    }

    public function transition(WP_REST_Request $request) { $payload = $this->payload($request); return $this->mutate($request, sanitize_key((string) ($payload['target_state'] ?? '')), 'kds'); }
    public function complete(WP_REST_Request $request) { return $this->mutate($request, 'completed', 'order_queue'); }
    public function cancel(WP_REST_Request $request) { return $this->mutate($request, 'cancelled', 'order_queue'); }

    private function mutate(WP_REST_Request $request, string $targetState, string $surface)
    {
        return $this->respond(function () use ($request, $targetState, $surface): array {
            $payload = $this->payload($request);
            return $this->orders->transition(absint($request->get_param('id')), sanitize_key((string) ($payload['expected_state'] ?? '')), array_key_exists('expected_revision', $payload) ? (int) $payload['expected_revision'] : -1, $targetState, sanitize_text_field((string) ($payload['client_operation_id'] ?? '')), get_current_user_id(), $surface, sanitize_text_field((string) ($payload['reason'] ?? '')));
        });
    }

    private function payload(WP_REST_Request $request): array { $json = $request->get_json_params(); return array_merge($request->get_params(), is_array($json) ? $json : []); }
    private function limit(WP_REST_Request $request): int { return max(1, min(200, absint($request->get_param('limit') ?: 100))); }
    private function respond(callable $callback) { try { return RestResponder::success($callback()); } catch (\Throwable $throwable) { return RestResponder::fromThrowable($throwable); } }
    private function check(string $capability) { return current_user_can($capability) ? true : ErrorFactory::forbidden('coffeepos_action_forbidden', __('You are not allowed to perform this CoffeePOS operation.', 'coffeepos')); }
}
