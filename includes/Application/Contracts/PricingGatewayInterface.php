<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

use CoffeePOS\Domain\Cart\Cart;

interface PricingGatewayInterface
{
    public function calculate(Cart $cart, ?string $couponCode = null): array;

    public function applicableCoupons(Cart $cart): array;
}
