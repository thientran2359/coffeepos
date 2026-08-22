<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Projection;

final class ProductView
{
    private int $id;

    private string $name;

    private int $priceMinor;

    private string $currency;

    private bool $variable;

    private bool $inStock;

    private string $imageUrl;

    public function __construct(
        int $id,
        string $name,
        int $priceMinor,
        string $currency,
        bool $variable,
        bool $inStock,
        string $imageUrl = ''
    ) {
        $this->id = $id;
        $this->name = trim($name);
        $this->priceMinor = $priceMinor;
        $this->currency = strtoupper(trim($currency));
        $this->variable = $variable;
        $this->inStock = $inStock;
        $this->imageUrl = trim($imageUrl);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price_minor' => $this->priceMinor,
            'currency' => $this->currency,
            'is_variable' => $this->variable,
            'is_in_stock' => $this->inStock,
            'image_url' => $this->imageUrl,
        ];
    }
}
