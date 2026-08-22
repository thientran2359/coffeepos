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

    private string $priceAmount;

    private string $priceDisplay;

    public function __construct(
        int $id,
        int $productId,
        array $attributes,
        int $priceMinor,
        string $currency,
        bool $available,
        string $priceAmount = '0',
        string $priceDisplay = ''
    ) {
        $this->id = $id;
        $this->productId = $productId;
        $this->attributes = $attributes;
        $this->priceMinor = $priceMinor;
        $this->currency = strtoupper(trim($currency));
        $this->available = $available;
        $this->priceAmount = trim($priceAmount) === '' ? '0' : trim($priceAmount);
        $this->priceDisplay = trim($priceDisplay);
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
            'price_amount' => $this->priceAmount,
            'price_display' => $this->priceDisplay,
        ];
    }
}
