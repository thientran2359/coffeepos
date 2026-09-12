<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Infrastructure\Settings\Settings;

use CoffeePOS\Application\Shift\ShiftService;
use CoffeePOS\Infrastructure\Concurrency\MySqlLockProvider;
use CoffeePOS\Infrastructure\Shift\WpdbShiftRepository;
use CoffeePOS\Integration\WooCommerce\WooCommerceShiftTotalsGateway;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Server;

final class ShiftController
{
    private ShiftService $service;

    public function __construct(?ShiftService $service = null)
    {
        $this->service = $service ?? new ShiftService(new WpdbShiftRepository(), new WooCommerceShiftTotalsGateway(), new MySqlLockProvider(), [Settings::class, 'formatTimestamp']);
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/shifts/current', [[
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'current'], 'permission_callback' => [$this, 'permissionCheck'],
        ]]);
        register_rest_route($namespace, '/shifts/open', [[
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'open'], 'permission_callback' => [$this, 'permissionCheck'],
        ]]);
        register_rest_route($namespace, '/shifts/(?P<id>\d+)/close', [[
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'close'], 'permission_callback' => [$this, 'permissionCheck'],
        ]]);
        register_rest_route($namespace, '/shifts/history', [[
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'history'], 'permission_callback' => [$this, 'permissionCheck'],
        ]]);
    }

    public function permissionCheck()
    {
        if (! (bool) Settings::get(Settings::OPTION_SHIFTS_ENABLED)) {
            return ErrorFactory::forbidden('coffeepos_feature_disabled', __('Shifts are disabled in CoffeePOS settings.', 'coffeepos'));
        }
        return current_user_can(Capabilities::MANAGE_OWN_SHIFT) ? true : ErrorFactory::forbidden('coffeepos_action_forbidden', __('You are not allowed to access shifts.', 'coffeepos'));
    }

    public function current()
    {
        return $this->respond(function (): array { return ['shift' => $this->service->current(get_current_user_id())]; });
    }

    public function open(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            $payload = $this->payload($request);
            return ['shift' => $this->service->open(get_current_user_id(), (string) ($payload['opening_cash'] ?? ''), (string) ($payload['opening_note'] ?? ''))];
        }, 201);
    }

    public function close(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            $payload = $this->payload($request);
            return ['shift' => $this->service->close(absint($request->get_param('id')), get_current_user_id(), (string) ($payload['actual_cash'] ?? ''), (string) ($payload['closing_note'] ?? ''))];
        });
    }

    public function history(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array { return ['items' => $this->service->history(get_current_user_id(), absint($request->get_param('limit') ?: 50))]; });
    }

    private function payload(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        return array_merge($request->get_params(), is_array($json) ? $json : []);
    }

    private function respond(callable $callback, int $status = 200)
    {
        try { return RestResponder::success($callback(), $status); } catch (\Throwable $throwable) { return RestResponder::fromThrowable($throwable); }
    }
}
