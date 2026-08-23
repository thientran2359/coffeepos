<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface MembershipProviderInterface
{
    public function membershipForCustomer(array $customer): ?array;
}
