<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

use CoffeePOS\Domain\Cart\Cart;

interface CartSessionStoreInterface
{
    public function create(string $currency): Cart;

    public function load(string $posSessionId): ?Cart;

    public function save(Cart $cart, int $expectedRevision): Cart;
}
