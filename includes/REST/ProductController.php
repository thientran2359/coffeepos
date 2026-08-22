<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Product\CategoryService;
use CoffeePOS\Application\Product\ProductService;
use CoffeePOS\Application\Product\VariationService;
use CoffeePOS\Integration\WooCommerce\WooCommerceCategoryGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceProductGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceVariationGateway;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class ProductController
{
    private ProductService $productService;

    private VariationService $variationService;

    private CategoryService $categoryService;

    public function __construct(
        ?ProductService $productService = null,
        ?VariationService $variationService = null,
        ?CategoryService $categoryService = null
    ) {
        $this->productService = $productService ?? new ProductService(new WooCommerceProductGateway());
        $this->variationService = $variationService ?? new VariationService(new WooCommerceVariationGateway());
        $this->categoryService = $categoryService ?? new CategoryService(new WooCommerceCategoryGateway());
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/products', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'products'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
        ]);

        register_rest_route($namespace, '/products/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'productDetail'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
        ]);

        register_rest_route($namespace, '/categories', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'categories'],
                'permission_callback' => [$this, 'permissionCheck'],
            ],
        ]);
    }

    public function permissionCheck()
    {
        if (Capabilities::currentUserCanAccessPos()) {
            return true;
        }

        return ErrorFactory::forbidden(
            'coffeepos_rest_forbidden',
            __('You are not allowed to access CoffeePOS REST endpoints.', 'coffeepos')
        );
    }

    public function products(WP_REST_Request $request)
    {
        try {
            $page = max(1, (int) $request->get_param('page'));
            $perPage = max(1, min(100, (int) $request->get_param('per_page')));

            if ($perPage === 1 && ! $request->has_param('per_page')) {
                $perPage = 30;
            }

            $criteria = [
                'page' => $page,
                'limit' => $perPage,
            ];

            $search = trim((string) $request->get_param('search'));

            if ($search !== '') {
                $criteria['search'] = $search;
            }

            $categoryId = (int) $request->get_param('category');

            if ($categoryId > 0) {
                $criteria['category_id'] = $categoryId;
            }

            $productViews = $this->productService->search($criteria);
            $items = [];

            foreach ($productViews as $productView) {
                $items[] = $productView->toArray();
            }

            $total = count($items);

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'items' => $items,
                    'pagination' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'total' => $total,
                        'pages' => $total === 0 ? 0 : max(1, $page),
                    ],
                ],
            ]);
        } catch (\Throwable $throwable) {
            return $this->mapError($throwable);
        }
    }

    public function productDetail(WP_REST_Request $request)
    {
        try {
            $productId = (int) $request->get_param('id');
            $product = $this->productService->getById($productId)->toArray();

            $variations = [];

            if ((bool) ($product['is_variable'] ?? false)) {
                $variationViews = $this->variationService->listByProductId($productId);

                foreach ($variationViews as $variationView) {
                    $variations[] = $variationView->toArray();
                }
            }

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'product' => $product,
                    'attributes' => $this->buildAttributeMap($variations),
                    'variations' => $variations,
                    'modifier_configuration' => [],
                    'quick_notes_configuration' => [],
                ],
            ]);
        } catch (\Throwable $throwable) {
            return $this->mapError($throwable);
        }
    }

    public function categories(WP_REST_Request $request)
    {
        try {
            $page = max(1, (int) $request->get_param('page'));
            $perPage = max(1, min(100, (int) $request->get_param('per_page')));

            if ($perPage === 1 && ! $request->has_param('per_page')) {
                $perPage = 50;
            }

            $criteria = [
                'page' => $page,
                'limit' => $perPage,
            ];

            $search = trim((string) $request->get_param('search'));

            if ($search !== '') {
                $criteria['search'] = $search;
            }

            $items = $this->categoryService->search($criteria);

            return new WP_REST_Response([
                'success' => true,
                'data' => [
                    'items' => $items,
                ],
            ]);
        } catch (\Throwable $throwable) {
            return $this->mapError($throwable);
        }
    }

    private function buildAttributeMap(array $variations): array
    {
        $map = [];

        foreach ($variations as $variation) {
            $attributes = (array) ($variation['attributes'] ?? []);

            foreach ($attributes as $attributeName => $attributeValue) {
                $normalizedName = trim((string) $attributeName);
                $normalizedValue = trim((string) $attributeValue);

                if ($normalizedName === '' || $normalizedValue === '') {
                    continue;
                }

                $map[$normalizedName][] = $normalizedValue;
            }
        }

        $result = [];

        foreach ($map as $attributeName => $values) {
            $uniqueValues = array_values(array_unique($values));
            sort($uniqueValues, SORT_NATURAL | SORT_FLAG_CASE);

            $result[] = [
                'name' => $attributeName,
                'options' => $uniqueValues,
            ];
        }

        return $result;
    }

    private function mapError(\Throwable $throwable)
    {
        if ($throwable instanceof Phase01Exception) {
            return ErrorFactory::restError(
                $throwable->errorCode(),
                $throwable->getMessage(),
                422,
                $throwable->context()
            );
        }

        return ErrorFactory::restError(
            'coffeepos_rest_error',
            __('Unexpected error while processing POS request.', 'coffeepos'),
            500
        );
    }
}
