<?php

declare(strict_types=1);

use CoffeePOS\Application\Cart\CartService;
use CoffeePOS\Application\Cart\CartSessionService;
use CoffeePOS\Application\Cart\SuspendedCartService;
use CoffeePOS\Application\Contracts\CartSessionStoreInterface;
use CoffeePOS\Application\Contracts\LockProviderInterface;
use CoffeePOS\Application\Contracts\MoneyFormatterInterface;
use CoffeePOS\Application\Contracts\ProductConfigurationProviderInterface;
use CoffeePOS\Application\Contracts\ProductGatewayInterface;
use CoffeePOS\Application\Contracts\SuspendedCartRepositoryInterface;
use CoffeePOS\Application\Contracts\TableProviderInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Product\ProductConfigurationService;
use CoffeePOS\Application\Product\ProductService;
use CoffeePOS\Application\Product\VariationService;
use CoffeePOS\Domain\Cart\Cart;
use CoffeePOS\Domain\Cart\CartItem;
use CoffeePOS\Domain\Order\OrderType;
use CoffeePOS\Domain\Order\TableContext;
use CoffeePOS\Domain\Shared\Money;
use CoffeePOS\Integration\WooCommerce\WooCommerceCartSerializer;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

if (! function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}

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

$store = new class implements CartSessionStoreInterface {
    /** @var array<string, Cart> */
    public array $carts = [];

    public function create(string $currency): Cart
    {
        $cart = Cart::createSession($currency, 'held-session-0001', '2026-09-09T00:00:00Z');
        $this->carts[$cart->posSessionId()] = $cart;
        return $cart;
    }

    public function load(string $posSessionId): ?Cart
    {
        return $this->carts[$posSessionId] ?? null;
    }

    public function save(Cart $cart, int $expectedRevision): Cart
    {
        if ($cart->revision() !== $expectedRevision) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::CART_REVISION_CONFLICT, 'Stale cart.');
        }
        $cart->advanceRevision('2026-09-09T00:00:01Z');
        $this->carts[$cart->posSessionId()] = $cart;
        return $cart;
    }
};

$repository = new class implements SuspendedCartRepositoryInterface {
    /** @var array<int, array> */
    public array $rows = [];
    private int $nextId = 1;

    public function create(int $userId, string $label, array $cartPayload): array
    {
        $id = $this->nextId++;
        return $this->rows[$id] = [
            'id' => $id,
            'user_id' => $userId,
            'label' => $label,
            'cart_payload' => $cartPayload,
            'created_at' => '2026-09-09 07:00:00',
            'updated_at' => '2026-09-09 07:00:00',
        ];
    }

    public function findByIdForUser(int $heldCartId, int $userId): ?array
    {
        $row = $this->rows[$heldCartId] ?? null;
        return is_array($row) && $row['user_id'] === $userId ? $row : null;
    }

    public function listByUser(int $userId, int $limit): array
    {
        return array_slice(array_values(array_filter($this->rows, static function (array $row) use ($userId): bool {
            return $row['user_id'] === $userId;
        })), 0, $limit);
    }

    public function deleteForUser(int $heldCartId, int $userId): void
    {
        if ($this->findByIdForUser($heldCartId, $userId) === null) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::HELD_CART_NOT_FOUND, 'Missing held cart.');
        }
        unset($this->rows[$heldCartId]);
    }
};

$products = new class implements ProductGatewayInterface {
    public int $priceMinor = 30000;

    public function findById(int $productId): ?array
    {
        if ($productId !== 11) {
            return null;
        }
        return [
            'id' => 11,
            'name' => 'Coffee',
            'price_minor' => $this->priceMinor,
            'currency' => 'VND',
            'is_variable' => false,
            'is_in_stock' => true,
            'is_purchasable' => true,
            'type' => 'simple',
            'category_ids' => [],
            'attributes' => [],
        ];
    }

    public function search(array $criteria = []): array
    {
        return [$this->findById(11)];
    }
};

$configuration = new class implements ProductConfigurationProviderInterface {
    public function configurationForProduct(array $product): array
    {
        return ['modifier_groups' => [], 'quick_notes' => []];
    }
};

$tables = new class implements TableProviderInterface {
    public function listAvailable(): array
    {
        return [['id' => 5, 'label' => 'Table 5', 'enabled' => true, 'sort_order' => 5]];
    }

    public function findAvailableById(int $tableId): ?array
    {
        return $tableId === 5 ? $this->listAvailable()[0] : null;
    }
};

$formatter = new class implements MoneyFormatterInterface {
    public function format(int $amountMinor, string $currency): string
    {
        return $amountMinor . ' ' . $currency;
    }
};

$productService = new ProductService($products);
$cartSessions = new CartSessionService(
    $store,
    new CartService(),
    $productService,
    new VariationService(),
    new ProductConfigurationService($productService, new VariationService(), $configuration),
    $formatter,
    null,
    $tables,
    true
);
$locks = new class implements LockProviderInterface {
    public function synchronized(string $key, callable $callback)
    {
        return $callback();
    }
};
$heldCarts = new SuspendedCartService(
    $repository,
    $store,
    new WooCommerceCartSerializer(),
    $cartSessions,
    $locks,
    static function (int $timestamp): string { return gmdate('Y-m-d H:i', $timestamp); }
);

$cart = $store->create('VND');
$cart->addItem(CartItem::create(11, 0, 2, Money::fromMinor(25000, 'VND'), null, null, 'Warm', ['product_name' => 'Coffee']));
$cart->setOrderType(OrderType::dineIn(), TableContext::from(5, 'Table 5'));
$cart->setOrderNote('Customer will return.');
$sessionId = $cart->posSessionId();
$heldId = 0;

$test('hold persists trusted snapshot and resets the same active session', static function () use ($assert, $heldCarts, $repository, $sessionId, &$heldId): void {
    $result = $heldCarts->hold(7, 'Table 5 - Minh', $sessionId, 0);
    $heldId = (int) $result['held_cart']['id'];
    $assert($heldId > 0 && count($repository->rows) === 1, 'Held snapshot was not persisted.');
    $assert($result['cart']['pos_session_id'] === $sessionId, 'Holding replaced the paired POS session.');
    $assert($result['cart']['revision'] === 1 && $result['cart']['items'] === [], 'Current cart was not reset atomically.');
    $assert($result['cart']['order_type'] === 'takeaway', 'Reset cart retained service context.');
    $assert($repository->rows[$heldId]['cart_payload']['order_note'] === 'Customer will return.', 'Trusted order note was not captured.');
});

$test('list is owner scoped and exposes a compact projection', static function () use ($assert, $heldCarts, &$heldId): void {
    $items = $heldCarts->list(7);
    $assert(count($items) === 1 && $items[0]['id'] === $heldId, 'Owner list did not return the held cart.');
    $assert($items[0]['total_quantity'] === 2 && $items[0]['table_label'] === 'Table 5', 'Held cart summary is incomplete.');
    $assert($heldCarts->list(8) === [], 'Another user can list the held cart.');
    $assert(! array_key_exists('cart_payload', $items[0]), 'Raw snapshot leaked through the list projection.');
});

$test('resume revalidates current product data and preserves the paired session', static function () use ($assert, $heldCarts, $repository, $sessionId, &$heldId): void {
    $result = $heldCarts->resume($heldId, 7, $sessionId, 1);
    $assert($result['cart']['pos_session_id'] === $sessionId, 'Resume replaced the paired POS session.');
    $assert($result['cart']['revision'] === 2, 'Resume did not advance the cart revision.');
    $assert($result['cart']['items'][0]['unit_price']['amount_minor'] === 30000, 'Resume trusted the stale snapshot price.');
    $assert($result['cart']['table']['table_label'] === 'Table 5', 'Resume lost the table context.');
    $assert($result['cart']['order_note'] === 'Customer will return.', 'Resume lost the order note.');
    $assert($repository->rows === [], 'Successfully resumed cart was not deleted from held storage.');
});

$secondHeldId = 0;
$test('resume refuses to overwrite a non-empty current cart', static function () use ($assert, $heldCarts, $cartSessions, $repository, $sessionId, &$secondHeldId): void {
    $held = $heldCarts->hold(7, 'Second hold', $sessionId, 2);
    $secondHeldId = (int) $held['held_cart']['id'];
    $cartSessions->addItem($sessionId, 3, ['product_id' => 11, 'variation_id' => 0, 'quantity' => 1]);
    try {
        $heldCarts->resume($secondHeldId, 7, $sessionId, 4);
    } catch (Phase01Exception $error) {
        $assert($error->errorCode() === Phase01ErrorCodes::HELD_CART_STATE_CONFLICT, 'Wrong non-empty resume error.');
        $assert(isset($repository->rows[$secondHeldId]), 'Rejected resume deleted the held cart.');
        return;
    }
    throw new RuntimeException('Resume overwrote a non-empty cart.');
});

$test('delete is owner scoped', static function () use ($assert, $heldCarts, $repository, &$secondHeldId): void {
    try {
        $heldCarts->delete($secondHeldId, 8);
    } catch (Phase01Exception $error) {
        $assert($error->errorCode() === Phase01ErrorCodes::HELD_CART_NOT_FOUND, 'Wrong ownership error.');
    }
    $heldCarts->delete($secondHeldId, 7);
    $assert($repository->rows === [], 'Held cart was not deleted by its owner.');
});

$test('held-cart API and PHP-owned UI contracts are wired', static function () use ($assert, $root): void {
    $template = (string) file_get_contents($root . '/templates/components/held-carts.php');
    $client = (string) file_get_contents($root . '/assets/js/api/client.js');
    $screen = (string) file_get_contents($root . '/assets/js/screens/cashier.js');
    $assert(strpos($template, 'coffeepos-held-cart-template') !== false, 'Native held-cart template is missing.');
    foreach (['holdCart', 'loadHeldCarts', 'resumeHeldCart', 'deleteHeldCart'] as $operation) {
        $assert(strpos($client, $operation) !== false, 'API operation missing: ' . $operation);
    }
    $assert(strpos($screen, "action === 'open-held-carts'") !== false, 'Cashier held-cart action is not wired.');
});

exit($failures === [] ? 0 : 1);
