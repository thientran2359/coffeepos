<?php

declare(strict_types=1);

use CoffeePOS\Application\Cart\CartService;
use CoffeePOS\Application\Cart\CartValidationService;
use CoffeePOS\Application\Coupon\CouponService;
use CoffeePOS\Application\Customer\CustomerService;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Product\VariationService;
use CoffeePOS\Domain\Cart\CartItem;
use CoffeePOS\Domain\Order\TableContext;
use CoffeePOS\Domain\Product\QuickNoteSelection;
use CoffeePOS\Domain\Shared\Money;

$pluginRoot = dirname(__DIR__, 2);
$autoload = $pluginRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

if (file_exists($autoload)) {
    require_once $autoload;
} else {
    spl_autoload_register(static function (string $className) use ($pluginRoot): void {
        $prefix = 'CoffeePOS\\';

        if (strpos($className, $prefix) !== 0) {
            return;
        }

        $relative = substr($className, strlen($prefix));
        $filePath = $pluginRoot . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

        if (file_exists($filePath)) {
            require_once $filePath;
        }
    });
}

$results = [];

$run = static function (string $name, callable $callback) use (&$results): void {
    try {
        $callback();
        $results[] = ['name' => $name, 'status' => 'PASS', 'detail' => ''];
    } catch (Throwable $throwable) {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'detail' => $throwable->getMessage()];
    }
};

$assert = static function (bool $condition, string $message = 'Assertion failed'): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$expectCode = static function (string $expectedCode, callable $callback) use ($assert): void {
    try {
        $callback();
    } catch (Phase01Exception $exception) {
        $assert($exception->errorCode() === $expectedCode, 'Expected code ' . $expectedCode . ', got ' . $exception->errorCode());

        return;
    }

    throw new RuntimeException('Expected Phase01Exception with code ' . $expectedCode);
};

$run('Cart: empty cart', static function () use ($assert): void {
    $service = new CartService();
    $cart = $service->createCart('VND');
    $view = $service->project($cart)->toArray();

    $assert($view['total_quantity'] === 0, 'Expected total_quantity = 0');
    $assert($view['subtotal_minor'] === 0, 'Expected subtotal_minor = 0');
});

$run('Cart: add simple product + merge same config', static function () use ($assert): void {
    $service = new CartService();
    $cart = $service->createCart('VND');
    $itemA = CartItem::create(10, 0, 1, Money::fromMinor(30000, 'VND'));
    $itemB = CartItem::create(10, 0, 2, Money::fromMinor(30000, 'VND'));

    $service->addItem($cart, $itemA);
    $view = $service->addItem($cart, $itemB)->toArray();

    $assert(count($view['items']) === 1, 'Expected merged cart item count = 1');
    $assert($view['items'][0]['quantity'] === 3, 'Expected merged quantity = 3');
});

$run('Cart: different config separation', static function () use ($assert): void {
    $service = new CartService();
    $cart = $service->createCart('VND');
    $itemA = CartItem::create(20, 201, 1, Money::fromMinor(45000, 'VND'));
    $itemB = CartItem::create(20, 201, 1, Money::fromMinor(45000, 'VND'), null, QuickNoteSelection::fromArray(['Ít đá']));

    $service->addItem($cart, $itemA);
    $view = $service->addItem($cart, $itemB)->toArray();

    $assert(count($view['items']) === 2, 'Expected separated cart item count = 2');
});

$run('Cart: increase/decrease/remove/clear', static function () use ($assert): void {
    $service = new CartService();
    $cart = $service->createCart('VND');
    $item = CartItem::create(30, 0, 1, Money::fromMinor(10000, 'VND'));
    $view = $service->addItem($cart, $item)->toArray();
    $itemId = $view['items'][0]['item_id'];

    $view = $service->updateItemQuantity($cart, $itemId, 4)->toArray();
    $assert($view['items'][0]['quantity'] === 4, 'Expected quantity = 4 after increase');

    $view = $service->updateItemQuantity($cart, $itemId, 2)->toArray();
    $assert($view['items'][0]['quantity'] === 2, 'Expected quantity = 2 after decrease');

    $view = $service->removeItem($cart, $itemId)->toArray();
    $assert(count($view['items']) === 0, 'Expected item removed');

    $service->addItem($cart, CartItem::create(31, 0, 1, Money::fromMinor(10000, 'VND')));
    $view = $service->clear($cart)->toArray();
    $assert(count($view['items']) === 0, 'Expected cart clear');
});

$run('Order type: dine-in without table -> reject', static function () use ($expectCode): void {
    $service = new CartService();
    $cart = $service->createCart('VND');

    $expectCode(Phase01ErrorCodes::TABLE_REQUIRED, static function () use ($service, $cart): void {
        $service->setOrderType($cart, 'dine_in');
    });
});

$run('Order type: dine-in with table -> accept', static function () use ($assert): void {
    $service = new CartService();
    $cart = $service->createCart('VND');
    $view = $service->setOrderType($cart, 'dine_in', TableContext::from(5, 'B5'))->toArray();

    $assert($view['order_type'] === 'dine_in', 'Expected order_type=dine_in');
    $assert((int) $view['table']['table_id'] === 5, 'Expected table id=5');
});

$run('Order type: takeaway without table -> accept', static function () use ($assert): void {
    $service = new CartService();
    $cart = $service->createCart('VND');
    $view = $service->setOrderType($cart, 'takeaway')->toArray();

    $assert($view['order_type'] === 'takeaway', 'Expected order_type=takeaway');
    $assert($view['table']['table_id'] === null, 'Expected empty table for takeaway');
});

$run('Order type: takeaway with table context -> reject', static function () use ($expectCode): void {
    $service = new CartService();
    $cart = $service->createCart('VND');

    $expectCode(Phase01ErrorCodes::TABLE_NOT_ALLOWED, static function () use ($service, $cart): void {
        $service->setTableContext($cart, TableContext::from(10, 'A10'));
    });
});

$run('Variation: single attribute', static function () use ($assert): void {
    $service = new VariationService();
    $view = $service->resolveVariation(101, ['attribute_pa_size' => 'm'], [
        ['id' => 1001, 'product_id' => 101, 'attributes' => ['attribute_pa_size' => 'm'], 'price_minor' => 40000, 'currency' => 'VND', 'is_available' => true],
    ])->toArray();

    $assert((int) $view['id'] === 1001, 'Expected variation id 1001');
});

$run('Variation: multiple attributes', static function () use ($assert): void {
    $service = new VariationService();
    $view = $service->resolveVariation(102, ['attribute_pa_size' => 'l', 'attribute_pa_temp' => 'cold'], [
        ['id' => 2001, 'product_id' => 102, 'attributes' => ['attribute_pa_temp' => 'cold', 'attribute_pa_size' => 'l'], 'price_minor' => 50000, 'currency' => 'VND', 'is_available' => true],
    ])->toArray();

    $assert((int) $view['id'] === 2001, 'Expected variation id 2001');
});

$run('Variation: invalid combination', static function () use ($expectCode): void {
    $service = new VariationService();

    $expectCode(Phase01ErrorCodes::VARIATION_NOT_FOUND, static function () use ($service): void {
        $service->resolveVariation(103, ['attribute_pa_size' => 'xl'], [
            ['id' => 3001, 'product_id' => 103, 'attributes' => ['attribute_pa_size' => 'm'], 'price_minor' => 50000, 'currency' => 'VND', 'is_available' => true],
        ]);
    });
});

$run('Variation: variation/product mismatch', static function () use ($expectCode): void {
    $service = new VariationService();

    $expectCode(Phase01ErrorCodes::VARIATION_NOT_FOUND, static function () use ($service): void {
        $service->resolveVariation(104, ['attribute_pa_size' => 'm'], [
            ['id' => 4001, 'product_id' => 999, 'attributes' => ['attribute_pa_size' => 'm'], 'price_minor' => 50000, 'currency' => 'VND', 'is_available' => true],
        ]);
    });
});

$run('Variation: unavailable variation', static function () use ($expectCode): void {
    $service = new VariationService();

    $expectCode(Phase01ErrorCodes::OUT_OF_STOCK, static function () use ($service): void {
        $service->resolveVariation(105, ['attribute_pa_size' => 'm'], [
            ['id' => 5001, 'product_id' => 105, 'attributes' => ['attribute_pa_size' => 'm'], 'price_minor' => 50000, 'currency' => 'VND', 'is_available' => false],
        ]);
    });
});

$run('Customer: guest + valid + not found', static function () use ($assert, $expectCode): void {
    $service = new CustomerService();
    $guest = $service->guestContext();
    $assert($guest->isGuest() === true, 'Expected guest context');

    $customer = $service->findByPhone('0909-000-111', [
        ['id' => 7001, 'name' => 'Alice', 'phone' => '0909000111'],
    ])->toArray();

    $assert((int) $customer['id'] === 7001, 'Expected found customer id=7001');

    $expectCode(Phase01ErrorCodes::CUSTOMER_NOT_FOUND, static function () use ($service): void {
        $service->findByPhone('0909000222', [['id' => 7001, 'name' => 'Alice', 'phone' => '0909000111']]);
    });
});

$run('Coupon: valid/invalid/Woo rejected behavior', static function () use ($assert, $expectCode): void {
    $service = new CouponService();
    $valid = $service->validateCouponResult('sale10', ['is_valid' => true, 'message' => 'ok'])->toArray();
    $assert($valid['is_valid'] === true, 'Expected valid coupon');

    $invalid = $service->validateCouponResult('sale10', ['is_valid' => false, 'message' => 'rejected'])->toArray();
    $assert($invalid['is_valid'] === false, 'Expected invalid coupon');

    $expectCode(Phase01ErrorCodes::INVALID_COUPON, static function () use ($service): void {
        $service->requireValidCoupon('sale10', ['is_valid' => false, 'message' => 'WooCommerce rejected']);
    });
});

$run('Validation: invalid product/variation/quantity/configuration', static function () use ($expectCode): void {
    $variationService = new VariationService();
    $validationService = new CartValidationService();

    $expectCode(Phase01ErrorCodes::INVALID_PRODUCT, static function () use ($variationService): void {
        $variationService->resolveVariation(0, ['attribute_pa_size' => 'm'], []);
    });

    $expectCode(Phase01ErrorCodes::INVALID_VARIATION, static function () use ($variationService): void {
        $variationService->resolveVariation(999, [], []);
    });

    $expectCode(Phase01ErrorCodes::INVALID_QUANTITY, static function () use ($validationService): void {
        $validationService->validateQuantity(0);
    });
});

$run('Error code consistency', static function () use ($assert): void {
    $requiredCodes = [
        Phase01ErrorCodes::INVALID_PRODUCT,
        Phase01ErrorCodes::INVALID_VARIATION,
        Phase01ErrorCodes::VARIATION_NOT_FOUND,
        Phase01ErrorCodes::INVALID_QUANTITY,
        Phase01ErrorCodes::OUT_OF_STOCK,
        Phase01ErrorCodes::INVALID_ORDER_TYPE,
        Phase01ErrorCodes::TABLE_REQUIRED,
        Phase01ErrorCodes::TABLE_NOT_ALLOWED,
        Phase01ErrorCodes::CUSTOMER_NOT_FOUND,
        Phase01ErrorCodes::INVALID_CUSTOMER,
        Phase01ErrorCodes::INVALID_COUPON,
        Phase01ErrorCodes::INVALID_CONFIGURATION,
    ];

    $assert(count($requiredCodes) === count(array_unique($requiredCodes)), 'Expected all error codes to be unique');
});

if (! function_exists('wc_get_product')) {
    $results[] = [
        'name' => 'Integration: Woo runtime checks',
        'status' => 'BLOCKED',
        'detail' => 'WooCommerce runtime is unavailable in this CLI context.',
    ];
}

$hasFailure = false;

foreach ($results as $result) {
    $line = sprintf('[%s] %s', $result['status'], $result['name']);

    if ($result['detail'] !== '') {
        $line .= ' - ' . $result['detail'];
    }

    echo $line . PHP_EOL;

    if ($result['status'] === 'FAIL') {
        $hasFailure = true;
    }
}

exit($hasFailure ? 1 : 0);
