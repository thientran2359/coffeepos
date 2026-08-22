<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

use CoffeePOS\Domain\Cart\Cart;

final class CartView
{
    private array $payload;

    private function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public static function fromDomain(Cart $cart): self
    {
        $items = [];

        foreach ($cart->items() as $item) {
            $items[] = CartItemView::fromDomain($item)->toArray();
        }

        return new self([
            'order_type' => $cart->orderType()->value(),
            'table' => $cart->tableContext()->toArray(),
            'customer' => CustomerView::fromDomain($cart->customerContext())->toArray(),
            'payment' => PaymentView::fromDomain($cart->paymentContext())->toArray(),
            'items' => $items,
            'total_quantity' => $cart->totalQuantity(),
            'subtotal_minor' => $cart->subtotal()->amountMinor(),
            'currency' => $cart->currency(),
        ]);
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
