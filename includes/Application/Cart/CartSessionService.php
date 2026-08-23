<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Cart;

use CoffeePOS\Application\Contracts\CartSessionStoreInterface;
use CoffeePOS\Application\Contracts\MoneyFormatterInterface;
use CoffeePOS\Application\Contracts\TableProviderInterface;
use CoffeePOS\Application\Customer\CustomerService;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Product\ProductConfigurationService;
use CoffeePOS\Application\Product\ProductService;
use CoffeePOS\Application\Product\VariationService;
use CoffeePOS\Application\Projection\CartView;
use CoffeePOS\Application\Projection\CustomerCartView;
use CoffeePOS\Domain\Cart\Cart;
use CoffeePOS\Domain\Cart\CartItem;
use CoffeePOS\Domain\Customer\CustomerContext;
use CoffeePOS\Domain\Order\OrderType;
use CoffeePOS\Domain\Order\TableContext;
use CoffeePOS\Domain\Product\ModifierSelection;
use CoffeePOS\Domain\Product\QuickNoteSelection;
use CoffeePOS\Domain\Shared\Money;

final class CartSessionService
{
    private CartSessionStoreInterface $sessionStore;

    private CartService $cartService;

    private ProductService $productService;

    private VariationService $variationService;

    private ProductConfigurationService $configurationService;

    private MoneyFormatterInterface $moneyFormatter;

    private ?CustomerService $customerService;

    private ?TableProviderInterface $tableProvider;

    public function __construct(
        CartSessionStoreInterface $sessionStore,
        CartService $cartService,
        ProductService $productService,
        VariationService $variationService,
        ProductConfigurationService $configurationService,
        MoneyFormatterInterface $moneyFormatter,
        ?CustomerService $customerService = null,
        ?TableProviderInterface $tableProvider = null
    ) {
        $this->sessionStore = $sessionStore;
        $this->cartService = $cartService;
        $this->productService = $productService;
        $this->variationService = $variationService;
        $this->configurationService = $configurationService;
        $this->moneyFormatter = $moneyFormatter;
        $this->customerService = $customerService;
        $this->tableProvider = $tableProvider;
    }

    public function createSession(string $currency): CartView
    {
        return $this->project($this->sessionStore->create($currency));
    }

    public function getSession(string $posSessionId): CartView
    {
        return $this->project($this->load($posSessionId));
    }

    public function getCustomerSession(string $posSessionId): array
    {
        return CustomerCartView::fromArray($this->getSession($posSessionId)->toArray());
    }

    public function addItem(string $posSessionId, int $expectedRevision, array $input): CartView
    {
        $cart = $this->loadForMutation($posSessionId, $expectedRevision);
        $cartItem = $this->buildCartItem($input);
        $this->cartService->addItem($cart, $cartItem);

        return $this->persist($cart, $expectedRevision);
    }

    public function updateItem(string $posSessionId, int $expectedRevision, string $itemId, array $input): CartView
    {
        $cart = $this->loadForMutation($posSessionId, $expectedRevision);
        $currentItem = $this->findItem($cart, $itemId);

        if ($currentItem === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CART,
                'Cart item was not found.',
                ['item_id' => $itemId]
            );
        }

        $replacement = $this->buildCartItem($input, $currentItem);
        $this->cartService->replaceItem($cart, $itemId, $replacement);

        return $this->persist($cart, $expectedRevision);
    }

    public function removeItem(string $posSessionId, int $expectedRevision, string $itemId): CartView
    {
        $cart = $this->loadForMutation($posSessionId, $expectedRevision);

        if ($this->findItem($cart, $itemId) === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CART,
                'Cart item was not found.',
                ['item_id' => $itemId]
            );
        }

        $this->cartService->removeItem($cart, $itemId);

        return $this->persist($cart, $expectedRevision);
    }

    public function clear(string $posSessionId, int $expectedRevision): CartView
    {
        $cart = $this->loadForMutation($posSessionId, $expectedRevision);
        $this->cartService->clear($cart);

        return $this->persist($cart, $expectedRevision);
    }

    public function validate(string $posSessionId, int $expectedRevision): array
    {
        $cart = $this->loadForMutation($posSessionId, $expectedRevision);
        $this->cartService->validate($cart);

        return [
            'cart' => $this->project($cart)->toArray(),
            'validation' => [
                'valid' => true,
                'errors' => [],
            ],
        ];
    }

    public function attachCustomer(string $posSessionId, int $expectedRevision, int $customerId): CartView
    {
        if ($this->customerService === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Customer service is not configured.'
            );
        }

        $cart = $this->loadForMutation($posSessionId, $expectedRevision);
        $customer = $this->customerService->findById($customerId)->toArray();
        $this->cartService->setCustomerContext($cart, CustomerContext::member(
            (int) $customer['customer_id'],
            (string) $customer['phone'],
            (string) $customer['display_name'],
            is_array($customer['membership'] ?? null) ? $customer['membership'] : null
        ));

        return $this->persist($cart, $expectedRevision);
    }

    public function removeCustomer(string $posSessionId, int $expectedRevision): CartView
    {
        $cart = $this->loadForMutation($posSessionId, $expectedRevision);
        $this->cartService->setCustomerContext($cart, CustomerContext::guest());

        return $this->persist($cart, $expectedRevision);
    }

    public function setServiceContext(
        string $posSessionId,
        int $expectedRevision,
        string $orderType,
        int $tableId = 0
    ): CartView {
        $cart = $this->loadForMutation($posSessionId, $expectedRevision);

        if ($orderType === OrderType::TAKEAWAY) {
            $this->cartService->setOrderType($cart, OrderType::TAKEAWAY);

            return $this->persist($cart, $expectedRevision);
        }

        if ($orderType !== OrderType::DINE_IN) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_ORDER_TYPE,
                'Invalid order type.',
                ['order_type' => $orderType]
            );
        }

        if ($this->tableProvider === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Table provider is not configured.'
            );
        }

        $table = $this->tableProvider->findAvailableById($tableId);

        if ($table === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_TABLE,
                'Selected table is unavailable.',
                ['table_id' => $tableId]
            );
        }

        $this->cartService->setOrderType(
            $cart,
            OrderType::DINE_IN,
            TableContext::from((int) $table['id'], (string) $table['label'])
        );

        return $this->persist($cart, $expectedRevision);
    }

    private function load(string $posSessionId): Cart
    {
        $cart = $this->sessionStore->load($posSessionId);

        if ($cart === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::CART_SESSION_NOT_FOUND,
                'Cart session was not found.',
                ['pos_session_id' => $posSessionId]
            );
        }

        return $cart;
    }

    private function loadForMutation(string $posSessionId, int $expectedRevision): Cart
    {
        $cart = $this->load($posSessionId);

        if ($cart->state() !== Cart::STATE_ACTIVE) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CART,
                'Cart is frozen for checkout.',
                ['state' => $cart->state(), 'order_id' => $cart->checkoutOrderId()]
            );
        }

        if ($expectedRevision < 0 || $cart->revision() !== $expectedRevision) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::CART_REVISION_CONFLICT,
                'Cart revision is out of date.',
                [
                    'pos_session_id' => $posSessionId,
                    'expected_revision' => $expectedRevision,
                    'current_revision' => $cart->revision(),
                    'cart' => $this->project($cart)->toArray(),
                ]
            );
        }

        return $cart;
    }

    private function persist(Cart $cart, int $expectedRevision): CartView
    {
        try {
            return $this->project($this->sessionStore->save($cart, $expectedRevision));
        } catch (Phase01Exception $exception) {
            if ($exception->errorCode() !== Phase01ErrorCodes::CART_REVISION_CONFLICT) {
                throw $exception;
            }

            $latest = $this->sessionStore->load($cart->posSessionId());
            $context = $exception->context();

            if ($latest !== null) {
                $context['cart'] = $this->project($latest)->toArray();
            }

            throw Phase01Exception::withCode(
                Phase01ErrorCodes::CART_REVISION_CONFLICT,
                $exception->getMessage(),
                $context
            );
        }
    }

    private function buildCartItem(array $input, ?CartItem $currentItem = null): CartItem
    {
        $productId = array_key_exists('product_id', $input)
            ? (int) $input['product_id']
            : ($currentItem !== null ? $currentItem->productId() : 0);
        $product = $this->productService->getById($productId)->toArray();

        if (empty($product['is_in_stock']) || empty($product['is_purchasable'])) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::OUT_OF_STOCK,
                'Product is not available.',
                ['product_id' => $productId]
            );
        }

        $variationId = array_key_exists('variation_id', $input)
            ? (int) $input['variation_id']
            : ($currentItem !== null ? $currentItem->variationId() : 0);
        $variation = null;
        $priceMinor = (int) ($product['price_minor'] ?? 0);

        if (! empty($product['is_variable'])) {
            if ($variationId <= 0) {
                throw Phase01Exception::withCode(
                    Phase01ErrorCodes::INVALID_VARIATION,
                    'A variation is required for this product.',
                    ['product_id' => $productId]
                );
            }

            $variation = $this->variationService->findById($variationId)->toArray();

            if ((int) ($variation['product_id'] ?? 0) !== $productId) {
                throw Phase01Exception::withCode(
                    Phase01ErrorCodes::INVALID_VARIATION,
                    'Variation does not belong to the selected product.',
                    ['product_id' => $productId, 'variation_id' => $variationId]
                );
            }

            if (empty($variation['is_available'])) {
                throw Phase01Exception::withCode(
                    Phase01ErrorCodes::OUT_OF_STOCK,
                    'Selected variation is unavailable.',
                    ['product_id' => $productId, 'variation_id' => $variationId]
                );
            }

            $priceMinor = (int) ($variation['price_minor'] ?? 0);
        } elseif ($variationId !== 0) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_VARIATION,
                'Simple products cannot use a variation id.',
                ['product_id' => $productId, 'variation_id' => $variationId]
            );
        }

        $modifiers = array_key_exists('modifiers', $input)
            ? (array) $input['modifiers']
            : ($currentItem !== null ? $currentItem->modifierSelection()->groups() : []);
        $quickNotes = array_key_exists('quick_notes', $input)
            ? (array) $input['quick_notes']
            : ($currentItem !== null ? $currentItem->quickNoteSelection()->notes() : []);
        $selections = $this->configurationService->validateSelections($product, $modifiers, $quickNotes);
        $quantity = array_key_exists('quantity', $input)
            ? (int) $input['quantity']
            : ($currentItem !== null ? $currentItem->quantity() : 1);
        $customNote = array_key_exists('custom_note', $input)
            ? trim((string) $input['custom_note'])
            : ($currentItem !== null ? $currentItem->customNote() : '');

        if (strlen($customNote) > 500) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Custom note is too long.'
            );
        }

        $variationAttributes = $variation !== null ? (array) ($variation['attributes'] ?? []) : [];
        $snapshot = [
            'product_name' => (string) ($product['name'] ?? ''),
            'variation_attributes' => $variationAttributes,
            'variation_summary' => $this->variationSummary($variationAttributes, (array) ($product['attributes'] ?? [])),
            'modifier_labels' => $selections['modifier_labels'],
            'quick_note_labels' => $selections['quick_note_labels'],
        ];

        return CartItem::create(
            $productId,
            $variationId,
            $quantity,
            Money::fromMinor($priceMinor, (string) ($product['currency'] ?? '')),
            ModifierSelection::fromArray($selections['modifiers']),
            QuickNoteSelection::fromArray($selections['quick_notes']),
            $customNote,
            $snapshot
        );
    }

    private function variationSummary(array $attributes, array $attributeGroups): string
    {
        $labels = [];

        foreach ($attributeGroups as $group) {
            if (! is_array($group)) {
                continue;
            }

            $name = (string) ($group['name'] ?? '');

            if ($name === '' || ! array_key_exists($name, $attributes)) {
                continue;
            }

            $value = (string) $attributes[$name];
            $valueLabel = $value;

            foreach ((array) ($group['options'] ?? []) as $option) {
                if (is_array($option) && (string) ($option['value'] ?? '') === $value) {
                    $valueLabel = (string) ($option['label'] ?? $value);
                    break;
                }
            }

            $labels[] = (string) ($group['label'] ?? $name) . ': ' . $valueLabel;
        }

        return implode(' / ', $labels);
    }

    private function findItem(Cart $cart, string $itemId): ?CartItem
    {
        foreach ($cart->items() as $item) {
            if ($item->identity() === $itemId) {
                return $item;
            }
        }

        return null;
    }

    private function project(Cart $cart): CartView
    {
        return CartView::fromDomain($cart, $this->moneyFormatter);
    }
}
