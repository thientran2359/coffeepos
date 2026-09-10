<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\Cart\CartService;
use CoffeePOS\Application\Cart\CartSessionService;
use CoffeePOS\Application\Cart\CartValidationService;
use CoffeePOS\Application\Cart\SuspendedCartService;
use CoffeePOS\Application\Customer\CustomerService;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Product\ProductConfigurationService;
use CoffeePOS\Application\Product\ProductService;
use CoffeePOS\Application\Product\VariationService;
use CoffeePOS\Infrastructure\Cart\WpdbSuspendedCartRepository;
use CoffeePOS\Infrastructure\Concurrency\MySqlLockProvider;
use CoffeePOS\Integration\WooCommerce\WooCommerceMembershipProvider;
use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Infrastructure\Settings\SettingsProductConfigurationProvider;
use CoffeePOS\Infrastructure\Settings\SettingsTableProvider;
use CoffeePOS\Integration\WooCommerce\WooCommerceCartSerializer;
use CoffeePOS\Integration\WooCommerce\WooCommerceCartSessionStore;
use CoffeePOS\Integration\WooCommerce\WooCommerceCustomerGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceMoneyFormatter;
use CoffeePOS\Integration\WooCommerce\WooCommercePricingGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceProductGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceStockGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceVariationGateway;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Server;

final class HeldCartController
{
    private SuspendedCartService $service;

    public function __construct(?SuspendedCartService $service = null)
    {
        if ($service !== null) {
            $this->service = $service;
            return;
        }

        $store = new WooCommerceCartSessionStore();
        $product = new ProductService(new WooCommerceProductGateway());
        $variation = new VariationService(new WooCommerceVariationGateway());
        $configuration = new ProductConfigurationService($product, $variation, new SettingsProductConfigurationProvider());
        $membershipProvider = new WooCommerceMembershipProvider();
        $customer = new CustomerService(
            new WooCommerceCustomerGateway(),
            $membershipProvider,
            new MySqlLockProvider(),
            Settings::memberRequiredFields()
        );
        $tables = new SettingsTableProvider();
        $pricing = new WooCommercePricingGateway();
        $cartSessions = new CartSessionService(
            $store,
            new CartService(new CartValidationService((bool) Settings::get(Settings::OPTION_REQUIRE_DINE_IN_TABLE)), new WooCommerceStockGateway()),
            $product,
            $variation,
            $configuration,
            new WooCommerceMoneyFormatter(),
            $customer,
            $tables,
            (bool) Settings::get(Settings::OPTION_REQUIRE_DINE_IN_TABLE),
            $pricing,
            $membershipProvider
        );

        $this->service = new SuspendedCartService(
            new WpdbSuspendedCartRepository(),
            $store,
            new WooCommerceCartSerializer(),
            $cartSessions,
            new MySqlLockProvider(),
            [Settings::class, 'formatTimestamp']
        );
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/held-carts', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'list'], 'permission_callback' => [$this, 'permissionCheck']],
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'hold'], 'permission_callback' => [$this, 'permissionCheck']],
        ]);
        register_rest_route($namespace, '/held-carts/(?P<id>\d+)', [
            ['methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'detail'], 'permission_callback' => [$this, 'permissionCheck']],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'delete'], 'permission_callback' => [$this, 'permissionCheck']],
        ]);
        register_rest_route($namespace, '/held-carts/(?P<id>\d+)/resume', [[
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'resume'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);
    }

    public function permissionCheck()
    {
        return current_user_can(Capabilities::ACCESS_CASHIER)
            ? true
            : ErrorFactory::forbidden('coffeepos_rest_forbidden', __('You are not allowed to access held carts.', 'coffeepos'));
    }

    public function hold(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            $payload = $this->payload($request);
            return $this->service->hold(
                get_current_user_id(),
                sanitize_text_field((string) ($payload['label'] ?? '')),
                $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                $this->revision($payload)
            );
        }, 201);
    }

    public function list(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            return ['items' => $this->service->list(get_current_user_id(), absint($request->get_param('limit') ?: 100))];
        });
    }

    public function detail(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            return ['held_cart' => $this->service->detail(absint($request->get_param('id')), get_current_user_id())];
        });
    }

    public function resume(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            $payload = $this->payload($request);
            return $this->service->resume(
                absint($request->get_param('id')),
                get_current_user_id(),
                $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                $this->revision($payload)
            );
        });
    }

    public function delete(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            return $this->service->delete(absint($request->get_param('id')), get_current_user_id());
        });
    }

    private function payload(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        return array_merge($request->get_params(), is_array($json) ? $json : []);
    }

    private function sessionId(string $value): string
    {
        $sessionId = sanitize_text_field($value);
        if (! preg_match('/^[a-zA-Z0-9\-]{16,64}$/', $sessionId)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_CART, 'Invalid POS session id.');
        }

        return $sessionId;
    }

    private function revision(array $payload): int
    {
        return array_key_exists('expected_revision', $payload) ? (int) $payload['expected_revision'] : -1;
    }

    private function respond(callable $callback, int $status = 200)
    {
        try {
            return RestResponder::success($callback(), $status);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }
}
