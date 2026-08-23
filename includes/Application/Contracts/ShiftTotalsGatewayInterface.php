<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface ShiftTotalsGatewayInterface
{
    public function totals(int $shiftId): array;
}
