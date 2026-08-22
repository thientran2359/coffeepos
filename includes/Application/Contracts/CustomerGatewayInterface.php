<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface CustomerGatewayInterface
{
    public function findById(int $customerId): ?array;

    public function findByPhone(string $phone): ?array;
}
