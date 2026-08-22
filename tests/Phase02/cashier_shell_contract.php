<?php

declare(strict_types=1);

$pluginRoot = dirname(__DIR__, 2);

$files = [
    'screenShell' => $pluginRoot . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . 'screen-shell.php',
    'cashierContent' => $pluginRoot . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'cashier' . DIRECTORY_SEPARATOR . 'content.php',
    'cashierHeader' => $pluginRoot . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'cashier' . DIRECTORY_SEPARATOR . 'header.php',
    'cashierMenu' => $pluginRoot . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'cashier' . DIRECTORY_SEPARATOR . 'menu-panel.php',
    'cashierCart' => $pluginRoot . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'cashier' . DIRECTORY_SEPARATOR . 'cart-panel.php',
    'cashierOverlay' => $pluginRoot . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR . 'cashier' . DIRECTORY_SEPARATOR . 'overlay-root.php',
    'cashierJs' => $pluginRoot . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'app.js',
];

$read = static function (string $path): string {
    if (! file_exists($path)) {
        throw new RuntimeException('Missing file: ' . $path);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        throw new RuntimeException('Cannot read file: ' . $path);
    }

    return $content;
};

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$results = [];

$run = static function (string $name, callable $callback) use (&$results): void {
    try {
        $callback();
        $results[] = ['name' => $name, 'status' => 'PASS', 'detail' => ''];
    } catch (Throwable $throwable) {
        $results[] = ['name' => $name, 'status' => 'FAIL', 'detail' => $throwable->getMessage()];
    }
};

$templatesBundle = '';

foreach (['screenShell', 'cashierContent', 'cashierHeader', 'cashierMenu', 'cashierCart', 'cashierOverlay'] as $templateKey) {
    $templatesBundle .= "\n" . $read($files[$templateKey]);
}

$jsBundle = $read($files['cashierJs']);

$run('Selector contract: cashier root + required components', static function () use ($assert, $templatesBundle): void {
    $selectors = [
        'data-screen="cashier"',
        'data-component="product-search"',
        'data-component="category-nav"',
        'data-component="product-grid"',
        'data-component="product-card"',
        'data-component="cart-panel"',
        'data-component="cart-item"',
        'data-component="order-type"',
        'data-component="customer-summary"',
        'data-component="coupon"',
        'data-component="cart-summary"',
        'data-component="checkout"',
        'data-component="overlay-root"',
    ];

    foreach ($selectors as $selector) {
        $assert(strpos($templatesBundle, $selector) !== false, 'Missing selector: ' . $selector);
    }
});

$run('Cart panel guards: empty state + disabled checkout', static function () use ($assert, $templatesBundle): void {
    $assert(strpos($templatesBundle, 'data-component="cart-empty"') !== false, 'Missing cart empty state container');
    $assert((bool) preg_match('/data-action="checkout"[^>]*disabled/', $templatesBundle), 'Checkout button must be disabled by default in Phase 02');
    $assert(strpos($templatesBundle, 'data-action="clear-cart"') !== false, 'Missing clear cart action hook');
});

$run('Product card shell: identity + stock/selection states', static function () use ($assert, $templatesBundle): void {
    $assert((bool) preg_match('/data-component="product-card"[^>]*data-product-id="[^"]+"/', $templatesBundle), 'Product card must expose stable data-product-id');
    $assert(strpos($templatesBundle, 'data-state="selected"') !== false, 'Missing selected state shell for product card');
    $assert(strpos($templatesBundle, 'data-state="out_of_stock"') !== false, 'Missing out_of_stock state shell for product card');
});

$run('JS action contract: explicit handlers or pending notices', static function () use ($assert, $jsBundle): void {
    $actions = [
        'search-products',
        'clear-search',
        'select-category',
        'select-product',
        'open-customer',
        'select-order-type',
        'open-coupon',
        'clear-cart',
        'increase-quantity',
        'decrease-quantity',
        'edit-cart-item',
        'remove-cart-item',
        'checkout',
    ];

    foreach ($actions as $action) {
        $assert(strpos($jsBundle, "'" . $action . "'") !== false || strpos($jsBundle, '"' . $action . '"') !== false, 'Missing JS action reference: ' . $action);
    }
});

$run('JS foundation state: modal + toast + screen controller', static function () use ($assert, $jsBundle): void {
    $assert(strpos($jsBundle, 'createCashierController') !== false, 'Missing cashier screen controller');
    $assert(strpos($jsBundle, 'openModal') !== false, 'Missing modal foundation behavior');
    $assert(strpos($jsBundle, 'createToastController') !== false, 'Missing toast foundation behavior');
    $assert(strpos($jsBundle, 'data-screen') !== false, 'Missing screen state binding');
});

$hasFailure = false;

foreach ($results as $result) {
    $line = '[' . $result['status'] . '] ' . $result['name'];

    if ($result['detail'] !== '') {
        $line .= ' :: ' . $result['detail'];
    }

    echo $line . PHP_EOL;

    if ($result['status'] === 'FAIL') {
        $hasFailure = true;
    }
}

exit($hasFailure ? 1 : 0);
