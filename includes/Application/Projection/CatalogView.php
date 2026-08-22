<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

final class CatalogView
{
    private array $payload;

    public function __construct(string $version, string $currency, array $categories)
    {
        $this->payload = [
            'version' => $version,
            'currency' => strtoupper(trim($currency)),
            'categories' => array_values($categories),
        ];
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}
