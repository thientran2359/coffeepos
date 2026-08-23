<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

use CoffeePOS\Application\Projection\CartView;

interface CartReconstructorInterface
{
    public function getSession(string $posSessionId): CartView;

    public function reconstruct(string $currency, array $items): CartView;
}
