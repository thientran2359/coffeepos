<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\ProductGatewayInterface;
use WC_Product;

final class WooCommerceProductGateway implements ProductGatewayInterface
{
    private WooCommerceMoney $money;

    public function __construct(?WooCommerceMoney $money = null)
    {
        $this->money = $money ?? new WooCommerceMoney();
    }

    public function findById(int $productId): ?array
    {
        if ($productId <= 0 || ! function_exists('wc_get_product')) {
            return null;
        }

        $product = wc_get_product($productId);

        if (! $product instanceof WC_Product) {
            return null;
        }

        return $this->mapProduct($product, true);
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
            'visibility' => 'catalog',
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

            $result[] = $this->mapProduct($product, false);
        }

        return $result;
    }

    private function mapProduct(WC_Product $product, bool $includeAttributes): array
    {
        $currency = $this->money->currentCurrency();
        $price = (string) $product->get_price();

        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'type' => $product->get_type(),
            'price_minor' => $this->money->toMinor($price),
            'price_amount' => $this->money->amountString($price),
            'price_display' => $this->money->formatMinor($this->money->toMinor($price), $currency),
            'currency' => $currency,
            'is_variable' => $product->is_type('variable'),
            'is_in_stock' => $product->is_in_stock(),
            'is_purchasable' => $product->is_purchasable(),
            'image_url' => (string) wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
            'category_ids' => array_map('intval', $product->get_category_ids()),
            'menu_order' => (int) $product->get_menu_order(),
            'badge_label' => '',
            'attributes' => $includeAttributes ? $this->mapVariationAttributes($product) : [],
        ];
    }

    private function mapVariationAttributes(WC_Product $product): array
    {
        if (! $product->is_type('variable') || ! method_exists($product, 'get_variation_attributes')) {
            return [];
        }

        $groups = [];

        foreach ((array) $product->get_variation_attributes() as $attributeName => $options) {
            $name = preg_replace('/^attribute_/', '', (string) $attributeName) ?? (string) $attributeName;
            $optionViews = [];

            foreach ((array) $options as $option) {
                $value = (string) $option;
                $label = $value;

                if (taxonomy_exists($name)) {
                    $term = get_term_by('slug', $value, $name);

                    if ($term instanceof \WP_Term) {
                        $label = (string) $term->name;
                    }
                }

                $optionViews[] = ['value' => $value, 'label' => $label];
            }

            $groups[] = [
                'name' => $name,
                'label' => function_exists('wc_attribute_label') ? wc_attribute_label($name, $product) : $name,
                'options' => $optionViews,
            ];
        }

        return $groups;
    }
}
