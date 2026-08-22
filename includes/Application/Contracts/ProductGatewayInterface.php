<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface ProductGatewayInterface
{
    public function findById(int $productId): ?array;

    public function search(array $criteria = []): array;
}
