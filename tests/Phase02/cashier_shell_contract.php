<?php

declare(strict_types=1);

$pluginRoot = dirname(__DIR__, 2);

$paths = [
    'router' => 'includes/POS/Router.php',
    'assetLoader' => 'includes/Infrastructure/Assets/AssetLoader.php',
    'screenShell' => 'templates/components/screen-shell.php',
    'cashierContent' => 'templates/cashier/content.php',
    'cashierHeader' => 'templates/cashier/header.php',
    'cashierMenu' => 'templates/cashier/menu-panel.php',
    'cashierCart' => 'templates/cashier/cart-panel.php',
    'cashierOverlay' => 'templates/cashier/overlay-root.php',
    'catalogCategory' => 'templates/components/catalog-category.php',
    'productCard' => 'templates/components/product-card.php',
    'modal' => 'templates/components/modal.php',
    'confirm' => 'templates/components/confirm-dialog.php',
    'drawer' => 'templates/components/drawer.php',
    'toast' => 'templates/components/toast.php',
    'loading' => 'templates/components/loading.php',
    'empty' => 'templates/components/empty-state.php',
    'error' => 'templates/components/error-state.php',
    'app' => 'assets/js/app.js',
    'core' => 'assets/js/core/app.js',
    'renderer' => 'assets/js/ui/template-renderer.js',
    'modalJs' => 'assets/js/ui/modal.js',
    'toastJs' => 'assets/js/ui/toast.js',
    'categoryJs' => 'assets/js/components/category-nav.js',
    'searchJs' => 'assets/js/components/product-search.js',
    'productJs' => 'assets/js/components/product-card.js',
    'orderTypeJs' => 'assets/js/components/order-type.js',
    'cashierJs' => 'assets/js/screens/cashier.js',
    'coreCss' => 'assets/css/core.css',
    'componentsCss' => 'assets/css/components.css',
    'cashierCss' => 'assets/css/screens/cashier.css',
];

$read = static function (string $relativePath) use ($pluginRoot): string {
    $path = $pluginRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

    if (! is_file($path)) {
        throw new RuntimeException('Missing file: ' . $relativePath);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        throw new RuntimeException('Cannot read file: ' . $relativePath);
    }

    return $content;
};

$files = [];

foreach ($paths as $key => $relativePath) {
    $files[$key] = $read($relativePath);
}

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$assertContains = static function (string $needle, string $haystack, string $message) use ($assert): void {
    $assert(strpos($haystack, $needle) !== false, $message . ': ' . $needle);
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

$templateBundle = implode("\n", [
    $files['screenShell'],
    $files['cashierContent'],
    $files['cashierHeader'],
    $files['cashierMenu'],
    $files['cashierCart'],
    $files['cashierOverlay'],
    $files['catalogCategory'],
    $files['productCard'],
    $files['modal'],
    $files['confirm'],
    $files['drawer'],
    $files['toast'],
    $files['loading'],
    $files['empty'],
    $files['error'],
]);

$jsBundle = implode("\n", [
    $files['app'],
    $files['core'],
    $files['renderer'],
    $files['modalJs'],
    $files['toastJs'],
    $files['categoryJs'],
    $files['searchJs'],
    $files['productJs'],
    $files['orderTypeJs'],
    $files['cashierJs'],
]);

$cssBundle = implode("\n", [
    $files['coreCss'],
    $files['componentsCss'],
    $files['cashierCss'],
]);

$run('TC-01/02 route scope remains POS-only', static function () use ($assertContains, $files): void {
    $assertContains("'cashier'", $files['router'], 'Cashier route is not registered');
    $assertContains('if ($screen === null)', $files['router'], 'Unrelated routes are not returned untouched');
    $assertContains('return $template;', $files['router'], 'Router must preserve the WordPress template');
    $assertContains("add_filter('show_admin_bar'", $files['router'], 'POS route does not control the admin bar');
    $assertContains('self::isPosRequest() ? false : $show', $files['router'], 'Admin bar must only be hidden on POS routes');
});

$run('TC-03-06 complete Cashier layout contract', static function () use ($assertContains, $templateBundle): void {
    foreach ([
        'data-component="cashier-header"',
        'data-component="menu-panel"',
        'data-component="cart-panel"',
        'data-component="overlay-root"',
    ] as $selector) {
        $assertContains($selector, $templateBundle, 'Missing shell selector');
    }

    $assertContains('data-screen="<?php echo esc_attr($screen); ?>"', $templateBundle, 'Screen shell lacks the dynamic screen selector');
});

$run('TC-07-09 catalog shell and presentation actions', static function () use ($assertContains, $templateBundle, $files): void {
    foreach ([
        'data-component="product-search"',
        'data-component="product-search-results"',
        'data-component="category-nav"',
        'data-action="scroll-category"',
        'data-component="catalog-scroll"',
        'data-component="catalog-section-list"',
        'data-component="catalog-category-section"',
        'data-component="catalog-category-products"',
        'data-component="product-card"',
        'data-occurrence-key',
    ] as $selector) {
        $assertContains($selector, $templateBundle, 'Missing catalog contract');
    }

    $assertContains("'out_of_stock'", $files['productCard'], 'Product card lacks out-of-stock state');
    $assertContains("setAttribute('data-state', 'selected')", $files['productJs'], 'Product card selection state is not implemented');
});

$run('Dynamic markup uses native templates and binding markers', static function () use ($assertContains, $files): void {
    foreach ([
        'coffeepos-category-button-template',
        'coffeepos-catalog-category-template',
        'coffeepos-product-card-template',
        'coffeepos-product-search-result-template',
        'coffeepos-cart-item-template',
    ] as $templateId) {
        $assertContains($templateId, $files['cashierMenu'] . $files['cashierCart'], 'Missing native template');
    }

    foreach (['data-field=', 'data-attr=', 'data-key='] as $marker) {
        $assertContains($marker, $files['cashierMenu'] . $files['cashierCart'], 'Missing template binding marker');
    }
});

$run('TemplateRenderer enforces the documented safety boundary', static function () use ($assert, $assertContains, $files): void {
    foreach ([
        'renderList',
        'textContent',
        'hasOwnProperty.call',
        '__proto__',
        'booleanAttributes',
        'urlValidators',
        'TemplateRendererError',
    ] as $contract) {
        $assertContains($contract, $files['renderer'], 'Renderer contract is incomplete');
    }

    $assert(strpos($files['renderer'], 'innerHTML') === false, 'Renderer must not interpolate HTML');
    $assertContains("registerUrlValidator('src'", $files['cashierJs'], 'Product images lack an explicit URL validator');
});

$run('TC-10/11 modal and confirm behavior foundations', static function () use ($assertContains, $templateBundle, $files): void {
    foreach ([
        'role="dialog"',
        'role="alertdialog"',
        'data-action="cancel-modal"',
        'data-action="confirm-modal"',
        'data-action="cancel-confirm"',
        'data-action="confirm-action"',
    ] as $contract) {
        $assertContains($contract, $templateBundle, 'Missing overlay contract');
    }

    foreach (['Escape', "event.key !== 'Tab'", 'previousFocus.focus()', 'aria-modal'] as $behavior) {
        $assertContains($behavior, $files['modalJs'] . $templateBundle, 'Missing accessible modal behavior');
    }
});

$run('TC-12-15 shared toast/loading/empty/error states', static function () use ($assertContains, $templateBundle, $files): void {
    foreach ([
        'data-component="toast"',
        'data-component="loading"',
        'data-component="empty-state"',
        'data-component="error-state"',
    ] as $component) {
        $assertContains($component, $templateBundle, 'Missing shared state component');
    }

    foreach (['success', 'info', 'warning', 'error'] as $type) {
        $assertContains("'" . $type . "'", $files['toastJs'], 'Missing toast type');
    }
});

$run('Cart shell exposes all future action hooks without enabling checkout', static function () use ($assert, $assertContains, $files): void {
    foreach ([
        'data-component="cart-item"',
        'data-action="increase-quantity"',
        'data-action="decrease-quantity"',
        'data-action="edit-cart-item"',
        'data-action="remove-cart-item"',
        'data-component="order-type"',
        'data-component="customer-summary"',
        'data-component="coupon"',
        'data-component="cart-summary"',
        'data-component="checkout"',
    ] as $contract) {
        $assertContains($contract, $files['cashierCart'], 'Missing cart shell contract');
    }

    $assert((bool) preg_match('/data-action="checkout"[^>]*disabled/', $files['cashierCart']), 'Checkout must be disabled by default');
});

$run('JavaScript is modular and app.js remains a bootstrap', static function () use ($assert, $assertContains, $files): void {
    $assertContains('createCashierController', $files['cashierJs'], 'Cashier screen controller is missing');
    $assertContains('CoffeePOS.activeScreen', $files['app'], 'Bootstrap does not expose initialized screen state');
    $assert(strpos($files['app'], 'createToastController') === false, 'app.js contains UI component implementation');
    $assert(strpos($files['app'], 'createModalController =') === false, 'app.js contains modal implementation');
    $assert(substr_count($files['assetLoader'], 'wp_register_script(') >= 10, 'AssetLoader does not register the modular dependency graph');
    $assertContains('if ($screen === \'cashier\')', $files['assetLoader'], 'Cashier assets are not screen scoped');
});

$run('TC-16-18 responsive and touch contracts', static function () use ($assertContains, $cssBundle): void {
    foreach ([
        'min-height: 44px',
        '@media (max-width: 1180px)',
        '@media (max-width: 900px)',
        '@media (max-width: 720px)',
        'grid-template-columns: minmax(0, 1fr) 330px',
    ] as $rule) {
        $assertContains($rule, $cssBundle, 'Missing responsive/touch CSS rule');
    }
});

$run('TC-19-21 keyboard and semantic control contracts', static function () use ($assertContains, $templateBundle, $cssBundle): void {
    foreach (['<button', '<label', 'aria-label=', 'aria-live=', ':focus-visible'] as $contract) {
        $assertContains($contract, $templateBundle . $cssBundle, 'Missing accessibility contract');
    }
});

$run('TC-22-25 presentation has no later-phase persistence leakage', static function () use ($assert, $jsBundle, $templateBundle): void {
    $presentationCode = $jsBundle . "\n" . $templateBundle;

    foreach ([
        'innerHTML',
        'wc_create_order',
        'WC()->session',
        'BroadcastChannel',
    ] as $forbidden) {
        $assert(strpos($presentationCode, $forbidden) === false, 'Out-of-scope behavior found: ' . $forbidden);
    }
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
