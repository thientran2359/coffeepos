<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

final class OrderQueueView
{
    public static function fromArray(array $order, bool $allowDirectCompletion = false): array
    {
        $kds = (array) ($order['kds'] ?? []);
        $state = (string) ($kds['state'] ?? 'new');
        $customer = (array) ($order['customer'] ?? []);
        $total = (array) ($order['total'] ?? []);

        return [
            'id' => (int) ($order['id'] ?? 0),
            'number' => (string) ($order['number'] ?? ''),
            'created_at' => (string) ($order['created_at'] ?? ''),
            'received_at' => (string) ($order['received_at'] ?? ''),
            'received_time' => (string) ($order['received_time'] ?? ''),
            'customer' => [
                'display_name' => (string) ($customer['display_name'] ?? 'Guest'),
                'is_guest' => ! empty($customer['is_guest']),
            ],
            'service' => (array) ($order['service'] ?? []),
            'service_label' => (string) ($order['service_label'] ?? ''),
            'total' => [
                'amount' => (string) ($total['amount'] ?? '0'),
                'currency' => (string) ($total['currency'] ?? ''),
                'display' => (string) ($total['display'] ?? ''),
            ],
            'woocommerce_status' => (string) ($order['woocommerce_status'] ?? ''),
            'kds' => [
                'state' => $state,
                'state_label' => ucfirst($state),
                'revision' => max(0, (int) ($kds['revision'] ?? 0)),
            ],
            'receipt' => ['available' => ! empty($order['receipt_available'])],
            'order_note' => (string) ($order['order_note'] ?? ''),
            'hide_order_note' => trim((string) ($order['order_note'] ?? '')) === '',
            'actions' => [
                'can_complete' => $state === 'ready' || ($allowDirectCompletion && in_array($state, ['new', 'preparing'], true)),
                'can_cancel' => (! array_key_exists('cancel_allowed', $order) || ! empty($order['cancel_allowed'])) && in_array($state, ['new', 'preparing'], true),
                'can_reprint' => ! empty($order['receipt_available']),
                'hide_complete' => $state !== 'ready' && ! ($allowDirectCompletion && in_array($state, ['new', 'preparing'], true)),
                'hide_cancel' => (array_key_exists('cancel_allowed', $order) && empty($order['cancel_allowed'])) || ! in_array($state, ['new', 'preparing'], true),
                'hide_reprint' => empty($order['receipt_available']),
            ],
        ];
    }
}
