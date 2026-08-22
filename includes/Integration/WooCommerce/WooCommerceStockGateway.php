<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\StockGatewayInterface;
use WC_Product;
use WC_Product_Variation;

final class WooCommerceStockGateway implements StockGatewayInterface
{
    public function isInStock(int $productId, int $variationId, int $quantity): bool
    {
        if ($productId <= 0 || $quantity < 1 || ! function_exists('wc_get_product')) {
            return false;
        }

        $targetProductId = $variationId > 0 ? $variationId : $productId;
        $product = wc_get_product($targetProductId);

        if (! $product instanceof WC_Product) {
            return false;
        }

        if ($variationId > 0) {
            if (! $product instanceof WC_Product_Variation) {
                return false;
            }

            if ($product->get_parent_id() !== $productId) {
                return false;
            }
        }

        if (! $product->is_in_stock()) {
            return false;
        }

        if (! method_exists($product, 'managing_stock') || ! $product->managing_stock()) {
            return true;
        }

        if (method_exists($product, 'backorders_allowed') && $product->backorders_allowed()) {
            return true;
        }

        $stockQuantity = $product->get_stock_quantity();

        if ($stockQuantity === null) {
            return true;
        }

        return (int) $stockQuantity >= $quantity;
    }
}
