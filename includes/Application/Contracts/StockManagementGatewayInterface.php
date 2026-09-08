<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface StockManagementGatewayInterface
{
    public function projection(int $productId): ?array;

    public function setQuantity(int $targetId, int $quantity): array;

    public function setStatus(int $targetId, string $status): array;
}
