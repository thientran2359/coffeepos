<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Operations;

use CoffeePOS\Application\Contracts\OperationalOrderGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Projection\KdsOrderView;
use CoffeePOS\Application\Projection\OrderQueueView;

final class OperationalOrderService
{
    private const ACTIVE_STATES = ['new', 'preparing', 'ready'];
    private const TRANSITIONS = [
        'new' => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['completed'],
    ];

    private OperationalOrderGatewayInterface $orders;

    public function __construct(OperationalOrderGatewayInterface $orders)
    {
        $this->orders = $orders;
    }

    public function listKds(array $states, int $limit): array
    {
        $allowed = array_values(array_intersect(self::ACTIVE_STATES, $states === [] ? self::ACTIVE_STATES : $states));
        if ($allowed === []) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_ORDER_STATE, 'Invalid KDS state filter.');
        }
        $items = [];
        foreach ($this->orders->listActive($this->limit($limit)) as $order) {
            if (in_array((string) ($order['kds']['state'] ?? ''), $allowed, true)) {
                $items[] = KdsOrderView::fromArray($order);
            }
        }
        return $items;
    }

    public function listQueue(string $state, string $orderType, int $limit): array
    {
        if ($state !== 'all' && ! in_array($state, self::ACTIVE_STATES, true)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_ORDER_STATE, 'Invalid queue state filter.');
        }
        if ($orderType !== 'all' && ! in_array($orderType, ['dine_in', 'takeaway'], true)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_ORDER_TYPE, 'Invalid queue order type filter.');
        }
        $items = [];
        foreach ($this->orders->listActive($this->limit($limit)) as $order) {
            $currentState = (string) ($order['kds']['state'] ?? '');
            $currentType = (string) ($order['service']['order_type'] ?? 'takeaway');
            if (($state === 'all' || $state === $currentState) && ($orderType === 'all' || $orderType === $currentType)) {
                $items[] = OrderQueueView::fromArray($order);
            }
        }
        return $items;
    }

    public function transition(int $orderId, string $expectedState, int $expectedRevision, string $targetState, string $operationId, int $userId, string $surface, string $reason = ''): array
    {
        if ($orderId <= 0) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_NOT_FOUND, 'Order was not found.');
        }
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $operationId)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::DUPLICATE_OPERATION_CONFLICT, 'A valid client operation id is required.');
        }
        $fingerprint = hash('sha256', implode('|', [$orderId, $expectedState, $expectedRevision, $targetState, trim($reason)]));

        return $this->withLock($orderId, function () use ($orderId, $expectedState, $expectedRevision, $targetState, $operationId, $fingerprint, $userId, $surface, $reason): array {
            $current = $this->orders->find($orderId);
            if ($current === null || empty($current['eligible'])) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_NOT_FOUND, 'Operational order was not found.');
            }
            $operations = array_values((array) ($current['_operations'] ?? []));
            foreach ($operations as $operation) {
                if (($operation['operation_id'] ?? '') !== $operationId) {
                    continue;
                }
                if (($operation['fingerprint'] ?? '') !== $fingerprint) {
                    throw Phase01Exception::withCode(Phase01ErrorCodes::DUPLICATE_OPERATION_CONFLICT, 'Operation id was already used with different input.');
                }
                return $this->result($current);
            }

            $state = (string) ($current['kds']['state'] ?? 'new');
            $revision = (int) ($current['kds']['revision'] ?? 0);
            if ($state !== $expectedState || $revision !== $expectedRevision) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_STATE_CONFLICT, 'Order state changed on another screen.', ['order' => KdsOrderView::fromArray($current)]);
            }
            if (! isset(self::TRANSITIONS[$state]) || ! in_array($targetState, self::TRANSITIONS[$state], true)) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_ORDER_STATE, 'The requested order transition is not allowed.', ['order' => KdsOrderView::fromArray($current)]);
            }

            $operations[] = [
                'operation_id' => $operationId,
                'fingerprint' => $fingerprint,
                'target_state' => $targetState,
                'result_revision' => $revision + 1,
                'completed_at' => gmdate('c'),
            ];
            $operations = array_slice($operations, -8);
            $saved = $this->orders->saveTransition($orderId, $revision, $state, [
                'state' => $targetState,
                'revision' => $revision + 1,
                'timestamp' => gmdate('c'),
                'operations' => $operations,
                'user_id' => max(0, $userId),
                'surface' => in_array($surface, ['kds', 'order_queue'], true) ? $surface : 'kds',
                'reason' => trim($reason),
            ]);
            return $this->result($saved);
        });
    }

    private function result(array $order): array
    {
        return ['order' => KdsOrderView::fromArray($order), 'queue_order' => in_array((string) ($order['kds']['state'] ?? ''), self::ACTIVE_STATES, true) ? OrderQueueView::fromArray($order) : null];
    }

    private function limit(int $limit): int
    {
        return max(1, min(200, $limit > 0 ? $limit : 100));
    }

    private function withLock(int $orderId, callable $callback): array
    {
        global $wpdb;
        if (! is_object($wpdb) || ! method_exists($wpdb, 'get_var')) {
            return $callback();
        }
        $name = 'coffeepos:kds:' . $orderId;
        $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $name)) === 1;
        if (! $locked) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_STATE_CONFLICT, 'Order is being updated on another screen.');
        }
        try {
            return $callback();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
        }
    }
}
