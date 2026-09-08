<?php

declare(strict_types=1);

use CoffeePOS\Application\Contracts\StockManagementGatewayInterface;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Product\StockManagementService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

final class StockScenarioGateway implements StockManagementGatewayInterface
{
    public array $target = ['id' => 20, 'name' => 'Coffee', 'manages_quantity' => true, 'quantity' => 4, 'stock_status' => 'instock', 'is_in_stock' => true];

    public function projection(int $productId): ?array { return $productId === 10 ? ['product_id' => 10, 'product_name' => 'Coffee', 'targets' => [$this->target]] : null; }
    public function setQuantity(int $targetId, int $quantity): array { $this->target['quantity'] = $quantity; $this->target['is_in_stock'] = $quantity > 0; $this->target['stock_status'] = $quantity > 0 ? 'instock' : 'outofstock'; return $this->target; }
    public function setStatus(int $targetId, string $status): array { $this->target['stock_status'] = $status; $this->target['is_in_stock'] = $status === 'instock'; return $this->target; }
}

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (! $condition) { $failures[] = $message; } };
$gateway = new StockScenarioGateway();
$service = new StockManagementService($gateway);
$result = $service->update(10, ['target_id' => 20, 'mode' => 'quantity', 'quantity' => 0, 'reason' => 'Sold out']);
$assert($result['updated_target']['quantity'] === 0 && $result['stock']['targets'][0]['is_in_stock'] === false, 'Absolute quantity update failed.');
$gateway->target = ['id' => 20, 'name' => 'Coffee', 'manages_quantity' => false, 'quantity' => null, 'stock_status' => 'outofstock', 'is_in_stock' => false];
$result = $service->update(10, ['target_id' => 20, 'mode' => 'status', 'stock_status' => 'instock', 'reason' => 'New delivery']);
$assert($result['updated_target']['stock_status'] === 'instock', 'Stock-status update failed.');
$gateway->target['manages_quantity'] = true;

foreach ([
    ['target_id' => 999, 'mode' => 'quantity', 'quantity' => 2, 'reason' => 'Delivery'],
    ['target_id' => 20, 'mode' => 'quantity', 'quantity' => -1, 'reason' => 'Correction'],
    ['target_id' => 20, 'mode' => 'quantity', 'quantity' => 2, 'reason' => 'x'],
] as $payload) {
    try { $service->update(10, $payload); $assert(false, 'Invalid stock mutation was accepted.'); } catch (Phase01Exception $exception) { $assert(true, 'Invalid stock mutation rejected.'); }
}

$root = dirname(__DIR__, 2);
$source = static function (string $path) use ($root): string { return (string) file_get_contents($root . '/' . $path); };
$server = $source('includes/REST/StockController.php') . $source('includes/Support/Capabilities.php') . $source('includes/REST/RouteRegistrar.php');
$client = $source('templates/cashier/menu-panel.php') . $source('templates/components/stock-modal.php') . $source('assets/js/components/product-card.js') . $source('assets/js/components/stock-modal.js') . $source('assets/js/api/client.js');
$assert(strpos($server, 'MANAGE_STOCK') !== false && strpos($server, '/products/(?P<id>\\d+)/stock') !== false, 'Stock route capability wiring is incomplete.');
$assert(strpos($client, 'edit-product-stock') !== false && strpos($client, 'loadProductStock') !== false && strpos($client, 'updateProductStock') !== false, 'Cashier stock editor wiring is incomplete.');

if ($failures !== []) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
fwrite(STDOUT, "Stock management scenarios passed.\n");
