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
    public const STATE_ACTIVE = 'active';
    public const STATE_CHECKOUT = 'checkout';
    public const STATE_COMPLETED = 'completed';

    private string $currency;

    private OrderType $orderType;

    private TableContext $tableContext;

    private CustomerContext $customerContext;

    private PaymentContext $paymentContext;

    private array $items = [];

    private string $posSessionId;

    private int $revision;

    private string $updatedAt;

    private string $state = self::STATE_ACTIVE;

    private int $checkoutOrderId = 0;

    private string $orderNote = '';

    private function __construct(string $currency, string $posSessionId = '', int $revision = 0, string $updatedAt = '')
    {
        $this->currency = Money::zero($currency)->currency();
        $this->orderType = OrderType::takeaway();
        $this->tableContext = TableContext::none();
        $this->customerContext = CustomerContext::guest();
        $this->paymentContext = PaymentContext::none();
        $this->posSessionId = trim($posSessionId);
        $this->revision = max(0, $revision);
        $this->updatedAt = trim($updatedAt);
    }

    public static function create(string $currency): self
    {
        return new self($currency);
    }

    public static function createSession(string $currency, string $posSessionId, string $updatedAt): self
    {
        if (trim($posSessionId) === '') {
            throw new \InvalidArgumentException('POS session id cannot be empty.');
        }

        return new self($currency, $posSessionId, 0, $updatedAt);
    }

    public static function restoreSession(string $currency, string $posSessionId, int $revision, string $updatedAt): self
    {
        if (trim($posSessionId) === '' || $revision < 0) {
            throw new \InvalidArgumentException('Invalid POS session metadata.');
        }

        return new self($currency, $posSessionId, $revision, $updatedAt);
    }

    public function posSessionId(): string
    {
        return $this->posSessionId;
    }

    public function revision(): int
    {
        return $this->revision;
    }

    public function updatedAt(): string
    {
        return $this->updatedAt;
    }

    public function state(): string
    {
        return $this->state;
    }

    public function checkoutOrderId(): int
    {
        return $this->checkoutOrderId;
    }

    public function restoreCheckoutState(string $state, int $orderId = 0): void
    {
        if (! in_array($state, [self::STATE_ACTIVE, self::STATE_CHECKOUT, self::STATE_COMPLETED], true)) {
            throw new \InvalidArgumentException('Invalid cart state.');
        }
        $this->state = $state;
        $this->checkoutOrderId = max(0, $orderId);
    }

    public function beginCheckout(int $orderId): void
    {
        if ($this->state !== self::STATE_ACTIVE || $orderId <= 0) {
            throw new \InvalidArgumentException('Cart cannot enter checkout.');
        }
        $this->state = self::STATE_CHECKOUT;
        $this->checkoutOrderId = $orderId;
    }

    public function completeCheckout(): void
    {
        if ($this->state !== self::STATE_CHECKOUT) {
            throw new \InvalidArgumentException('Cart is not in checkout.');
        }
        $this->state = self::STATE_COMPLETED;
    }

    public function advanceRevision(string $updatedAt): void
    {
        if ($this->posSessionId === '') {
            throw new \InvalidArgumentException('Cannot revise a cart without a POS session id.');
        }

        $this->revision++;
        $this->updatedAt = trim($updatedAt);
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

    public function orderNote(): string
    {
        return $this->orderNote;
    }

    public function setOrderNote(string $orderNote): void
    {
        $length = function_exists('mb_strlen') ? mb_strlen($orderNote) : strlen($orderNote);

        if ($length > 2000) {
            throw new \InvalidArgumentException('Order note is too long.');
        }

        $this->orderNote = trim($orderNote);
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

        $this->orderType = $orderType;
        $this->tableContext = $nextTableContext;
    }

    public function setTableContext(TableContext $tableContext): void
    {
        if ($this->orderType->isTakeaway() && $tableContext->hasTable()) {
            throw new \InvalidArgumentException('Takeaway orders cannot retain table context.');
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
        $this->orderNote = '';
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
