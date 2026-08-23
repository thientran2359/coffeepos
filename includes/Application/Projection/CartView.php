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
        $discountMinor = min($subtotalMinor, $cart->paymentContext()->couponDiscountMinor());
        $totalMinor = max(0, $subtotalMinor - $discountMinor);
        $subtotalDisplay = $formatter !== null
            ? $formatter->format($subtotalMinor, $cart->currency())
            : $subtotalMinor . ' ' . $cart->currency();
        $discountDisplay = $formatter !== null
            ? $formatter->format($discountMinor, $cart->currency())
            : $discountMinor . ' ' . $cart->currency();
        $totalDisplay = $formatter !== null
            ? $formatter->format($totalMinor, $cart->currency())
            : $totalMinor . ' ' . $cart->currency();

        $payload = [
            'pos_session_id' => $cart->posSessionId(),
            'revision' => $cart->revision(),
            'updated_at' => $cart->updatedAt(),
            'state' => $cart->state(),
            'checkout_order_id' => $cart->checkoutOrderId(),
            'order_type' => $cart->orderType()->value(),
            'table' => $cart->tableContext()->toArray(),
            'customer' => CustomerView::fromDomain($cart->customerContext())->toArray(),
            'payment' => PaymentView::fromDomain($cart->paymentContext())->toArray(),
            'order_note' => $cart->orderNote(),
            'items' => $items,
            'total_quantity' => $cart->totalQuantity(),
            'subtotal_minor' => $subtotalMinor,
            'subtotal' => [
                'amount_minor' => $subtotalMinor,
                'display' => $subtotalDisplay,
            ],
            'discount' => [
                'amount_minor' => $discountMinor,
                'display' => $discountDisplay,
            ],
            'total' => [
                'amount_minor' => $totalMinor,
                'display' => $totalDisplay,
            ],
            'currency' => $cart->currency(),
            'validation' => [
                'checkout_ready' => $cart->hasItems() && $cart->state() === Cart::STATE_ACTIVE,
            ],
        ];
        $payload['customer_display'] = CustomerCartView::fromArray($payload);

        return new self($payload);
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
