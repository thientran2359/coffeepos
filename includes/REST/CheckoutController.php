<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\Cart\CartValidationService;
use CoffeePOS\Application\Checkout\CheckoutService;
use CoffeePOS\Application\Coupon\CartCouponService;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Integration\Payment\PendingVietQrGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceCartSessionStore;
use CoffeePOS\Integration\WooCommerce\WooCommerceMoneyFormatter;
use CoffeePOS\Integration\WooCommerce\WooCommerceOrderGateway;
use CoffeePOS\Integration\WooCommerce\WooCommercePricingGateway;
use CoffeePOS\Application\Shift\ShiftService;
use CoffeePOS\Infrastructure\Concurrency\MySqlLockProvider;
use CoffeePOS\Infrastructure\Shift\WpdbShiftRepository;
use CoffeePOS\Integration\WooCommerce\WooCommerceShiftTotalsGateway;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Server;

final class CheckoutController
{
    private CartCouponService $coupons;
    private CheckoutService $checkout;
    private ShiftService $shifts;

    public function __construct(?CartCouponService $coupons = null, ?CheckoutService $checkout = null)
    {
        $store = new WooCommerceCartSessionStore();
        $pricing = new WooCommercePricingGateway();
        $formatter = new WooCommerceMoneyFormatter();
        $this->coupons = $coupons ?? new CartCouponService($store, $pricing, $formatter);
        $this->checkout = $checkout ?? new CheckoutService(
            $store,
            new CartValidationService(),
            $pricing,
            new WooCommerceOrderGateway(),
            new PendingVietQrGateway(),
            $formatter
        );
        $this->shifts = new ShiftService(new WpdbShiftRepository(), new WooCommerceShiftTotalsGateway(), new MySqlLockProvider());
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/coupons/applicable', [[
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'applicableCoupons'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);
        register_rest_route($namespace, '/cart/coupon', [
            ['methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'applyCoupon'], 'permission_callback' => [$this, 'permissionCheck']],
            ['methods' => WP_REST_Server::DELETABLE, 'callback' => [$this, 'removeCoupon'], 'permission_callback' => [$this, 'permissionCheck']],
        ]);
        register_rest_route($namespace, '/orders/checkout', [[
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'checkout'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);
        register_rest_route($namespace, '/payments/vietqr-preview', [[
            'methods' => WP_REST_Server::CREATABLE, 'callback' => [$this, 'vietQrPreview'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);
        register_rest_route($namespace, '/orders/(?P<id>\d+)/payment', [[
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'paymentStatus'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);
        register_rest_route($namespace, '/orders/(?P<id>\d+)/receipt', [[
            'methods' => WP_REST_Server::READABLE, 'callback' => [$this, 'receipt'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);
    }

    public function permissionCheck()
    {
        return Capabilities::currentUserCanAccessPos()
            ? true
            : ErrorFactory::forbidden('coffeepos_rest_forbidden', __('You are not allowed to access CoffeePOS REST endpoints.', 'coffeepos'));
    }

    public function applicableCoupons(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            return ['items' => $this->coupons->applicable($this->sessionId((string) $request->get_param('pos_session_id')))];
        });
    }

    public function applyCoupon(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            $payload = $this->payload($request);
            return ['cart' => $this->coupons->apply($this->sessionId((string) ($payload['pos_session_id'] ?? '')), $this->revision($payload), sanitize_text_field((string) ($payload['code'] ?? '')))->toArray()];
        });
    }

    public function removeCoupon(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            $payload = $this->payload($request);
            return ['cart' => $this->coupons->remove($this->sessionId((string) ($payload['pos_session_id'] ?? '')), $this->revision($payload))->toArray()];
        });
    }

    public function checkout(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            $payload = $this->payload($request);
            $shift = $this->shifts->requireOpen(get_current_user_id());
            return $this->checkout->checkout(
                $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                $this->revision($payload),
                sanitize_text_field((string) ($payload['client_operation_id'] ?? '')),
                is_array($payload['payment'] ?? null) ? $payload['payment'] : [],
                get_current_user_id(),
                (int) $shift['id']
            );
        });
    }

    public function paymentStatus(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array { return $this->checkout->paymentStatus(absint($request->get_param('id'))); });
    }

    public function vietQrPreview(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array {
            $payload = $this->payload($request);
            return $this->checkout->previewBankTransfer(
                $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                $this->revision($payload)
            );
        });
    }

    public function receipt(WP_REST_Request $request)
    {
        return $this->respond(function () use ($request): array { return ['receipt' => $this->checkout->receipt(absint($request->get_param('id')))]; });
    }

    private function respond(callable $callback)
    {
        try {
            return RestResponder::success($callback());
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    private function payload(WP_REST_Request $request): array
    {
        $json = $request->get_json_params();
        return array_merge($request->get_params(), is_array($json) ? $json : []);
    }

    private function sessionId(string $value): string
    {
        $id = sanitize_text_field($value);
        if (! preg_match('/^[a-zA-Z0-9\-]{16,64}$/', $id)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_CART, 'Invalid POS session id.');
        }
        return $id;
    }

    private function revision(array $payload): int
    {
        return array_key_exists('expected_revision', $payload) ? (int) $payload['expected_revision'] : -1;
    }
}
