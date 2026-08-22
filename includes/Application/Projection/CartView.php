<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

use CoffeePOS\Application\Contracts\MoneyFormatterInterface;
use CoffeePOS\Domain\Cart\Cart;

final class CartView
{
    private array $payload;

    private function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    public static function fromDomain(Cart $cart, ?MoneyFormatterInterface $formatter = null): self
    {
        $items = [];

        foreach ($cart->items() as $item) {
            $items[] = CartItemView::fromDomain($item, $formatter)->toArray();
        }

        $subtotalMinor = $cart->subtotal()->amountMinor();
        $subtotalDisplay = $formatter !== null
            ? $formatter->format($subtotalMinor, $cart->currency())
            : $subtotalMinor . ' ' . $cart->currency();
        $zeroDisplay = $formatter !== null
            ? $formatter->format(0, $cart->currency())
            : '0 ' . $cart->currency();

        return new self([
            'pos_session_id' => $cart->posSessionId(),
            'revision' => $cart->revision(),
            'updated_at' => $cart->updatedAt(),
            'order_type' => $cart->orderType()->value(),
            'table' => $cart->tableContext()->toArray(),
            'customer' => CustomerView::fromDomain($cart->customerContext())->toArray(),
            'payment' => PaymentView::fromDomain($cart->paymentContext())->toArray(),
            'items' => $items,
            'total_quantity' => $cart->totalQuantity(),
            'subtotal_minor' => $subtotalMinor,
            'subtotal' => [
                'amount_minor' => $subtotalMinor,
                'display' => $subtotalDisplay,
            ],
            'discount' => [
                'amount_minor' => 0,
                'display' => $zeroDisplay,
            ],
            'total' => [
                'amount_minor' => $subtotalMinor,
                'display' => $subtotalDisplay,
            ],
            'currency' => $cart->currency(),
            'validation' => [
                'checkout_ready' => $cart->hasItems(),
            ],
        ]);
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
