<?php

declare(strict_types=1);

use CoffeePOS\Application\Contracts\LockProviderInterface;
use CoffeePOS\Application\Contracts\ShiftRepositoryInterface;
use CoffeePOS\Application\Contracts\ShiftTotalsGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Shift\ShiftService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (! function_exists('sanitize_textarea_field')) { function sanitize_textarea_field($v) { return trim(strip_tags((string) $v)); } }
if (! function_exists('wc_get_price_decimals')) { function wc_get_price_decimals() { return 2; } }
if (! function_exists('get_woocommerce_currency')) { function get_woocommerce_currency() { return 'VND'; } }
if (! function_exists('get_the_author_meta')) { function get_the_author_meta($key, $id) { return 'Cashier ' . $id; } }
if (! function_exists('mysql2date')) { function mysql2date($format, $date) { return gmdate('c', strtotime($date . ' UTC')); } }

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (! $condition) { $failures[] = $message; } };
$repository = new class implements ShiftRepositoryInterface {
    public array $rows = []; public int $next = 1;
    public function findOpenByUser(int $userId): ?array { foreach ($this->rows as $r) { if ((int)$r['user_id'] === $userId && $r['status'] === 'open') return $r; } return null; }
    public function findById(int $id): ?array { return $this->rows[$id] ?? null; }
    public function create(int $userId, string $cash, string $note): array { $id=$this->next++; return $this->rows[$id]=['id'=>$id,'user_id'=>$userId,'status'=>'open','opened_at'=>'2026-08-23 08:00:00','opening_cash'=>$cash,'opening_note'=>$note,'closed_at'=>null,'actual_cash'=>null,'closing_note'=>null]; }
    public function close(int $id, int $userId, string $cash, string $note): array { if (!isset($this->rows[$id]) || $this->rows[$id]['status']!=='open') throw Phase01Exception::withCode(Phase01ErrorCodes::SHIFT_STATE_CONFLICT,'conflict'); $this->rows[$id]['status']='closed';$this->rows[$id]['closed_at']='2026-08-23 10:00:00';$this->rows[$id]['actual_cash']=$cash;$this->rows[$id]['closing_note']=$note;return $this->rows[$id]; }
    public function history(int $userId, int $limit): array { return array_values(array_filter($this->rows, static fn($r) => (int)$r['user_id']===$userId && $r['status']==='closed')); }
};
$totals = new class implements ShiftTotalsGatewayInterface { public function totals(int $id): array { return ['cash_sales'=>100.0,'bank_sales'=>50.0,'total_sales'=>150.0,'order_count'=>2]; } };
$locks = new class implements LockProviderInterface { public array $keys=[]; public function synchronized(string $key, callable $callback) { $this->keys[]=$key; return $callback(); } };
$service = new ShiftService($repository, $totals, $locks);

$shift = $service->open(7, '25', 'Morning');
$assert($shift['status']==='open' && $shift['expected_cash']==='125.00', 'TC-01 open/expected cash failed.');
$assert($shift['cash_sales']==='100.00' && $shift['bank_sales']==='50.00' && $shift['order_count']===2, 'TC-02 authoritative totals failed.');
try { $service->open(7, '0', 'duplicate'); $assert(false, 'TC-03 duplicate active shift accepted.'); } catch (Phase01Exception $e) { $assert($e->errorCode()===Phase01ErrorCodes::SHIFT_ALREADY_OPEN, 'TC-03 wrong duplicate error.'); }
try { $service->open(8, '-1', 'bad'); $assert(false, 'TC-04 negative amount accepted.'); } catch (Phase01Exception $e) { $assert($e->errorCode()===Phase01ErrorCodes::INVALID_SHIFT_AMOUNT, 'TC-04 wrong amount error.'); }
$closed = $service->close((int)$shift['id'], 7, '120', 'Done');
$assert($closed['status']==='closed' && $closed['variance']==='-5.00', 'TC-05 close/variance failed.');
$assert(count($service->history(7))===1, 'TC-06 closed shift history failed.');
try { $service->requireOpen(7); $assert(false, 'TC-07 checkout accepted without open shift.'); } catch (Phase01Exception $e) { $assert($e->errorCode()===Phase01ErrorCodes::SHIFT_REQUIRED, 'TC-07 wrong required error.'); }

$root = dirname(__DIR__, 2);
$source = static function (string $path) use ($root): string { return (string) file_get_contents($root . '/' . $path); };
$assert(strpos($source('includes/Integration/WooCommerce/WooCommerceOrderGateway.php'), "'_coffeepos_shift_id'")!==false, 'TC-08 order shift association missing.');
$controller=$source('includes/REST/ShiftController.php');
$assert(strpos($controller,"'/shifts/current'")!==false && strpos($controller,"'/shifts/open'")!==false && strpos($controller,"'/shifts/(?P<id>\\d+)/close'")!==false, 'TC-09 REST routes missing.');
$ui=$source('templates/shifts/content.php').$source('assets/js/screens/shifts.js');
$assert(strpos($ui,'data-template="shift-history-row"')!==false && strpos($ui,'innerHTML')===false, 'TC-10 PHP template ownership failed.');

if ($failures) { foreach ($failures as $failure) echo '[FAIL] '.$failure.PHP_EOL; exit(1); }
echo "Phase 09 core scenarios passed.\n";
