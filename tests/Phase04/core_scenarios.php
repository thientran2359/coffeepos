<?php

declare(strict_types=1);

use CoffeePOS\Application\Cart\CartService;
use CoffeePOS\Application\Cart\CartSessionService;
use CoffeePOS\Application\Contracts\CartSessionStoreInterface;
use CoffeePOS\Application\Contracts\CustomerGatewayInterface;
use CoffeePOS\Application\Contracts\MoneyFormatterInterface;
use CoffeePOS\Application\Contracts\MembershipProviderInterface;
use CoffeePOS\Application\Contracts\ProductConfigurationProviderInterface;
use CoffeePOS\Application\Contracts\TableProviderInterface;
use CoffeePOS\Application\Customer\CustomerService;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Product\ProductConfigurationService;
use CoffeePOS\Application\Product\ProductService;
use CoffeePOS\Application\Product\VariationService;
use CoffeePOS\Domain\Cart\Cart;
use CoffeePOS\Domain\Customer\CustomerContext;
use CoffeePOS\Integration\WooCommerce\WooCommerceCartSerializer;

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

$store = new class implements CartSessionStoreInterface {
    /** @var array<string, Cart> */
    public array $carts = [];

    public function create(string $currency): Cart
    {
        $id = 'session-' . (count($this->carts) + 10000000);
        $cart = Cart::createSession($currency, $id, '2026-08-23T00:00:00Z');
        $this->carts[$id] = $cart;
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
        $cart->advanceRevision('2026-08-23T00:00:01Z');
        $this->carts[$cart->posSessionId()] = $cart;
        return $cart;
    }
};

$customerGateway = new class implements CustomerGatewayInterface {
    public function findById(int $customerId): ?array
    {
        return $customerId === 7 ? ['id' => 7, 'name' => 'Trusted Name', 'phone' => '0901234567'] : null;
    }

    public function findByPhone(string $phone): ?array
    {
        return $phone === '0901234567' ? ['id' => 7, 'name' => 'Trusted Name', 'phone' => '0901234567'] : null;
    }
};

$tableProvider = new class implements TableProviderInterface {
    public function listAvailable(): array
    {
        return [['id' => 2, 'label' => 'Table 02', 'enabled' => true, 'sort_order' => 20]];
    }

    public function findAvailableById(int $tableId): ?array
    {
        return $tableId === 2 ? $this->listAvailable()[0] : null;
    }
};

$formatter = new class implements MoneyFormatterInterface {
    public function format(int $amountMinor, string $currency): string
    {
        return $amountMinor . ' ' . $currency;
    }
};

$configurationProvider = new class implements ProductConfigurationProviderInterface {
    public function configurationForProduct(array $product): array
    {
        return ['modifier_groups' => [], 'quick_notes' => []];
    }
};

$productService = new ProductService();
$variationService = new VariationService();
$service = new CartSessionService(
    $store,
    new CartService(),
    $productService,
    $variationService,
    new ProductConfigurationService($productService, $variationService, $configurationProvider),
    $formatter,
    new CustomerService($customerGateway),
    $tableProvider
);

$cart = $service->createSession('VND')->toArray();
$sessionId = $cart['pos_session_id'];

$test('TC-01 guest cart projection', static function () use ($assert, $cart): void {
    $assert($cart['customer']['mode'] === 'guest', 'New cart is not guest.');
    $assert($cart['order_type'] === 'takeaway', 'New cart is not takeaway.');
    $assert($cart['table']['table_id'] === null, 'Guest takeaway cart retained a table.');
});

$test('TC-02 phone lookup and validation', static function () use ($assert, $customerGateway): void {
    $service = new CustomerService($customerGateway);
    $customer = $service->findByPhone('090-123-4567')->toArray();
    $assert($customer['customer_id'] === 7, 'Normalized lookup failed.');
    try {
        $service->findByPhone('12');
    } catch (Phase01Exception $error) {
        $assert($error->errorCode() === Phase01ErrorCodes::INVALID_CUSTOMER_PHONE, 'Wrong invalid-phone code.');
        return;
    }
    throw new RuntimeException('Invalid phone was accepted.');
});

$test('TC-16/17 optional membership projection', static function () use ($assert, $customerGateway): void {
    $provider = new class implements MembershipProviderInterface {
        public function membershipForCustomer(array $customer): ?array
        {
            return ['tier_label' => 'Gold', 'points_display' => '120 points', 'unsafe' => '<b>ignored</b>'];
        }
    };
    $customer = (new CustomerService($customerGateway, $provider))->findById(7)->toArray();
    $assert($customer['membership']['tier_label'] === 'Gold', 'Membership projection missing.');
    $assert(! isset($customer['membership']['unsafe']), 'Unknown membership field leaked.');
});

$test('TC-09/12 attach trusted customer increments revision', static function () use ($assert, $service, $sessionId): void {
    $cart = $service->attachCustomer($sessionId, 0, 7)->toArray();
    $assert($cart['revision'] === 1, 'Attach did not increment revision.');
    $assert($cart['customer']['display_name'] === 'Trusted Name', 'Trusted customer was not projected.');
});

$test('TC-23 dine-in resolves trusted table atomically', static function () use ($assert, $service, $sessionId): void {
    $cart = $service->setServiceContext($sessionId, 1, 'dine_in', 2)->toArray();
    $assert($cart['revision'] === 2, 'Service mutation did not increment revision.');
    $assert($cart['order_type'] === 'dine_in', 'Dine-in was not persisted.');
    $assert($cart['table']['table_label'] === 'Table 02', 'Trusted table label was not resolved.');
});

$test('TC-26 takeaway clears table', static function () use ($assert, $service, $sessionId): void {
    $cart = $service->setServiceContext($sessionId, 2, 'takeaway')->toArray();
    $assert($cart['order_type'] === 'takeaway', 'Takeaway was not persisted.');
    $assert($cart['table']['table_id'] === null, 'Takeaway retained table context.');
});

$test('TC-13 remove customer returns guest', static function () use ($assert, $service, $sessionId): void {
    $cart = $service->removeCustomer($sessionId, 3)->toArray();
    $assert($cart['customer']['mode'] === 'guest', 'Customer was not removed.');
    $assert($cart['revision'] === 4, 'Remove did not increment revision.');
});

$test('TC-25 invalid table rejected without revision', static function () use ($assert, $service, $sessionId): void {
    try {
        $service->setServiceContext($sessionId, 4, 'dine_in', 999);
    } catch (Phase01Exception $error) {
        $assert($error->errorCode() === Phase01ErrorCodes::INVALID_TABLE, 'Wrong invalid-table code.');
        $assert($service->getSession($sessionId)->toArray()['revision'] === 4, 'Rejected mutation changed revision.');
        return;
    }
    throw new RuntimeException('Invalid table was accepted.');
});

$test('TC-31 stale context mutation is rejected', static function () use ($assert, $service, $sessionId): void {
    try {
        $service->removeCustomer($sessionId, 3);
    } catch (Phase01Exception $error) {
        $assert($error->errorCode() === Phase01ErrorCodes::CART_REVISION_CONFLICT, 'Wrong stale-revision code.');
        return;
    }
    throw new RuntimeException('Stale context mutation was accepted.');
});

$test('TC-15/28 context serializer round trip', static function () use ($assert, $store, $sessionId): void {
    $serializer = new WooCommerceCartSerializer();
    $restored = $serializer->fromPayload($serializer->toPayload($store->load($sessionId)));
    $assert($restored->customerContext()->isGuest(), 'Guest context was lost.');
    $assert($restored->orderType()->isTakeaway(), 'Order type was lost.');
    $assert(! $restored->tableContext()->hasTable(), 'Cleared table was restored.');
});

$test('TC-15 membership projection survives session hydration', static function () use ($assert): void {
    $cart = Cart::createSession('VND', 'membership-session', '2026-08-23T00:00:00Z');
    $cart->setCustomerContext(CustomerContext::member(
        7,
        '0901234567',
        'Trusted Name',
        ['tier_label' => 'Gold']
    ));
    $serializer = new WooCommerceCartSerializer();
    $restored = $serializer->fromPayload($serializer->toPayload($cart));
    $assert($restored->customerContext()->membership()['tier_label'] === 'Gold', 'Membership was lost.');
});

$test('TC-36/37 Phase-04 templates and API wiring', static function () use ($assert, $root): void {
    $template = file_get_contents($root . '/templates/components/context-dialogs.php');
    $client = file_get_contents($root . '/assets/js/api/client.js');
    $assert(strpos((string) $template, 'coffeepos-customer-result-template') !== false, 'Customer template missing.');
    $assert(strpos((string) $template, 'coffeepos-table-option-template') !== false, 'Table template missing.');
    foreach (['lookupCustomer', 'attachCustomer', 'removeCustomer', 'setServiceContext'] as $operation) {
        $assert(strpos((string) $client, $operation) !== false, 'API operation missing: ' . $operation);
    }
});

exit($failures === [] ? 0 : 1);
