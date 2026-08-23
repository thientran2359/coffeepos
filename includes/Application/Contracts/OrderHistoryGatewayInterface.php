<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface OrderHistoryGatewayInterface
{
    public function list(array $criteria): array;

    public function find(int $orderId): ?array;

    public function refund(int $orderId, string $amount, string $reason, string $operationId, string $fingerprint, int $userId): array;

    public function reorderItems(int $orderId): array;

    public function findReorderOperation(int $orderId, string $operationId): ?array;

    public function recordReorderOperation(int $orderId, string $operationId, string $fingerprint, string $posSessionId): void;
}
