<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

final class VariationView
{
    private int $id;

    private int $productId;

    private array $attributes;

    private int $priceMinor;

    private string $currency;

    private bool $available;

    public function __construct(
        int $id,
        int $productId,
        array $attributes,
        int $priceMinor,
        string $currency,
        bool $available
    ) {
        $this->id = $id;
        $this->productId = $productId;
        $this->attributes = $attributes;
        $this->priceMinor = $priceMinor;
        $this->currency = strtoupper(trim($currency));
        $this->available = $available;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'attributes' => $this->attributes,
            'price_minor' => $this->priceMinor,
            'currency' => $this->currency,
            'is_available' => $this->available,
        ];
    }
}
