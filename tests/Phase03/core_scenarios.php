<?php

declare(strict_types=1);

use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Contracts\CategoryGatewayInterface;
use CoffeePOS\Application\Contracts\ProductGatewayInterface;
use CoffeePOS\Application\Product\CatalogService;
use CoffeePOS\Application\Product\CategoryService;
use CoffeePOS\Application\Product\ProductService;
use CoffeePOS\Application\Product\VariationService;
use CoffeePOS\Domain\Cart\Cart;
use CoffeePOS\Domain\Cart\CartItem;
use CoffeePOS\Domain\Product\ModifierSelection;
use CoffeePOS\Domain\Product\QuickNoteSelection;
use CoffeePOS\Domain\Shared\Money;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try {
        $callback();
        echo '[PASS] ' . $name . PHP_EOL;
    } catch (Throwable $error) {
        $failures[] = $name . ': ' . $error->getMessage();
        echo '[FAIL] ' . end($failures) . PHP_EOL;
    }
};
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$variation = static function (int $id, array $attributes, bool $available = true): array {
    return [
        'id' => $id,
        'product_id' => 10,
        'attributes' => $attributes,
        'price_minor' => 45000,
        'price_amount' => '45000',
        'price_display' => '45.000 đ',
        'currency' => 'VND',
        'is_available' => $available,
    ];
};

$test('TC-11 full multi-attribute variation match', static function () use ($assert, $variation): void {
    $service = new VariationService();
    $view = $service->resolveVariation(10, ['pa_size' => 'm', 'pa_temperature' => 'cold'], [
        $variation(101, ['pa_size' => 'm', 'pa_temperature' => 'hot']),
        $variation(102, ['pa_size' => 'm', 'pa_temperature' => 'cold']),
    ])->toArray();
    $assert($view['id'] === 102, 'Resolver did not use the full attribute set.');
});

$test('TC-11 WooCommerce Any-attribute wildcard', static function () use ($assert, $variation): void {
    $service = new VariationService();
    $view = $service->resolveVariation(10, ['pa_size' => 'l', 'pa_temperature' => 'cold'], [
        $variation(103, ['pa_size' => '', 'pa_temperature' => 'cold']),
    ])->toArray();
    $assert($view['id'] === 103, 'WooCommerce wildcard attribute did not resolve.');
});

$test('TC-12 invalid combination is rejected', static function () use ($assert, $variation): void {
    try {
        (new VariationService())->resolveVariation(10, ['pa_size' => 's'], [
            $variation(104, ['pa_size' => 'm']),
        ]);
    } catch (Phase01Exception $error) {
        $assert($error->errorCode() === Phase01ErrorCodes::VARIATION_NOT_FOUND, 'Unexpected error code.');
        return;
    }
    throw new RuntimeException('Invalid combination was accepted.');
});

$test('TC-14 unavailable variation is rejected', static function () use ($assert, $variation): void {
    try {
        (new VariationService())->resolveVariation(10, ['pa_size' => 'm'], [
            $variation(105, ['pa_size' => 'm'], false),
        ]);
    } catch (Phase01Exception $error) {
        $assert($error->errorCode() === Phase01ErrorCodes::OUT_OF_STOCK, 'Unexpected error code.');
        return;
    }
    throw new RuntimeException('Unavailable variation was accepted.');
});

$test('TC-27/28 cart identity merges equal configuration only', static function () use ($assert): void {
    $cart = Cart::createSession('VND', 'session-a', '2026-08-23T00:00:00Z');
    $make = static function (array $notes): CartItem {
        return CartItem::create(
            10,
            0,
            1,
            Money::fromMinor(45000, 'VND'),
            ModifierSelection::fromArray([]),
            QuickNoteSelection::fromArray($notes),
            ''
        );
    };
    $cart->addItem($make(['less_ice']));
    $cart->addItem($make(['less_ice']));
    $cart->addItem($make(['no_ice']));
    $assert(count($cart->items()) === 2, 'Different configuration did not remain separate.');
    $assert($cart->totalQuantity() === 3, 'Equal configuration did not merge quantities.');
});

$test('Woo category admin order controls shared catalog sections', static function () use ($assert, $root): void {
    $categories = new CategoryService(new class implements CategoryGatewayInterface {
        public function search(array $criteria = []): array
        {
            return [
                ['id' => 30, 'slug' => 'tea', 'name' => 'Tea', 'sort_order' => 30, 'count' => 1],
                ['id' => 10, 'slug' => 'coffee', 'name' => 'Coffee', 'sort_order' => 10, 'count' => 1],
                ['id' => 20, 'slug' => 'juice', 'name' => 'Juice', 'sort_order' => 20, 'count' => 1],
            ];
        }
    });
    $products = new ProductService(new class implements ProductGatewayInterface {
        public function findById(int $productId): ?array { return null; }
        public function search(array $criteria = []): array
        {
            return [
                ['id' => 301, 'name' => 'Tea', 'price_minor' => 30000, 'currency' => 'VND', 'is_in_stock' => true, 'is_purchasable' => true, 'category_ids' => [30]],
                ['id' => 101, 'name' => 'Coffee', 'price_minor' => 25000, 'currency' => 'VND', 'is_in_stock' => true, 'is_purchasable' => true, 'category_ids' => [10], 'menu_order' => 1],
                ['id' => 102, 'name' => 'Featured Coffee', 'price_minor' => 30000, 'currency' => 'VND', 'is_in_stock' => true, 'is_purchasable' => true, 'category_ids' => [10], 'menu_order' => 99, 'is_featured' => true, 'badge_label' => 'Hot'],
                ['id' => 201, 'name' => 'Juice', 'price_minor' => 35000, 'currency' => 'VND', 'is_in_stock' => true, 'is_purchasable' => true, 'category_ids' => [20]],
            ];
        }
    });
    $catalog = (new CatalogService($categories, $products))->load('VND')->toArray();
    $assert(array_column($catalog['categories'], 'id') === [10, 20, 30], 'Catalog sections do not follow WooCommerce category admin order.');
    $assert(array_column($catalog['categories'][0]['products'], 'id') === [102, 101], 'Featured products must appear before normal products inside each category section.');
    $assert($catalog['categories'][0]['products'][0]['is_featured'] === true && $catalog['categories'][0]['products'][0]['badge_label'] === 'Hot', 'Featured product projection is missing its Hot badge.');

    $gatewaySource = (string) file_get_contents($root . '/includes/Integration/WooCommerce/WooCommerceCategoryGateway.php');
    $cashierRenderer = (string) file_get_contents($root . '/assets/js/components/catalog-renderer.js');
    $customerRenderer = (string) file_get_contents($root . '/assets/js/components/customer-catalog.js');
    $customerTemplate = (string) file_get_contents($root . '/templates/components/customer-display-templates.php');
    $customerCss = (string) file_get_contents($root . '/assets/css/screens/customer-display.css');
    $assert(strpos($gatewaySource, "'menu_order' => 'ASC'") !== false && strpos($gatewaySource, "'force_menu_order_sort' => true") !== false, 'Category gateway does not invoke WooCommerce term menu ordering.');
    $assert(strpos($cashierRenderer, 'categories.forEach') !== false && strpos($customerRenderer, 'categories.forEach') !== false, 'Cashier and Customer Display must preserve CatalogView section order.');
    $assert(strpos($customerTemplate, 'coffeepos-customer-product-badge') !== false, 'Customer Display featured badge template is missing.');
    $assert(strpos($customerCss, '.coffeepos-customer-product-row div') !== false && strpos($customerCss, 'flex-wrap: wrap') !== false && strpos($customerCss, '.coffeepos-customer-product-badge') !== false, 'Customer Display Hot badge must sit inline with the product name and wrap safely.');
});

$test('Phase 03 modules are wired and checkout remains disabled', static function () use ($assert, $root): void {
    $assets = file_get_contents($root . '/includes/Infrastructure/Assets/AssetLoader.php');
    $screen = file_get_contents($root . '/assets/js/screens/cashier.js');
    $cart = file_get_contents($root . '/templates/cashier/cart-panel.php');
    foreach (['coffeepos-api-client', 'coffeepos-component-product-modal', 'coffeepos-component-cart-panel'] as $handle) {
        $assert(strpos((string) $assets, $handle) !== false, 'Missing script handle: ' . $handle);
    }
    foreach (['loadCatalog()', 'createCart()', 'updateCartItem', 'removeCartItem', 'clearCart'] as $operation) {
        $assert(strpos((string) $screen, $operation) !== false, 'Missing Cashier operation: ' . $operation);
    }
    foreach (['sessionStorage', 'getCart(existingSessionId)', "error.code === 'cart_session_not_found'"] as $recoveryContract) {
        $assert(strpos((string) $screen, $recoveryContract) !== false, 'Missing reload recovery contract: ' . $recoveryContract);
    }
    $assert((bool) preg_match('/data-action="checkout"[^>]*disabled/', (string) $cart), 'Checkout must remain disabled.');
});

exit($failures === [] ? 0 : 1);
