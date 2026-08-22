<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\VariationGatewayInterface;
use WC_Product;
use WC_Product_Variation;

final class WooCommerceVariationGateway implements VariationGatewayInterface
{
    private WooCommerceMoney $money;

    public function __construct(?WooCommerceMoney $money = null)
    {
        $this->money = $money ?? new WooCommerceMoney();
    }

    public function findById(int $variationId): ?array
    {
        if ($variationId <= 0 || ! function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($variationId);

        if (! $product instanceof WC_Product_Variation) {
            return null;
        }

        return $this->mapVariation($product);
    }

    public function findByProductId(int $productId): array
    {
        if ($productId <= 0 || ! function_exists('wc_get_product')) {
            return [];
        }

        $product = wc_get_product($productId);

        if (! $product instanceof WC_Product || ! $product->is_type('variable')) {
            return [];
        }

        $variations = [];

        foreach ($product->get_children() as $variationId) {
            $variation = wc_get_product((int) $variationId);

            if (! $variation instanceof WC_Product_Variation) {
                continue;
            }

            if ($variation->get_parent_id() !== $productId) {
                continue;
            }

            $variations[] = $this->mapVariation($variation);
        }

        return $variations;
    }

    private function mapVariation(WC_Product_Variation $variation): array
    {
        $isVisible = true;

        if (method_exists($variation, 'variation_is_visible')) {
            $isVisible = (bool) $variation->variation_is_visible();
        }

        $currency = $this->money->currentCurrency();
        $price = (string) $variation->get_price();

        return [
            'id' => $variation->get_id(),
            'product_id' => $variation->get_parent_id(),
            'attributes' => (array) $variation->get_attributes(),
            'price_minor' => $this->money->toMinor($price),
            'price_amount' => $this->money->amountString($price),
            'price_display' => $this->money->formatMinor($this->money->toMinor($price), $currency),
            'currency' => $currency,
            'is_available' => $variation->is_purchasable() && $variation->is_in_stock() && $isVisible,
        ];
    }
}
