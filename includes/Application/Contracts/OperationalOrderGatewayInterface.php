<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface OperationalOrderGatewayInterface
{
    public function listActive(int $limit): array;

    public function find(int $orderId): ?array;

    public function saveTransition(int $orderId, int $expectedRevision, string $expectedState, array $changes): array;
}
