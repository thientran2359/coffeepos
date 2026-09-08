<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\StockManagementGatewayInterface;
use RuntimeException;
use WC_Product;

final class WooCommerceStockManagementGateway implements StockManagementGatewayInterface
{
    public function projection(int $productId): ?array
    {
        $product = $this->product($productId);

        if (! $product instanceof WC_Product || ! in_array($product->get_status(), ['publish', 'private'], true)) {
            return null;
        }

        $targets = [];

        if ($product->is_type('variable') && ! $product->managing_stock()) {
            foreach ($product->get_children() as $variationId) {
                $variation = $this->product((int) $variationId);

                if ($variation instanceof WC_Product) {
                    $targets[] = $this->mapTarget($variation);
                }
            }
        } else {
            $targets[] = $this->mapTarget($product);
        }

        if ($targets === []) {
            return null;
        }

        return [
            'product_id' => $product->get_id(),
            'product_name' => $product->get_name(),
            'targets' => $targets,
        ];
    }

    public function setQuantity(int $targetId, int $quantity): array
    {
        $product = $this->requiredProduct($targetId);

        if (! $product->managing_stock() || ! function_exists('wc_update_product_stock')) {
            throw new RuntimeException('Product does not use managed stock.');
        }

        $updatedQuantity = wc_update_product_stock($product, $quantity, 'set');

        if ($updatedQuantity === false) {
            throw new RuntimeException('WooCommerce could not update product stock.');
        }
        $product = $this->requiredProduct($targetId);
        $product->set_stock_status($quantity > 0 || $product->backorders_allowed() ? 'instock' : 'outofstock');
        $product->save();

        return $this->mapTarget($this->requiredProduct($targetId));
    }

    public function setStatus(int $targetId, string $status): array
    {
        $product = $this->requiredProduct($targetId);

        if ($product->managing_stock()) {
            throw new RuntimeException('Managed stock must be updated by quantity.');
        }

        $product->set_stock_status($status);
        $product->save();

        return $this->mapTarget($this->requiredProduct($targetId));
    }

    private function product(int $productId)
    {
        return $productId > 0 && function_exists('wc_get_product') ? wc_get_product($productId) : null;
    }

    private function requiredProduct(int $productId): WC_Product
    {
        $product = $this->product($productId);

        if (! $product instanceof WC_Product) {
            throw new RuntimeException('Product is unavailable.');
        }

        return $product;
    }

    private function mapTarget(WC_Product $product): array
    {
        $quantity = $product->get_stock_quantity();
        $name = $product->get_name();

        if ($product->is_type('variation') && method_exists($product, 'get_variation_attributes')) {
            $labels = [];
            foreach ($product->get_variation_attributes() as $attribute => $value) {
                $labels[] = wc_attribute_label(str_replace('attribute_', '', (string) $attribute), $product) . ': ' . (string) $value;
            }
            if ($labels !== []) {
                $name = implode(', ', $labels);
            }
        }

        return [
            'id' => $product->get_id(),
            'name' => $name,
            'manages_quantity' => $product->managing_stock(),
            'quantity' => $quantity === null ? null : (int) $quantity,
            'stock_status' => $product->get_stock_status(),
            'is_in_stock' => $product->is_in_stock(),
        ];
    }
}
