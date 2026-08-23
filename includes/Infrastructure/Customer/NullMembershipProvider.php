<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Customer;

use CoffeePOS\Application\Contracts\MembershipProviderInterface;

final class NullMembershipProvider implements MembershipProviderInterface
{
    public function membershipForCustomer(array $customer): ?array
    {
        return null;
    }
}
