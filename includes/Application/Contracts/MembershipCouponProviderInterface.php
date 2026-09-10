<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface MembershipCouponProviderInterface
{
    public function preferredCouponForMembership(?array $membership): string;
}
