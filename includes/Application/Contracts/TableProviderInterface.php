<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface TableProviderInterface
{
    public function listAvailable(): array;

    public function findAvailableById(int $tableId): ?array;
}
