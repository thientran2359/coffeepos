<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

use CoffeePOS\Domain\Cart\Cart;

interface OrderGatewayInterface
{
    public function findByOperation(string $operationId): ?array;

    public function create(Cart $cart, array $pricing, array $context): array;

    public function project(int $orderId): array;

    public function receipt(int $orderId): array;

    public function setNextSessionId(int $orderId, string $posSessionId): void;
}
