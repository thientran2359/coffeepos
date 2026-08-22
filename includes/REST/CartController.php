<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\Cart\CartService;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Product\ProductService;
use CoffeePOS\Application\Product\VariationService;
use CoffeePOS\Domain\Cart\Cart;
use CoffeePOS\Domain\Cart\CartItem;
use CoffeePOS\Domain\Customer\CustomerContext;
use CoffeePOS\Domain\Order\TableContext;
use CoffeePOS\Domain\Payment\PaymentContext;
use CoffeePOS\Domain\Product\ModifierSelection;
use CoffeePOS\Domain\Product\QuickNoteSelection;
use CoffeePOS\Domain\Shared\Money;
use CoffeePOS\Integration\WooCommerce\WooCommerceProductGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceVariationGateway;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class CartController
{
    private CartService $cartService;

    private ProductService $productService;

    private VariationService $variationService;

    public function __construct(
        ?CartService $cartService = null,
        ?ProductService $productService = null,
        ?VariationService $variationService = null
    ) {
        $productGateway = new WooCommerceProductGateway();
        $variationGateway = new WooCommerceVariationGateway();

        $this->cartService = $cartService ?? new CartService();
        $this->productService = $productService ?? new ProductService($productGateway);
        $this->variationService = $variationService ?? new VariationService($variationGateway);
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/cart/validate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'validate'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
        ]);

        register_rest_route($namespace, '/cart/items', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'addItem'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
        ]);

        register_rest_route($namespace, '/cart/items/(?P<item_id>[a-f0-9]{64})', [
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'updateItem'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'removeItem'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
        ]);

        register_rest_route($namespace, '/cart', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'clearCart'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
        ]);
    }

    public function permissionCheck()
    {
        if (Capabilities::currentUserCanAccessPos()) {
            return true;
        }

        return ErrorFactory::forbidden(
            'coffeepos_rest_forbidden',
            __('You are not allowed to access CoffeePOS REST endpoints.', 'coffeepos')
        );
    }

    public function validate(WP_REST_Request $request)
    {
        try {
            $cart = $this->buildCartFromPayload($this->payload($request));

            return $this->cartResponse($this->cartService->project($cart)->toArray());
        } catch (\Throwable $throwable) {
            return $this->mapError($throwable);
        }
    }

    public function addItem(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);
            $cart = $this->buildCartFromPayload($payload);
            $itemPayload = (array) ($payload['item'] ?? []);
            $item = $this->buildRequestCartItem($itemPayload, $cart->currency());

            return $this->cartResponse($this->cartService->addItem($cart, $item)->toArray());
        } catch (\Throwable $throwable) {
            return $this->mapError($throwable);
        }
    }

    public function updateItem(WP_REST_Request $request)
    {
        try {
            $payload = $this->payload($request);
            $cart = $this->buildCartFromPayload($payload);
            $itemId = (string) $request->get_param('item_id');
            $itemPayload = (array) ($payload['item'] ?? []);

            if ($itemPayload !== []) {
                $replacement = $this->buildRequestCartItem($itemPayload, $cart->currency());

                return $this->cartResponse($this->cartService->replaceItem($cart, $itemId, $replacement)->toArray());
            }

            $quantity = max(1, (int) ($payload['quantity'] ?? 1));

            return $this->cartResponse($this->cartService->updateItemQuantity($cart, $itemId, $quantity)->toArray());
        } catch (\Throwable $throwable) {
            return $this->mapError($throwable);
        }
    }

    public function removeItem(WP_REST_Request $request)
    {
        try {
            $cart = $this->buildCartFromPayload($this->payload($request));
            $itemId = (string) $request->get_param('item_id');

            return $this->cartResponse($this->cartService->removeItem($cart, $itemId)->toArray());
        } catch (\Throwable $throwable) {
            return $this->mapError($throwable);
        }
    }

    public function clearCart(WP_REST_Request $request)
    {
        try {
            $cart = $this->buildCartFromPayload($this->payload($request));

            return $this->cartResponse($this->cartService->clear($cart)->toArray());
        } catch (\Throwable $throwable) {
            return $this->mapError($throwable);
        }
    }

    private function payload(WP_REST_Request $request): array
    {
        $payload = $request->get_json_params();

        return is_array($payload) ? $payload : [];
    }

    private function buildCartFromPayload(array $payload): Cart
    {
        $cartPayload = (array) ($payload['cart'] ?? []);
        $currency = strtoupper(trim((string) ($cartPayload['currency'] ?? '')));

        if ($currency === '') {
            $currency = $this->defaultCurrency();
        }

        $cart = $this->cartService->createCart($currency);

        $orderType = (string) ($cartPayload['order_type'] ?? 'takeaway');
        $tablePayload = (array) ($cartPayload['table'] ?? []);
        $tableContext = TableContext::from(
            $this->toNullableInt($tablePayload['table_id'] ?? null),
            (string) ($tablePayload['table_label'] ?? '')
        );

        $this->cartService->setOrderType($cart, $orderType, $tableContext->hasTable() ? $tableContext : null);
        $this->cartService->setCustomerContext($cart, $this->buildCustomerContext((array) ($cartPayload['customer'] ?? [])));
        $this->cartService->setPaymentContext($cart, $this->buildPaymentContext((array) ($cartPayload['payment'] ?? [])));

        $items = (array) ($cartPayload['items'] ?? []);

        foreach ($items as $itemPayload) {
            if (! is_array($itemPayload)) {
                continue;
            }

            $this->cartService->addItem($cart, $this->buildSnapshotCartItem($itemPayload, $currency));
        }

        return $cart;
    }

    private function buildCustomerContext(array $payload): CustomerContext
    {
        $customerId = (int) ($payload['customer_id'] ?? 0);
        $isGuest = (bool) ($payload['is_guest'] ?? true);

        if ($isGuest || $customerId <= 0) {
            return CustomerContext::guest();
        }

        return CustomerContext::member(
            $customerId,
            (string) ($payload['phone'] ?? ''),
            (string) ($payload['display_name'] ?? '')
        );
    }

    private function buildPaymentContext(array $payload): PaymentContext
    {
        $couponCode = trim((string) ($payload['coupon_code'] ?? ''));

        return $couponCode === '' ? PaymentContext::none() : PaymentContext::withCoupon($couponCode);
    }

    private function buildSnapshotCartItem(array $payload, string $currency): CartItem
    {
        $productId = max(1, (int) ($payload['product_id'] ?? 0));
        $variationId = max(0, (int) ($payload['variation_id'] ?? 0));
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));
        $priceMinor = (int) ($payload['unit_price_minor'] ?? 0);
        $itemCurrency = strtoupper(trim((string) ($payload['currency'] ?? $currency)));

        if ($itemCurrency === '') {
            $itemCurrency = $currency;
        }

        return CartItem::create(
            $productId,
            $variationId,
            $quantity,
            Money::fromMinor($priceMinor, $itemCurrency),
            ModifierSelection::fromArray((array) ($payload['modifiers'] ?? [])),
            QuickNoteSelection::fromArray((array) ($payload['quick_notes'] ?? [])),
            (string) ($payload['custom_note'] ?? '')
        );
    }

    private function buildRequestCartItem(array $payload, string $currency): CartItem
    {
        $productId = max(1, (int) ($payload['product_id'] ?? 0));
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));
        $product = $this->productService->getById($productId)->toArray();
        $variationId = max(0, (int) ($payload['variation_id'] ?? 0));
        $priceMinor = (int) ($product['price_minor'] ?? 0);
        $itemCurrency = strtoupper(trim((string) ($product['currency'] ?? $currency)));

        if ((bool) ($product['is_variable'] ?? false)) {
            if ($variationId > 0) {
                $variation = $this->variationService->findById($variationId)->toArray();
            } else {
                $variation = $this->variationService->resolveProductVariation(
                    $productId,
                    (array) ($payload['selected_attributes'] ?? [])
                )->toArray();
            }

            $variationId = (int) ($variation['id'] ?? 0);
            $priceMinor = (int) ($variation['price_minor'] ?? $priceMinor);
            $itemCurrency = strtoupper(trim((string) ($variation['currency'] ?? $itemCurrency)));
        }

        if ($itemCurrency === '') {
            $itemCurrency = $currency;
        }

        return CartItem::create(
            $productId,
            $variationId,
            $quantity,
            Money::fromMinor($priceMinor, $itemCurrency),
            ModifierSelection::fromArray((array) ($payload['modifiers'] ?? [])),
            QuickNoteSelection::fromArray((array) ($payload['quick_notes'] ?? [])),
            (string) ($payload['custom_note'] ?? '')
        );
    }

    private function toNullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }

    private function defaultCurrency(): string
    {
        if (function_exists('get_woocommerce_currency')) {
            return strtoupper(trim((string) get_woocommerce_currency()));
        }

        return 'USD';
    }

    private function cartResponse(array $cartPayload): WP_REST_Response
    {
        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'cart' => $cartPayload,
            ],
        ]);
    }

    private function mapError(\Throwable $throwable)
    {
        if ($throwable instanceof Phase01Exception) {
            return ErrorFactory::restError(
                $throwable->errorCode(),
                $throwable->getMessage(),
                422,
                $throwable->context()
            );
        }

        return ErrorFactory::restError(
            'coffeepos_rest_error',
            __('Unexpected error while processing POS request.', 'coffeepos'),
            500
        );
    }
}
