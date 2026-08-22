<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface CategoryGatewayInterface
{
    public function search(array $criteria = []): array;
}
