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

    private string $type;

    private string $priceAmount;

    private string $priceDisplay;

    private bool $purchasable;

    private array $categoryIds;

    private int $menuOrder;

    private string $badgeLabel;

    private array $attributes;

    public function __construct(
        int $id,
        string $name,
        int $priceMinor,
        string $currency,
        bool $variable,
        bool $inStock,
        string $imageUrl = '',
        string $type = 'simple',
        string $priceAmount = '0',
        string $priceDisplay = '',
        bool $purchasable = true,
        array $categoryIds = [],
        int $menuOrder = 0,
        string $badgeLabel = '',
        array $attributes = []
    ) {
        $this->id = $id;
        $this->name = trim($name);
        $this->priceMinor = $priceMinor;
        $this->currency = strtoupper(trim($currency));
        $this->variable = $variable;
        $this->inStock = $inStock;
        $this->imageUrl = trim($imageUrl);
        $this->type = trim($type) === '' ? 'simple' : trim($type);
        $this->priceAmount = trim($priceAmount) === '' ? '0' : trim($priceAmount);
        $this->priceDisplay = trim($priceDisplay);
        $this->purchasable = $purchasable;
        $this->categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        $this->menuOrder = $menuOrder;
        $this->badgeLabel = trim($badgeLabel);
        $this->attributes = array_values($attributes);
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
            'type' => $this->type,
            'price_amount' => $this->priceAmount,
            'price_display' => $this->priceDisplay,
            'is_purchasable' => $this->purchasable,
            'category_ids' => $this->categoryIds,
            'menu_order' => $this->menuOrder,
            'badge_label' => $this->badgeLabel,
            'attributes' => $this->attributes,
        ];
    }
}
