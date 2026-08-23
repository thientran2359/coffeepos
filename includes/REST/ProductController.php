<?php

declare(strict_types=1);

namespace CoffeePOS\REST;

use CoffeePOS\Application\Product\CatalogService;
use CoffeePOS\Application\Product\CategoryService;
use CoffeePOS\Application\Product\ProductConfigurationService;
use CoffeePOS\Application\Product\ProductService;
use CoffeePOS\Application\Product\VariationService;
use CoffeePOS\Infrastructure\Settings\SettingsProductConfigurationProvider;
use CoffeePOS\Integration\WooCommerce\WooCommerceCategoryGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceMoney;
use CoffeePOS\Integration\WooCommerce\WooCommerceProductGateway;
use CoffeePOS\Integration\WooCommerce\WooCommerceVariationGateway;
use CoffeePOS\Support\Capabilities;
use CoffeePOS\Support\ErrorFactory;
use WP_REST_Request;
use WP_REST_Server;

final class ProductController
{
    private CatalogService $catalogService;

    private CategoryService $categoryService;

    private ProductService $productService;

    private VariationService $variationService;

    private ProductConfigurationService $configurationService;

    private WooCommerceMoney $money;

    public function __construct(
        ?CatalogService $catalogService = null,
        ?CategoryService $categoryService = null,
        ?ProductService $productService = null,
        ?VariationService $variationService = null,
        ?ProductConfigurationService $configurationService = null,
        ?WooCommerceMoney $money = null
    ) {
        $productGateway = new WooCommerceProductGateway();
        $variationGateway = new WooCommerceVariationGateway();
        $resolvedCategoryService = $categoryService ?? new CategoryService(new WooCommerceCategoryGateway());
        $resolvedProductService = $productService ?? new ProductService($productGateway);
        $resolvedVariationService = $variationService ?? new VariationService($variationGateway);

        $this->categoryService = $resolvedCategoryService;
        $this->productService = $resolvedProductService;
        $this->variationService = $resolvedVariationService;
        $this->catalogService = $catalogService ?? new CatalogService($resolvedCategoryService, $resolvedProductService);
        $this->configurationService = $configurationService ?? new ProductConfigurationService(
            $resolvedProductService,
            $resolvedVariationService,
            new SettingsProductConfigurationProvider()
        );
        $this->money = $money ?? new WooCommerceMoney();
    }

    public function register(string $namespace): void
    {
        register_rest_route($namespace, '/catalog', [[
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'catalog'],
            'permission_callback' => [$this, 'catalogPermissionCheck'],
        ]]);

        register_rest_route($namespace, '/products', [[
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'products'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);

        register_rest_route($namespace, '/products/(?P<id>\d+)', [[
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'productDetail'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);

        register_rest_route($namespace, '/products/(?P<id>\d+)/variation', [[
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'resolveVariation'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);

        register_rest_route($namespace, '/categories', [[
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'categories'],
            'permission_callback' => [$this, 'permissionCheck'],
        ]]);
    }

    public function permissionCheck()
    {
        if (current_user_can(Capabilities::ACCESS_CASHIER)) {
            return true;
        }

        return ErrorFactory::forbidden(
            'coffeepos_rest_forbidden',
            __('You are not allowed to access CoffeePOS REST endpoints.', 'coffeepos')
        );
    }

    public function catalogPermissionCheck()
    {
        return true;
    }

    public function catalog(WP_REST_Request $request)
    {
        try {
            return RestResponder::success([
                'catalog' => $this->catalogService->load($this->money->currentCurrency())->toArray(),
            ]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function products(WP_REST_Request $request)
    {
        try {
            $page = max(1, (int) $request->get_param('page'));
            $perPage = $request->has_param('per_page')
                ? max(1, min(100, (int) $request->get_param('per_page')))
                : 30;
            $criteria = ['page' => $page, 'limit' => $perPage];
            $search = trim((string) $request->get_param('search'));
            $categoryId = (int) $request->get_param('category');

            if ($search !== '') {
                $criteria['search'] = $search;
            }

            if ($categoryId > 0) {
                $criteria['category_id'] = $categoryId;
            }

            $items = [];

            foreach ($this->productService->search($criteria) as $view) {
                $items[] = $view->toArray();
            }

            return RestResponder::success([
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'count' => count($items),
                ],
            ]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function productDetail(WP_REST_Request $request)
    {
        try {
            return RestResponder::success($this->configurationService->detail((int) $request->get_param('id')));
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function resolveVariation(WP_REST_Request $request)
    {
        try {
            $payload = $request->get_json_params();
            $attributes = is_array($payload) ? (array) ($payload['attributes'] ?? []) : [];
            $sanitized = [];

            foreach ($attributes as $name => $value) {
                $normalizedName = sanitize_key((string) $name);
                $normalizedValue = sanitize_title((string) $value);

                if ($normalizedName !== '' && $normalizedValue !== '') {
                    $sanitized[$normalizedName] = $normalizedValue;
                }
            }

            $variation = $this->variationService->resolveProductVariation(
                (int) $request->get_param('id'),
                $sanitized
            );

            return RestResponder::success(['variation' => $variation->toArray()]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }

    public function categories(WP_REST_Request $request)
    {
        try {
            return RestResponder::success([
                'items' => $this->categoryService->search([
                    'limit' => $request->has_param('per_page') ? (int) $request->get_param('per_page') : 50,
                    'page' => max(1, (int) $request->get_param('page')),
                    'hide_empty' => false,
                    'search' => trim((string) $request->get_param('search')),
                ]),
            ]);
        } catch (\Throwable $throwable) {
            return RestResponder::fromThrowable($throwable);
        }
    }
}
