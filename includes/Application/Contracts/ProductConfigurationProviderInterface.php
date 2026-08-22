<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Contracts;

interface ProductConfigurationProviderInterface
{
    public function configurationForProduct(array $product): array;
}
