<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface CouponGatewayInterface
{
    public function validate(string $couponCode, array $context = []): array;
}
