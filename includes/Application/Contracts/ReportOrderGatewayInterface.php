<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface ReportOrderGatewayInterface
{
    /**
     * @return iterable<array<string, mixed>>
     */
    public function orders(array $criteria): iterable;
}
