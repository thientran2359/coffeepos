<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface StockGatewayInterface
{
    public function isInStock(int $productId, int $variationId, int $quantity): bool;
}
