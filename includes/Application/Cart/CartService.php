<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Cart;

use CoffeePOS\Application\Contracts\StockGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Projection\CartView;
use CoffeePOS\Domain\Cart\Cart;
use CoffeePOS\Domain\Cart\CartItem;
use CoffeePOS\Domain\Customer\CustomerContext;
use CoffeePOS\Domain\Order\OrderType;
use CoffeePOS\Domain\Order\TableContext;
use CoffeePOS\Domain\Payment\PaymentContext;

final class CartService
{
    private CartValidationService $cartValidationService;

    private ?StockGatewayInterface $stockGateway;

    public function __construct(?CartValidationService $cartValidationService = null, ?StockGatewayInterface $stockGateway = null)
    {
        $this->cartValidationService = $cartValidationService ?? new CartValidationService();
        $this->stockGateway = $stockGateway;
    }

    public function createCart(string $currency): Cart
    {
        return Cart::create($currency);
    }

    public function addItem(Cart $cart, CartItem $cartItem): CartView
    {
        $this->assertStock($cartItem->productId(), $cartItem->variationId(), $cartItem->quantity());

        try {
            $cart->addItem($cartItem);
            $this->cartValidationService->validateCart($cart);
        } catch (\InvalidArgumentException $exception) {
            throw $this->mapDomainException($exception);
        }

        return CartView::fromDomain($cart);
    }

    public function updateItemQuantity(Cart $cart, string $itemId, int $quantity): CartView
    {
        $this->cartValidationService->validateQuantity($quantity);

        $cartItem = $this->findCartItemById($cart, $itemId);

        if ($cartItem !== null) {
            $this->assertStock($cartItem->productId(), $cartItem->variationId(), $quantity);
        }

        try {
            $cart->updateItemQuantity($itemId, $quantity);
            $this->cartValidationService->validateCart($cart);
        } catch (\InvalidArgumentException $exception) {
            throw $this->mapDomainException($exception);
        }

        return CartView::fromDomain($cart);
    }

    public function replaceItem(Cart $cart, string $itemId, CartItem $replacementItem): CartView
    {
        $this->assertStock($replacementItem->productId(), $replacementItem->variationId(), $replacementItem->quantity());

        try {
            $cart->replaceItem($itemId, $replacementItem);
            $this->cartValidationService->validateCart($cart);
        } catch (\InvalidArgumentException $exception) {
            throw $this->mapDomainException($exception);
        }

        return CartView::fromDomain($cart);
    }

    public function removeItem(Cart $cart, string $itemId): CartView
    {
        $cart->removeItem($itemId);

        return CartView::fromDomain($cart);
    }

    public function clear(Cart $cart): CartView
    {
        $cart->clearItems();

        return CartView::fromDomain($cart);
    }

    public function setOrderType(Cart $cart, string $orderType, ?TableContext $tableContext = null): CartView
    {
        try {
            $resolvedOrderType = OrderType::fromString($orderType);
        } catch (\InvalidArgumentException $exception) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_ORDER_TYPE,
                'Invalid order type.',
                ['order_type' => $orderType]
            );
        }

        if ($resolvedOrderType->isDineIn()) {
            $resolvedTableContext = $tableContext ?? $cart->tableContext();
            $this->cartValidationService->validateOrderTypeAndTable($resolvedOrderType, $resolvedTableContext);
            $cart->setOrderType($resolvedOrderType, $resolvedTableContext);
        } else {
            $cart->setOrderType($resolvedOrderType, null);
        }

        return CartView::fromDomain($cart);
    }

    public function setTableContext(Cart $cart, TableContext $tableContext): CartView
    {
        $this->cartValidationService->validateOrderTypeAndTable($cart->orderType(), $tableContext);
        $cart->setTableContext($tableContext);

        return CartView::fromDomain($cart);
    }

    public function setCustomerContext(Cart $cart, CustomerContext $customerContext): CartView
    {
        $cart->setCustomerContext($customerContext);

        return CartView::fromDomain($cart);
    }

    public function setPaymentContext(Cart $cart, PaymentContext $paymentContext): CartView
    {
        $cart->setPaymentContext($paymentContext);

        return CartView::fromDomain($cart);
    }

    public function project(Cart $cart): CartView
    {
        return CartView::fromDomain($cart);
    }

    public function validate(Cart $cart): CartView
    {
        $this->cartValidationService->validateCart($cart);

        return CartView::fromDomain($cart);
    }

    private function mapDomainException(\InvalidArgumentException $exception): Phase01Exception
    {
        $message = $exception->getMessage();

        if (stripos($message, 'Quantity') !== false) {
            return Phase01Exception::withCode(Phase01ErrorCodes::INVALID_QUANTITY, $message);
        }

        if (stripos($message, 'Dine-in') !== false) {
            return Phase01Exception::withCode(Phase01ErrorCodes::TABLE_REQUIRED, $message);
        }

        if (stripos($message, 'Takeaway') !== false) {
            return Phase01Exception::withCode(Phase01ErrorCodes::TABLE_NOT_ALLOWED, $message);
        }

        return Phase01Exception::withCode(Phase01ErrorCodes::INVALID_CONFIGURATION, $message);
    }

    private function assertStock(int $productId, int $variationId, int $quantity): void
    {
        if ($this->stockGateway === null) {
            return;
        }

        if ($this->stockGateway->isInStock($productId, $variationId, $quantity)) {
            return;
        }

        throw Phase01Exception::withCode(
            Phase01ErrorCodes::OUT_OF_STOCK,
            'Requested item quantity is out of stock.',
            [
                'product_id' => $productId,
                'variation_id' => $variationId,
                'quantity' => $quantity,
            ]
        );
    }

    private function findCartItemById(Cart $cart, string $itemId): ?CartItem
    {
        foreach ($cart->items() as $item) {
            if ($item->identity() === $itemId) {
                return $item;
            }
        }

        return null;
    }
}
