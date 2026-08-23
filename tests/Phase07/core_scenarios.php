<?php

declare(strict_types=1);

use CoffeePOS\Application\Contracts\OperationalOrderGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Operations\OperationalOrderService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (! $condition) { $failures[] = $message; } };
$source = static function (string $path): string { $value = file_get_contents(dirname(__DIR__, 2) . '/' . $path); return $value === false ? '' : $value; };

$record = static function (int $id, string $state = 'new', int $revision = 0, string $type = 'takeaway'): array {
    return [
        'id' => $id, 'number' => (string) $id, 'eligible' => true,
        'created_at' => '2026-08-23T07:00:00Z', 'received_at' => '2026-08-23T07:00:00Z', 'received_time' => '07:00',
        'customer' => ['display_name' => 'Guest', 'is_guest' => true],
        'service' => ['order_type' => $type, 'table_label' => ''], 'service_label' => $type === 'dine_in' ? 'Dine-in' : 'Takeaway',
        'total' => ['amount' => '50.00', 'currency' => 'VND', 'display' => '50.000 ₫'],
        'woocommerce_status' => 'processing', 'receipt_available' => true,
        'items' => [['order_item_id' => $id * 10, 'product_name' => 'Latte', 'quantity' => 1, 'variation_summary' => 'Large', 'modifier_summary' => '', 'quick_note_summary' => 'Less ice', 'custom_note' => 'No sugar']],
        'kds' => ['state' => $state, 'revision' => $revision, 'started_at' => null, 'ready_at' => null, 'completed_at' => null],
        '_operations' => [],
    ];
};

$gateway = new class([$record(101), $record(102, 'preparing', 2, 'dine_in')]) implements OperationalOrderGatewayInterface {
    public array $orders = [];
    public int $saves = 0;
    public function __construct(array $orders) { foreach ($orders as $order) { $this->orders[$order['id']] = $order; } }
    public function listActive(int $limit): array { return array_slice(array_values(array_filter($this->orders, static function (array $order): bool { return in_array($order['kds']['state'], ['new', 'preparing', 'ready'], true); })), 0, $limit); }
    public function find(int $orderId): ?array { return $this->orders[$orderId] ?? null; }
    public function saveTransition(int $orderId, int $expectedRevision, string $expectedState, array $changes): array {
        $current = $this->orders[$orderId];
        if ($current['kds']['revision'] !== $expectedRevision || $current['kds']['state'] !== $expectedState) { throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_STATE_CONFLICT, 'Conflict'); }
        $this->saves++; $current['kds']['state'] = $changes['state']; $current['kds']['revision'] = $changes['revision']; $current['_operations'] = $changes['operations'];
        if ($changes['state'] === 'completed') { $current['woocommerce_status'] = 'completed'; }
        if ($changes['state'] === 'cancelled') { $current['woocommerce_status'] = 'cancelled'; }
        return $this->orders[$orderId] = $current;
    }
};

$service = new OperationalOrderService($gateway);
$kds = $service->listKds(['new', 'preparing', 'ready'], 100);
$assert(count($kds) === 2 && $kds[0]['next_action']['target_state'] === 'preparing', 'TC-01/05 KDS eligibility/projection failed.');
$queue = $service->listQueue('preparing', 'dine_in', 100);
$assert(count($queue) === 1 && $queue[0]['actions']['can_cancel'] === true, 'TC-08/09 Queue filters/actions failed.');

$start = $service->transition(101, 'new', 0, 'preparing', 'operation-start-101', 9, 'kds');
$assert($start['order']['kds']['state'] === 'preparing' && $start['order']['kds']['revision'] === 1, 'TC-10 state/revision failed.');
$retry = $service->transition(101, 'new', 0, 'preparing', 'operation-start-101', 9, 'kds');
$assert($gateway->saves === 1 && $retry['order']['kds']['state'] === 'preparing', 'TC-21 identical retry was not idempotent.');
try { $service->transition(101, 'new', 0, 'cancelled', 'operation-start-101', 9, 'kds'); $assert(false, 'TC-22 conflicting operation ID accepted.'); }
catch (Phase01Exception $error) { $assert($error->errorCode() === Phase01ErrorCodes::DUPLICATE_OPERATION_CONFLICT, 'TC-22 wrong error.'); }
try { $service->transition(101, 'new', 0, 'preparing', 'operation-stale-101', 9, 'kds'); $assert(false, 'TC-20 stale state accepted.'); }
catch (Phase01Exception $error) { $assert($error->errorCode() === Phase01ErrorCodes::ORDER_STATE_CONFLICT, 'TC-20 wrong conflict.'); }

$ready = $service->transition(101, 'preparing', 1, 'ready', 'operation-ready-101', 9, 'kds');
$assert($ready['queue_order']['actions']['can_complete'] === true, 'TC-11 ready/Queue complete action failed.');
$complete = $service->transition(101, 'ready', 2, 'completed', 'operation-complete-101', 9, 'order_queue');
$assert($complete['queue_order'] === null && $complete['order']['kds']['state'] === 'completed', 'TC-12 completion did not leave active queue.');

$cancel = $service->transition(102, 'preparing', 2, 'cancelled', 'operation-cancel-102', 9, 'order_queue', 'Customer request');
$assert($cancel['order']['kds']['state'] === 'cancelled' && $cancel['queue_order'] === null, 'TC-14 cancellation failed.');

$routes = $source('includes/REST/OperationalOrderController.php');
$polling = $source('assets/js/core/polling-controller.js');
$timer = $source('assets/js/components/kds-timer.js');
$sound = $source('assets/js/components/kds-sound.js');
$templates = $source('templates/components/kds-templates.php') . $source('templates/components/order-queue-templates.php');
$gatewaySource = $source('includes/Integration/WooCommerce/WooCommerceOperationalOrderGateway.php');
$orderGatewaySource = $source('includes/Integration/WooCommerce/WooCommerceOrderGateway.php');

$assert(strpos($routes, "'/kds/orders'") !== false && strpos($routes, "'/order-queue/orders'") !== false, 'TC-25/26 operational routes missing.');
$assert(strpos($polling, 'setInterval') === false && strpos($polling, 'setTimeout') !== false && strpos($polling, 'AbortController') !== false, 'TC-35/36 polling overlap contract missing.');
$assert(strpos($timer, 'setInterval') !== false && strpos($timer, 'seconds >= 600') !== false && strpos($timer, 'seconds >= 300') !== false, 'TC-47-51 timer thresholds missing.');
$assert(strpos($sound, 'localStorage') !== false && strpos($sound, 'initialized') !== false, 'TC-52-56 sound baseline/preference missing.');
$assert(strpos($templates, '<template') !== false && strpos($templates, 'data-action="transition-kds-order"') !== false && strpos($templates, 'data-action="cancel-order"') !== false, 'TC-57/58 PHP template hooks missing.');
$assert(strpos($gatewaySource, 'wc_get_orders') !== false && strpos($gatewaySource, 'update_post_meta') === false, 'TC-24 HPOS CRUD contract failed.');
$assert(strpos($orderGatewaySource, 'completePaymentForPreparation') !== false && strpos($orderGatewaySource, "? 'processing' : \$status") !== false, 'TC-29 paid POS orders must remain processing until KDS completion.');

if ($failures !== []) { fwrite(STDERR, "Phase 07 scenarios failed:\n- " . implode("\n- ", $failures) . "\n"); exit(1); }
echo "Phase 07 core scenarios passed.\n";
