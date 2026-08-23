<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface CustomerCreationGatewayInterface extends CustomerGatewayInterface
{
    public function findByCreationOperation(string $operationId): ?array;

    public function createCustomer(array $customer, string $operationId, string $fingerprint): array;
}
