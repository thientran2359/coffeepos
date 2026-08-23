<?php

declare(strict_types=1);

use CoffeePOS\Application\Contracts\MoneyFormatterInterface;
use CoffeePOS\Application\Contracts\ReportOrderGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Reports\ReportExporter;
use CoffeePOS\Application\Reports\SalesReportService;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (! function_exists('sanitize_key')) { function sanitize_key($value) { return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $value)); } }
if (! function_exists('sanitize_file_name')) { function sanitize_file_name($value) { return (string) preg_replace('/[^a-zA-Z0-9._\-]/', '-', (string) $value); } }
if (! function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($value) { return strip_tags((string) $value); } }
if (! function_exists('wp_timezone')) { function wp_timezone() { return new DateTimeZone('Asia/Bangkok'); } }
if (! function_exists('__')) { function __($value) { return (string) $value; } }

$failures = [];
$assert = static function (bool $ok, string $message) use (&$failures): void { if (! $ok) { $failures[] = $message; } };

$gateway = new class implements ReportOrderGatewayInterface {
    public array $criteria = [];
    public function orders(array $criteria): iterable
    {
        $this->criteria = $criteria;
        yield [
            'currency' => 'VND', 'decimals' => 0, 'total' => '100000', 'refunded' => '10000', 'payment_method' => 'cash', 'hour' => 8,
            'items' => [
                ['product_id' => 1, 'variation_id' => 0, 'name' => 'Coffee', 'quantity' => 2, 'refunded_quantity' => 0, 'total' => '80000', 'refunded' => '10000'],
                ['product_id' => 2, 'variation_id' => 0, 'name' => 'Cake', 'quantity' => 1, 'refunded_quantity' => 0, 'total' => '20000', 'refunded' => '0'],
            ],
        ];
        yield [
            'currency' => 'VND', 'decimals' => 0, 'total' => '60000', 'refunded' => '0', 'payment_method' => 'bank_transfer', 'hour' => 9,
            'items' => [['product_id' => 1, 'variation_id' => 0, 'name' => 'Coffee', 'quantity' => 1, 'refunded_quantity' => 0, 'total' => '60000', 'refunded' => '0']],
        ];
        yield [
            'currency' => 'USD', 'decimals' => 2, 'total' => '10.00', 'refunded' => '2.00', 'payment_method' => '', 'hour' => 8,
            'items' => [['product_id' => 3, 'variation_id' => 7, 'name' => '=Legacy item', 'quantity' => 2, 'refunded_quantity' => 1, 'total' => '10.00', 'refunded' => '0.00']],
        ];
    }
};
$formatter = new class implements MoneyFormatterInterface {
    public function format(int $minor, string $currency): string { return $minor . ' ' . $currency; }
};
$now = static function (): DateTimeImmutable { return new DateTimeImmutable('2026-08-23 12:00:00', new DateTimeZone('Asia/Bangkok')); };
$service = new SalesReportService($gateway, $formatter, $now);
$report = $service->generate(['preset' => 'custom', 'date_from' => '2026-08-01', 'date_to' => '2026-08-23', 'product_limit' => 10]);

$assert($report['summary']['order_count'] === 3 && $report['summary']['products_sold'] === 5, 'TC-01 global order/product summary is incorrect.');
$assert(count($report['currency_groups']) === 2, 'TC-02 currencies were combined.');
$usd = $report['currency_groups'][0];
$vnd = $report['currency_groups'][1];
$assert($usd['currency'] === 'USD' && $usd['net_revenue']['amount'] === '8.00', 'TC-03 USD/refund projection is incorrect.');
$assert($vnd['currency'] === 'VND' && $vnd['gross_revenue']['amount'] === '160000' && $vnd['net_revenue']['amount'] === '150000', 'TC-04 VND revenue formula is incorrect.');
$assert($vnd['aov']['amount'] === '75000' && $vnd['products_sold'] === 4, 'TC-05 AOV/product quantity is incorrect.');
$assert($vnd['payments'][0]['method'] === 'cash' && $vnd['payments'][0]['net']['amount'] === '90000', 'TC-06 cash composition is incorrect.');
$assert($vnd['top_by_revenue'][0]['name'] === 'Coffee' && $vnd['top_by_revenue'][0]['revenue']['amount'] === '130000', 'TC-07 product aggregation/ranking is incorrect.');
$assert(count($vnd['peak_hours']) === 24 && $vnd['peak_hours'][8]['order_count'] === 1 && $vnd['peak_hours'][9]['order_count'] === 1, 'TC-08 peak-hour projection is incorrect.');
$assert($usd['unknown_payment_orders'] === 1 && $usd['unallocated_refund']['amount'] === '2.00', 'TC-09 unknown payment/unallocated refund is incorrect.');
$assert(count($report['warnings']) >= 3, 'TC-10 data-quality warnings are missing.');
$assert($gateway->criteria['date_from_ts'] < $gateway->criteria['date_to_ts'], 'TC-11 normalized date timestamps were not sent to the gateway.');

try {
    $service->generate(['preset' => 'custom', 'date_from' => '2024-01-01', 'date_to' => '2026-08-23']);
    $assert(false, 'TC-12 oversized report range was accepted.');
} catch (Phase01Exception $exception) {
    $assert($exception->errorCode() === Phase01ErrorCodes::REPORT_RANGE_TOO_LARGE, 'TC-12 wrong oversized-range error.');
}
try {
    $service->generate(['preset' => 'custom', 'date_from' => '2026-02-30', 'date_to' => '2026-03-01']);
    $assert(false, 'TC-13 invalid calendar date was accepted.');
} catch (Phase01Exception $exception) {
    $assert($exception->errorCode() === Phase01ErrorCodes::INVALID_REPORT_RANGE, 'TC-13 wrong invalid-date error.');
}

$exporter = new ReportExporter();
$csv = $exporter->export($report, 'csv');
$assert(substr($csv['content'], 0, 3) === "\xEF\xBB\xBF" && strpos($csv['content'], "'=Legacy item") !== false, 'TC-14 CSV BOM/formula-injection protection failed.');
if (class_exists('ZipArchive')) {
    $xlsx = $exporter->export($report, 'xlsx');
    $assert(substr($xlsx['content'], 0, 2) === 'PK' && strpos($xlsx['content_type'], 'spreadsheetml') !== false, 'TC-15 XLSX package generation failed.');
    $temporary = tempnam(sys_get_temp_dir(), 'coffeepos-phase11-');
    file_put_contents($temporary, $xlsx['content']);
    $archive = new ZipArchive();
    $opened = $archive->open($temporary) === true;
    $workbook = $opened ? (string) $archive->getFromName('xl/workbook.xml') : '';
    $summarySheet = $opened ? (string) $archive->getFromName('xl/worksheets/sheet1.xml') : '';
    if ($opened) { $archive->close(); }
    @unlink($temporary);
    $assert($opened && strpos($workbook, 'Peak Hours') !== false && strpos($summarySheet, 'Net revenue') !== false, 'TC-15 XLSX workbook/worksheet content is invalid.');
}

$root = dirname(__DIR__, 2);
$source = static function (string $path) use ($root): string { return (string) file_get_contents($root . '/' . $path); };
$gatewaySource = $source('includes/Integration/WooCommerce/WooCommerceReportOrderGateway.php');
$assert(strpos($gatewaySource, 'wc_get_orders') !== false && strpos($gatewaySource, "'limit' => self::BATCH_SIZE") !== false && strpos($gatewaySource, '$wpdb') === false, 'TC-16 HPOS/bounded query contract is missing.');
$controllerSource = $source('includes/REST/ReportController.php');
$assert(strpos($controllerSource, 'VIEW_REPORTS') !== false && strpos($controllerSource, 'X-CoffeePOS-Binary') !== false, 'TC-17 report authorization/binary transport is missing.');
$ui = $source('templates/reports/content.php') . $source('assets/js/screens/reports.js');
$assert(strpos($ui, 'coffeepos-report-currency-template') !== false && strpos($ui, 'TemplateRenderer') !== false && strpos($ui, 'innerHTML') === false, 'TC-18 PHP template ownership failed.');
$assert(strpos($source('includes/Support/Capabilities.php'), "'reports' => self::VIEW_REPORTS") !== false, 'TC-19 manager-only Reports route guard is missing.');
$shiftTotalsSource = $source('includes/Integration/WooCommerce/WooCommerceShiftTotalsGateway.php');
$assert(strpos($shiftTotalsSource, "'limit' => self::BATCH_SIZE") !== false && strpos($shiftTotalsSource, "'limit' => -1") === false, 'TC-20 hardening left shift totals unbounded.');

if ($failures !== []) {
    foreach ($failures as $failure) { echo '[FAIL] ' . $failure . PHP_EOL; }
    exit(1);
}

echo "Phase 11 core scenarios passed.\n";
