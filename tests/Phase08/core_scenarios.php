<?php

declare(strict_types=1);

use CoffeePOS\Application\Contracts\CustomerCreationGatewayInterface;
use CoffeePOS\Application\Contracts\LockProviderInterface;
use CoffeePOS\Application\Customer\CustomerPhone;
use CoffeePOS\Application\Customer\CustomerService;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Projection\CustomerCartView;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try {
        $callback();
        echo '[PASS] ' . $name . PHP_EOL;
    } catch (Throwable $throwable) {
        $failures[] = $name . ': ' . $throwable->getMessage();
        echo '[FAIL] ' . end($failures) . PHP_EOL;
    }
};
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

$gateway = new class implements CustomerCreationGatewayInterface {
    public array $customers = [
        7 => ['id' => 7, 'name' => 'Existing Member', 'phone' => '0353123250', 'email' => 'member@example.test'],
    ];
    public array $operations = [];
    public int $creates = 0;

    public function findById(int $customerId): ?array
    {
        return $this->customers[$customerId] ?? null;
    }

    public function findByPhone(string $phone): ?array
    {
        foreach ($this->customers as $customer) {
            if (CustomerPhone::normalize((string) $customer['phone']) === CustomerPhone::normalize($phone)) {
                return $customer;
            }
        }
        return null;
    }

    public function findByCreationOperation(string $operationId): ?array
    {
        $customerId = $this->operations[$operationId] ?? 0;
        return $customerId ? $this->customers[$customerId] : null;
    }

    public function createCustomer(array $customer, string $operationId, string $fingerprint): array
    {
        $this->creates++;
        $id = 100 + $this->creates;
        $this->customers[$id] = [
            'id' => $id,
            'name' => $customer['display_name'],
            'phone' => $customer['phone'],
            'email' => $customer['email'],
            'creation_operation_id' => $operationId,
            'creation_fingerprint' => $fingerprint,
        ];
        $this->operations[$operationId] = $id;
        return $this->customers[$id];
    }
};

$lock = new class implements LockProviderInterface {
    public array $keys = [];

    public function synchronized(string $key, callable $callback)
    {
        $this->keys[] = $key;
        return $callback();
    }
};

$service = new CustomerService($gateway, null, $lock);

$test('TC-01/02 phone normalization and masking', static function () use ($assert): void {
    $assert(CustomerPhone::normalize('0353 123 250') === '0353123250', 'Local phone normalization failed.');
    $assert(CustomerPhone::normalize('+84 353 123 250') === '0353123250', '+84 normalization failed.');
    $assert(CustomerPhone::normalize('12') === '', 'Incomplete phone was accepted.');
    $assert(CustomerPhone::mask('0353123250') === '0353***250', 'Phone masking contract failed.');
    $assert(CustomerPhone::mask('123') === '', 'Short phone leaked through masking.');
});

$test('TC-03 existing member projection includes cashier and masked fields', static function () use ($assert, $service): void {
    $customer = $service->findByPhone('+84 353 123 250')->toArray();
    $assert($customer['customer_id'] === 7, 'Existing member lookup failed.');
    $assert($customer['phone'] === '0353123250', 'Cashier phone projection missing.');
    $assert($customer['phone_masked'] === '0353***250', 'Masked projection missing.');
    $assert($customer['email'] === 'member@example.test', 'Authorized email projection missing.');
});

$test('TC-04 member creation and idempotent replay', static function () use ($assert, $service, $gateway, $lock): void {
    $input = [
        'display_name' => 'New Member',
        'phone' => '0901-234-567',
        'email' => '',
        'client_operation_id' => 'member-create-0001',
    ];
    $created = $service->createMember($input);
    $replayed = $service->createMember($input);
    $assert($created['replayed'] === false && $replayed['replayed'] === true, 'Creation replay flag is wrong.');
    $assert($gateway->creates === 1, 'Idempotent retry created a duplicate member.');
    $assert($created['customer']->toArray()['phone'] === '0901234567', 'Created phone was not normalized.');
    $assert(strpos($lock->keys[0], '0901234567') === false, 'Raw phone leaked into lock key.');
});

$test('TC-05 changed idempotent payload is rejected', static function () use ($assert, $service): void {
    try {
        $service->createMember([
            'display_name' => 'Changed Member',
            'phone' => '0901234567',
            'email' => '',
            'client_operation_id' => 'member-create-0001',
        ]);
    } catch (Phase01Exception $exception) {
        $assert($exception->errorCode() === Phase01ErrorCodes::IDEMPOTENCY_KEY_REUSED, 'Wrong idempotency reuse error.');
        return;
    }
    throw new RuntimeException('Changed idempotent payload was accepted.');
});

$test('TC-06 duplicate phone offers existing member', static function () use ($assert, $service): void {
    try {
        $service->createMember([
            'display_name' => 'Duplicate',
            'phone' => '0353123250',
            'email' => '',
            'client_operation_id' => 'member-create-0002',
        ]);
    } catch (Phase01Exception $exception) {
        $assert($exception->errorCode() === Phase01ErrorCodes::CUSTOMER_PHONE_EXISTS, 'Wrong duplicate-phone error.');
        $assert(($exception->context()['customer']['customer_id'] ?? 0) === 7, 'Existing candidate was not returned safely.');
        return;
    }
    throw new RuntimeException('Duplicate phone was accepted.');
});

$test('TC-07 invalid member fields are rejected', static function () use ($assert, $service): void {
    foreach ([
        [['display_name' => '', 'phone' => '0902222333', 'email' => '', 'client_operation_id' => 'member-invalid-01'], Phase01ErrorCodes::INVALID_CUSTOMER_NAME],
        [['display_name' => 'Member', 'phone' => '12', 'email' => '', 'client_operation_id' => 'member-invalid-02'], Phase01ErrorCodes::INVALID_CUSTOMER_PHONE],
        [['display_name' => 'Member', 'phone' => '0902222333', 'email' => 'invalid', 'client_operation_id' => 'member-invalid-03'], Phase01ErrorCodes::INVALID_CUSTOMER_EMAIL],
    ] as $scenario) {
        try {
            $service->createMember($scenario[0]);
        } catch (Phase01Exception $exception) {
            $assert($exception->errorCode() === $scenario[1], 'Wrong validation error code.');
            continue;
        }
        throw new RuntimeException('Invalid member input was accepted.');
    }
});

$test('TC-08 Customer Display projection excludes private identity', static function () use ($assert): void {
    $projection = CustomerCartView::fromArray([
        'pos_session_id' => '1234567890abcdef',
        'revision' => 2,
        'customer' => [
            'is_guest' => false,
            'customer_id' => 7,
            'display_name' => 'Existing Member',
            'phone' => '0353123250',
            'phone_masked' => '0353***250',
            'email' => 'private@example.test',
            'membership' => null,
        ],
        'items' => [],
    ]);
    $assert($projection['customer']['mode'] === 'member', 'Display member mode missing.');
    $assert($projection['customer']['phone_masked'] === '0353***250', 'Display masked phone missing.');
    $assert(! isset($projection['customer']['phone']), 'Full phone leaked to display projection.');
    $assert(! isset($projection['customer']['email']), 'Email leaked to display projection.');
    $assert(! isset($projection['customer']['customer_id']), 'Customer id leaked to display projection.');
});

$test('TC-09 Phase-08 API/template wiring', static function () use ($assert, $root): void {
    $controller = (string) file_get_contents($root . '/includes/REST/CartController.php');
    $client = (string) file_get_contents($root . '/assets/js/api/client.js');
    $component = (string) file_get_contents($root . '/assets/js/components/cart-context.js');
    $templates = (string) file_get_contents($root . '/templates/components/context-dialogs.php');
    $display = (string) file_get_contents($root . '/templates/customer/cart.php');
    $assert(strpos($controller, "'/customers'") !== false, 'Create-customer REST route missing.');
    $assert(strpos($client, 'createCustomer') !== false, 'Create-customer API client missing.');
    $assert(strpos($component, 'window.setTimeout(lookup, 400)') !== false, 'Automatic lookup debounce missing.');
    $assert(strpos($component, 'AbortController') !== false, 'Superseded lookup cancellation missing.');
    $assert(strpos($templates, 'data-component="customer-create-form"') !== false, 'PHP-owned create-member form missing.');
    $assert(strpos($display, 'customer-phone-masked') !== false, 'Display masked-phone field missing.');
});

if ($failures !== []) {
    fwrite(STDERR, "Phase 08 scenarios failed:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Phase 08 core scenarios passed.\n";
