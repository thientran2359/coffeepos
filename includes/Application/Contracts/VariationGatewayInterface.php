<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface VariationGatewayInterface
{
    public function findById(int $variationId): ?array;

    public function findByProductId(int $productId): array;
}
