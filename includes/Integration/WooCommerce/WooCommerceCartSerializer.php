<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Domain\Cart\Cart;
use CoffeePOS\Domain\Cart\CartItem;
use CoffeePOS\Domain\Customer\CustomerContext;
use CoffeePOS\Domain\Order\OrderType;
use CoffeePOS\Domain\Order\TableContext;
use CoffeePOS\Domain\Payment\PaymentContext;
use CoffeePOS\Domain\Product\ModifierSelection;
use CoffeePOS\Domain\Product\QuickNoteSelection;
use CoffeePOS\Domain\Shared\Money;

final class WooCommerceCartSerializer
{
    public function toPayload(Cart $cart): array
    {
        $items = [];

        foreach ($cart->items() as $item) {
            $items[] = [
                'product_id' => $item->productId(),
                'variation_id' => $item->variationId(),
                'quantity' => $item->quantity(),
                'unit_price_minor' => $item->unitPrice()->amountMinor(),
                'modifiers' => $item->modifierSelection()->groups(),
                'quick_notes' => $item->quickNoteSelection()->notes(),
                'custom_note' => $item->customNote(),
                'display_snapshot' => $item->displaySnapshot(),
            ];
        }

        return [
            'pos_session_id' => $cart->posSessionId(),
            'revision' => $cart->revision(),
            'currency' => $cart->currency(),
            'updated_at' => $cart->updatedAt(),
            'items' => $items,
            'customer' => $cart->customerContext()->toArray(),
            'order_type' => $cart->orderType()->value(),
            'table' => $cart->tableContext()->toArray(),
            'payment' => $cart->paymentContext()->toArray(),
        ];
    }

    public function fromPayload(array $payload): Cart
    {
        $cart = Cart::restoreSession(
            (string) ($payload['currency'] ?? ''),
            (string) ($payload['pos_session_id'] ?? ''),
            (int) ($payload['revision'] ?? -1),
            (string) ($payload['updated_at'] ?? '')
        );

        foreach ((array) ($payload['items'] ?? []) as $item) {
            if (! is_array($item)) {
                throw new \UnexpectedValueException('Invalid cart item payload.');
            }

            $cart->addItem(CartItem::create(
                (int) ($item['product_id'] ?? 0),
                (int) ($item['variation_id'] ?? 0),
                (int) ($item['quantity'] ?? 0),
                Money::fromMinor((int) ($item['unit_price_minor'] ?? 0), $cart->currency()),
                ModifierSelection::fromArray((array) ($item['modifiers'] ?? [])),
                QuickNoteSelection::fromArray((array) ($item['quick_notes'] ?? [])),
                (string) ($item['custom_note'] ?? ''),
                (array) ($item['display_snapshot'] ?? [])
            ));
        }

        $customer = (array) ($payload['customer'] ?? []);

        if (empty($customer['is_guest']) && (int) ($customer['customer_id'] ?? 0) > 0) {
            $cart->setCustomerContext(CustomerContext::member(
                (int) $customer['customer_id'],
                (string) ($customer['phone'] ?? ''),
                (string) ($customer['display_name'] ?? '')
            ));
        }

        $payment = (array) ($payload['payment'] ?? []);

        if (trim((string) ($payment['coupon_code'] ?? '')) !== '') {
            $cart->setPaymentContext(PaymentContext::withCoupon((string) $payment['coupon_code']));
        }

        $orderType = (string) ($payload['order_type'] ?? OrderType::TAKEAWAY);

        if ($orderType === OrderType::DINE_IN) {
            $table = (array) ($payload['table'] ?? []);
            $cart->setOrderType(OrderType::dineIn(), TableContext::from(
                isset($table['table_id']) ? (int) $table['table_id'] : null,
                (string) ($table['table_label'] ?? '')
            ));
        }

        return $cart;
    }
}
