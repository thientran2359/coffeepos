<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

final class CustomerCartView
{
    public static function fromArray(array $cart): array
    {
        $items = [];
        foreach ((array) ($cart['items'] ?? []) as $item) {
            if (! is_array($item)) {
                continue;
            }
            $items[] = [
                'item_id' => (string) ($item['item_id'] ?? ''),
                'product_name' => (string) ($item['product_name'] ?? ''),
                'variation_summary' => (string) ($item['variation_summary'] ?? ''),
                'modifier_summary' => (string) ($item['modifier_summary'] ?? ''),
                'quantity' => max(0, (int) ($item['quantity'] ?? 0)),
                'unit_price' => self::money((array) ($item['unit_price'] ?? [])),
                'unit_price_display' => (string) ($item['unit_price_display'] ?? ''),
                'line_total' => self::money((array) ($item['line_total'] ?? [])),
                'line_total_display' => (string) ($item['line_total_display'] ?? ''),
            ];
        }

        $customer = (array) ($cart['customer'] ?? []);
        $membership = is_array($customer['membership'] ?? null) ? $customer['membership'] : null;
        return [
            'pos_session_id' => (string) ($cart['pos_session_id'] ?? ''),
            'revision' => max(0, (int) ($cart['revision'] ?? 0)),
            'state' => (string) ($cart['state'] ?? 'active'),
            'order_type' => (string) ($cart['order_type'] ?? 'takeaway'),
            'table' => [
                'table_id' => isset($cart['table']['table_id']) ? (int) $cart['table']['table_id'] : null,
                'table_label' => (string) ($cart['table']['table_label'] ?? ''),
            ],
            'customer' => [
                'is_guest' => ! empty($customer['is_guest']),
                'mode' => ! empty($customer['is_guest']) ? 'guest' : 'member',
                'display_name' => (string) ($customer['display_name'] ?? ''),
                'phone_masked' => (string) ($customer['phone_masked'] ?? ''),
                'membership' => $membership,
            ],
            'items' => $items,
            'total_quantity' => max(0, (int) ($cart['total_quantity'] ?? 0)),
            'subtotal' => self::money((array) ($cart['subtotal'] ?? [])),
            'discount' => self::money((array) ($cart['discount'] ?? [])),
            'total' => self::money((array) ($cart['total'] ?? [])),
            'currency' => (string) ($cart['currency'] ?? ''),
        ];
    }

    private static function money(array $value): array
    {
        return [
            'amount_minor' => (int) ($value['amount_minor'] ?? 0),
            'display' => (string) ($value['display'] ?? ''),
        ];
    }
}
