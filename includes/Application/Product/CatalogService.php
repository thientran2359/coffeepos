<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Product;

use CoffeePOS\Application\Projection\CatalogView;

final class CatalogService
{
    private CategoryService $categoryService;

    private ProductService $productService;

    public function __construct(CategoryService $categoryService, ProductService $productService)
    {
        $this->categoryService = $categoryService;
        $this->productService = $productService;
    }

    public function load(string $currency): CatalogView
    {
        $categories = $this->categoryService->search([
            'limit' => -1,
            'hide_empty' => true,
        ]);
        $products = [];

        foreach ($this->productService->search(['limit' => -1]) as $productView) {
            $products[] = $productView->toArray();
        }

        usort($products, static function (array $left, array $right): int {
            $order = ((int) ($left['menu_order'] ?? 0)) <=> ((int) ($right['menu_order'] ?? 0));

            return $order !== 0 ? $order : (((int) $left['id']) <=> ((int) $right['id']));
        });

        $groupedCategories = [];

        foreach ($categories as $category) {
            $categoryProducts = [];
            $categoryId = (int) $category['id'];

            foreach ($products as $product) {
                $categoryIds = array_map('intval', (array) ($product['category_ids'] ?? []));

                if (! in_array($categoryId, $categoryIds, true)) {
                    continue;
                }

                $categoryProducts[] = [
                    'id' => (int) $product['id'],
                    'occurrence_key' => $categoryId . ':' . (int) $product['id'],
                    'name' => (string) $product['name'],
                    'type' => (string) ($product['type'] ?? 'simple'),
                    'price_amount' => (string) ($product['price_amount'] ?? '0'),
                    'price_display' => (string) ($product['price_display'] ?? ''),
                    'image_url' => (string) ($product['image_url'] ?? ''),
                    'is_variable' => (bool) ($product['is_variable'] ?? false),
                    'is_in_stock' => (bool) ($product['is_in_stock'] ?? false),
                    'is_purchasable' => (bool) ($product['is_purchasable'] ?? false),
                    'badge_label' => (string) ($product['badge_label'] ?? ''),
                ];
            }

            if ($categoryProducts === []) {
                continue;
            }

            $groupedCategories[] = [
                'id' => $categoryId,
                'slug' => (string) $category['slug'],
                'name' => (string) $category['name'],
                'sort_order' => (int) $category['sort_order'],
                'products' => $categoryProducts,
            ];
        }

        $versionPayload = json_encode($groupedCategories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $version = hash('sha256', is_string($versionPayload) ? $versionPayload : '[]');

        return new CatalogView($version, $currency, $groupedCategories);
    }
}
