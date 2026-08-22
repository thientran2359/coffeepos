<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\ProductGatewayInterface;
use WC_Product;

final class WooCommerceProductGateway implements ProductGatewayInterface
{
    public function findById(int $productId): ?array
    {
        if ($productId <= 0 || ! function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($productId);

        if (! $product instanceof WC_Product) {
            return null;
        }

        return $this->mapProduct($product);
    }

    public function search(array $criteria = []): array
    {
        if (! function_exists('wc_get_products')) {
            return [];
        }

        $query = [
            'status' => 'publish',
            'limit' => (int) ($criteria['limit'] ?? 20),
            'page' => (int) ($criteria['page'] ?? 1),
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ];

        $search = trim((string) ($criteria['search'] ?? ''));

        if ($search !== '') {
            $query['search'] = '*' . $search . '*';
        }

        $category = (int) ($criteria['category_id'] ?? 0);

        if ($category > 0) {
            $query['category'] = [(string) $category];
        }

        $products = wc_get_products($query);
        $result = [];

        foreach ($products as $product) {
            if (! $product instanceof WC_Product) {
                continue;
            }

            $result[] = $this->mapProduct($product);
        }

        return $result;
    }

    private function mapProduct(WC_Product $product): array
    {
        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price_minor' => $this->toMinor((string) $product->get_price()),
            'currency' => (string) get_woocommerce_currency(),
            'is_variable' => $product->is_type('variable'),
            'is_in_stock' => $product->is_in_stock(),
            'image_url' => (string) wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
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
