<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

use CoffeePOS\Domain\Cart\Cart;

interface CartSnapshotSerializerInterface
{
    public function toPayload(Cart $cart): array;
}
