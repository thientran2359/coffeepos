<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface MoneyFormatterInterface
{
    public function format(int $amountMinor, string $currency): string;
}
