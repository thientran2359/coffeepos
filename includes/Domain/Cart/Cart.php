<?php

declare(strict_types=1);

namespace CoffeePOS\Domain\Cart;

use CoffeePOS\Domain\Customer\CustomerContext;
use CoffeePOS\Domain\Order\OrderType;
use CoffeePOS\Domain\Order\TableContext;
use CoffeePOS\Domain\Payment\PaymentContext;
use CoffeePOS\Domain\Shared\Money;

final class Cart
{
    private string $currency;

    private OrderType $orderType;

    private TableContext $tableContext;

    private CustomerContext $customerContext;

    private PaymentContext $paymentContext;

    private array $items = [];

    private function __construct(string $currency)
    {
        $this->currency = Money::zero($currency)->currency();
        $this->orderType = OrderType::takeaway();
        $this->tableContext = TableContext::none();
        $this->customerContext = CustomerContext::guest();
        $this->paymentContext = PaymentContext::none();
    }

    public static function create(string $currency): self
    {
        return new self($currency);
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function orderType(): OrderType
    {
        return $this->orderType;
    }

    public function tableContext(): TableContext
    {
        return $this->tableContext;
    }

    public function customerContext(): CustomerContext
    {
        return $this->customerContext;
    }

    public function paymentContext(): PaymentContext
    {
        return $this->paymentContext;
    }

    public function items(): array
    {
        return array_values($this->items);
    }

    public function hasItems(): bool
    {
        return $this->items !== [];
    }

    public function setOrderType(OrderType $orderType, ?TableContext $tableContext = null): void
    {
        if ($orderType->isTakeaway()) {
            $this->orderType = $orderType;
            $this->tableContext = TableContext::none();

            return;
        }

        $nextTableContext = $tableContext ?? $this->tableContext;

        if (! $nextTableContext->hasTable()) {
            throw new \InvalidArgumentException('Dine-in orders require table context.');
        }

        $this->orderType = $orderType;
        $this->tableContext = $nextTableContext;
    }

    public function setTableContext(TableContext $tableContext): void
    {
        if ($this->orderType->isTakeaway() && $tableContext->hasTable()) {
            throw new \InvalidArgumentException('Takeaway orders cannot retain table context.');
        }

        if ($this->orderType->isDineIn() && ! $tableContext->hasTable()) {
            throw new \InvalidArgumentException('Dine-in orders require table context.');
        }

        $this->tableContext = $tableContext;
    }

    public function setCustomerContext(CustomerContext $customerContext): void
    {
        $this->customerContext = $customerContext;
    }

    public function setPaymentContext(PaymentContext $paymentContext): void
    {
        $this->paymentContext = $paymentContext;
    }

    public function addItem(CartItem $cartItem): void
    {
        $this->assertCurrency($cartItem->unitPrice());
        $itemId = $cartItem->identity();

        if (! isset($this->items[$itemId])) {
            $this->items[$itemId] = $cartItem;

            return;
        }

        $this->items[$itemId] = $this->items[$itemId]->merge($cartItem);
    }

    public function updateItemQuantity(string $itemId, int $quantity): void
    {
        if (! isset($this->items[$itemId])) {
            throw new \InvalidArgumentException('Cart item not found.');
        }

        $this->items[$itemId] = $this->items[$itemId]->withQuantity($quantity);
    }

    public function replaceItem(string $itemId, CartItem $cartItem): void
    {
        if (! isset($this->items[$itemId])) {
            throw new \InvalidArgumentException('Cart item not found.');
        }

        $this->assertCurrency($cartItem->unitPrice());
        unset($this->items[$itemId]);
        $this->addItem($cartItem);
    }

    public function removeItem(string $itemId): void
    {
        unset($this->items[$itemId]);
    }

    public function clearItems(): void
    {
        $this->items = [];
    }

    public function totalQuantity(): int
    {
        $total = 0;

        foreach ($this->items as $item) {
            $total += $item->quantity();
        }

        return $total;
    }

    public function subtotal(): Money
    {
        $subtotal = Money::zero($this->currency);

        foreach ($this->items as $item) {
            $subtotal = $subtotal->add($item->lineTotal());
        }

        return $subtotal;
    }

    private function assertCurrency(Money $money): void
    {
        if ($money->currency() !== $this->currency) {
            throw new \InvalidArgumentException('Cart item currency does not match cart currency.');
        }
    }
}
