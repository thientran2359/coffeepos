<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

final class KdsOrderView
{
    public static function fromArray(array $order): array
    {
        $kds = (array) ($order['kds'] ?? []);
        $state = (string) ($kds['state'] ?? 'new');
        $actions = [
            'new' => ['target_state' => 'preparing', 'label' => 'Start'],
            'preparing' => ['target_state' => 'ready', 'label' => 'Ready'],
            'ready' => ['target_state' => 'completed', 'label' => 'Complete'],
        ];

        return [
            'id' => (int) ($order['id'] ?? 0),
            'number' => (string) ($order['number'] ?? ''),
            'received_at' => (string) ($order['received_at'] ?? ''),
            'received_time' => (string) ($order['received_time'] ?? ''),
            'service' => (array) ($order['service'] ?? []),
            'service_label' => (string) ($order['service_label'] ?? ''),
            'kds' => [
                'state' => $state,
                'state_label' => ucfirst($state),
                'revision' => max(0, (int) ($kds['revision'] ?? 0)),
                'started_at' => $kds['started_at'] ?? null,
                'ready_at' => $kds['ready_at'] ?? null,
                'completed_at' => $kds['completed_at'] ?? null,
            ],
            'items' => array_values((array) ($order['items'] ?? [])),
            'order_note' => (string) ($order['order_note'] ?? ''),
            'next_action' => $actions[$state] ?? ['target_state' => '', 'label' => ''],
            'has_action' => isset($actions[$state]),
            'hide_order_note' => trim((string) ($order['order_note'] ?? '')) === '',
        ];
    }
}
