<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\Cart\CartService;
use CoffeePOS\Application\Cart\CartSessionService;
use CoffeePOS\Application\Cart\CartValidationService;
use CoffeePOS\Application\Contracts\TableProviderInterface;
use CoffeePOS\Application\Customer\CustomerService;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Product\ProductConfigurationService;
use CoffeePOS\Application\Product\ProductService;
use CoffeePOS\Application\Product\VariationService;
use CoffeePOS\Infrastructure\Settings\SettingsProductConfigurationProvider;
use CoffeePOS\Infrastructure\Settings\SettingsTableProvider;
use CoffeePOS\Infrastructure\Customer\NullMembershipProvider;
use CoffeePOS\Infrastructure\Concurrency\MySqlLockProvider;
use CoffeePOS\Integration\WooCommerce\WooCommerceCartSessionStore;
use CoffeePOS\Integration\WooCommerce\WooCommerceCustomerGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceMoney;
use CoffeePOS\Integration\WooCommerce\WooCommerceMoneyFormatter;
use CoffeePOS\Integration\WooCommerce\WooCommerceProductGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceStockGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceVariationGateway;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Server;

final class CartController
{
    private CartSessionService $cartSessionService;

    private WooCommerceMoney $money;

    private CustomerService $customerService;

    private TableProviderInterface $tableProvider;

    public function __construct(
        ?CartSessionService $cartSessionService = null,
        ?WooCommerceMoney $money = null,
        ?CustomerService $customerService = null,
        ?TableProviderInterface $tableProvider = null
    )
    {
        $productGateway = new WooCommerceProductGateway();
        $variationGateway = new WooCommerceVariationGateway();
        $productService = new ProductService($productGateway);
        $variationService = new VariationService($variationGateway);
        $configurationService = new ProductConfigurationService(
            $productService,
            $variationService,
            new SettingsProductConfigurationProvider()
        );

        $this->customerService = $customerService ?? new CustomerService(
            new WooCommerceCustomerGateway(),
            new NullMembershipProvider(),
            new MySqlLockProvider()
        );
        $this->tableProvider = $tableProvider ?? new SettingsTableProvider();
        $this->cartSessionService = $cartSessionService ?? new CartSessionService(
            new WooCommerceCartSessionStore(),
            new CartService(new CartValidationService(), new WooCommerceStockGateway()),
            $productService,
            $variationService,
            $configurationService,
            new WooCommerceMoneyFormatter(),
            $this->customerService,
            $this->tableProvider
        );
        $this->money = $money ?? new WooCommerceMoney();
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/cart/session', [[
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'createSession'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);

        register_rest_route($namespace, '/cart', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCart'],
                'permission_callback' => [$this, 'getCartPermissionCheck'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'clearCart'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
        ]);

        register_rest_route($namespace, '/cart/validate', [[
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'validateCart'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);

        register_rest_route($namespace, '/cart/items', [[
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'addItem'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);

        register_rest_route($namespace, '/cart/items/(?P<item_id>[a-f0-9]{64})', [
            [
                'methods' => 'PATCH',
                'callback' => [$this, 'updateItem'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'removeItem'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
        ]);

        register_rest_route($namespace, '/customers/lookup', [[
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'lookupCustomer'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);

        register_rest_route($namespace, '/customers', [[
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'createCustomer'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);

        register_rest_route($namespace, '/tables', [[
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'tables'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);

        register_rest_route($namespace, '/cart/customer', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'attachCustomer'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'removeCustomer'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
        ]);

        register_rest_route($namespace, '/cart/service-context', [[
            'methods' => 'PUT',
            'callback' => [$this, 'setServiceContext'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);

        register_rest_route($namespace, '/cart/order-note', [
            [
                'methods' => 'PUT',
                'callback' => [$this, 'setOrderNote'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'clearOrderNote'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
        ]);
    }

    public function permissionCheck()
    {
        if (current_user_can(Capabilities::ACCESS_CASHIER)) {
            return true;
        }

        return ErrorFactory::forbidden(
            'coffeepos_rest_forbidden',
            __('You are not allowed to access CoffeePOS REST endpoints.', 'coffeepos')
        );
    }

    public function getCartPermissionCheck(WP_REST_Request $request)
    {
        if ((string) $request->get_param('view') === 'customer') {
            return true;
        }

        return $this->permissionCheck();
    }

    public function createSession(WP_REST_Request $request)
    {
        try {
            return RestResponder::success([
                'cart' => $this->cartSessionService->createSession($this->money->currentCurrency())->toArray(),
            ], 201);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function getCart(WP_REST_Request $request)
    {
        try {
            $sessionId = $this->sessionId((string) $request->get_param('pos_session_id'));
            if ((string) $request->get_param('view') === 'customer') {
                return RestResponder::success([
                    'cart' => $this->cartSessionService->getCustomerSession($sessionId),
                ]);
            }
            return RestResponder::success([
                'cart' => $this->cartSessionService->getSession(
                    $sessionId
                )->toArray(),
            ]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function addItem(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);

            return RestResponder::success([
                'cart' => $this->cartSessionService->addItem(
                    $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                    $this->revision($payload),
                    $this->itemInput($payload)
                )->toArray(),
            ], 201);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function updateItem(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);

            return RestResponder::success([
                'cart' => $this->cartSessionService->updateItem(
                    $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                    $this->revision($payload),
                    (string) $request->get_param('item_id'),
                    $this->itemInput($payload)
                )->toArray(),
            ]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function removeItem(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);

            return RestResponder::success([
                'cart' => $this->cartSessionService->removeItem(
                    $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                    $this->revision($payload),
                    (string) $request->get_param('item_id')
                )->toArray(),
            ]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function clearCart(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);

            return RestResponder::success([
                'cart' => $this->cartSessionService->clear(
                    $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                    $this->revision($payload)
                )->toArray(),
            ]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function validateCart(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);

            return RestResponder::success($this->cartSessionService->validate(
                $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                $this->revision($payload)
            ));
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function lookupCustomer(WP_REST_Request $request)
    {
        try {
            return RestResponder::success([
                'customer' => $this->customerService->findByPhone(
                    sanitize_text_field((string) $request->get_param('phone'))
                )->toArray(),
            ]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function createCustomer(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);
            $result = $this->customerService->createMember([
                'display_name' => sanitize_text_field((string) ($payload['display_name'] ?? '')),
                'phone' => sanitize_text_field((string) ($payload['phone'] ?? '')),
                'email' => sanitize_text_field((string) ($payload['email'] ?? '')),
                'client_operation_id' => sanitize_text_field((string) ($payload['client_operation_id'] ?? '')),
            ]);

            return RestResponder::success([
                'customer' => $result['customer']->toArray(),
            ], ! empty($result['replayed']) ? 200 : 201);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function tables(WP_REST_Request $request)
    {
        try {
            return RestResponder::success(['items' => $this->tableProvider->listAvailable()]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function attachCustomer(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);
            return RestResponder::success(['cart' => $this->cartSessionService->attachCustomer(
                $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                $this->revision($payload),
                (int) ($payload['customer_id'] ?? 0)
            )->toArray()]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function removeCustomer(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);
            return RestResponder::success(['cart' => $this->cartSessionService->removeCustomer(
                $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                $this->revision($payload)
            )->toArray()]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function setServiceContext(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);
            return RestResponder::success(['cart' => $this->cartSessionService->setServiceContext(
                $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                $this->revision($payload),
                sanitize_key((string) ($payload['order_type'] ?? '')),
                (int) ($payload['table_id'] ?? 0)
            )->toArray()]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function setOrderNote(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);
            return RestResponder::success(['cart' => $this->cartSessionService->setOrderNote(
                $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                $this->revision($payload),
                sanitize_textarea_field((string) ($payload['note'] ?? ''))
            )->toArray()]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function clearOrderNote(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);
            return RestResponder::success(['cart' => $this->cartSessionService->clearOrderNote(
                $this->sessionId((string) ($payload['pos_session_id'] ?? '')),
                $this->revision($payload)
            )->toArray()]);
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
        $sessionId = sanitize_text_field($value);

        if (! preg_match('/^[a-zA-Z0-9\-]{16,64}$/', $sessionId)) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CART,
                'Invalid POS session id.'
            );
        }

        return $sessionId;
    }

    private function revision(array $payload): int
    {
        return array_key_exists('expected_revision', $payload)
            ? (int) $payload['expected_revision']
            : -1;
    }

    private function itemInput(array $payload): array
    {
        $input = [];

        foreach (['product_id', 'variation_id', 'quantity'] as $integerField) {
            if (array_key_exists($integerField, $payload)) {
                $input[$integerField] = (int) $payload[$integerField];
            }
        }

        if (array_key_exists('custom_note', $payload)) {
            $input['custom_note'] = sanitize_textarea_field((string) $payload['custom_note']);
        }

        if (array_key_exists('quick_notes', $payload)) {
            $input['quick_notes'] = array_values(array_filter(array_map('sanitize_key', (array) $payload['quick_notes'])));
        }

        if (array_key_exists('modifiers', $payload)) {
            $modifiers = [];

            foreach ((array) $payload['modifiers'] as $groupId => $optionIds) {
                $normalizedGroupId = sanitize_key((string) $groupId);

                if ($normalizedGroupId === '' || ! is_array($optionIds)) {
                    continue;
                }

                $modifiers[$normalizedGroupId] = array_values(array_filter(array_map('sanitize_key', $optionIds)));
            }

            $input['modifiers'] = $modifiers;
        }

        return $input;
    }
}
