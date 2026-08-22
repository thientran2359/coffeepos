<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\MoneyFormatterInterface;

final class WooCommerceMoneyFormatter implements MoneyFormatterInterface
{
    private WooCommerceMoney $money;

    public function __construct(?WooCommerceMoney $money = null)
    {
        $this->money = $money ?? new WooCommerceMoney();
    }

    public function format(int $amountMinor, string $currency): string
    {
        return $this->money->formatMinor($amountMinor, $currency);
    }
}
