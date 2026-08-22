<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\VariationGatewayInterface;
use WC_Product;
use WC_Product_Variation;

final class WooCommerceVariationGateway implements VariationGatewayInterface
{
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

        return [
            'id' => $variation->get_id(),
            'product_id' => $variation->get_parent_id(),
            'attributes' => (array) $variation->get_attributes(),
            'price_minor' => $this->toMinor((string) $variation->get_price()),
            'currency' => (string) get_woocommerce_currency(),
            'is_available' => $variation->is_purchasable() && $variation->is_in_stock() && $isVisible,
        ];
    }

    private function toMinor(string $price): int
    {
        $decimals = function_exists('wc_get_price_decimals') ? max(0, (int) wc_get_price_decimals()) : 2;
        $normalized = preg_replace('/[^0-9\.\-]/', '', $price) ?? '0';

        if ($normalized === '' || $normalized === '-' || $normalized === '.') {
            return 0;
        }

        $isNegative = strpos($normalized, '-') === 0;
        $unsigned = ltrim($normalized, '-');
        $parts = explode('.', $unsigned, 2);
        $whole = preg_replace('/\D+/', '', $parts[0]) ?: '0';
        $fraction = isset($parts[1]) ? preg_replace('/\D+/', '', $parts[1]) : '';
        $fraction = substr(str_pad($fraction, $decimals, '0'), 0, $decimals);
        $minor = (int) ($whole . $fraction);

        return $isNegative ? -$minor : $minor;
    }
}
