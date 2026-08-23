<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Reports;

use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;

final class ReportExporter
{
    private XlsxWriter $xlsx;

    public function __construct(?XlsxWriter $xlsx = null)
    {
        $this->xlsx = $xlsx ?? new XlsxWriter();
    }

    public function export(array $report, string $format): array
    {
        $format = sanitize_key($format);
        if (! in_array($format, ['csv', 'xlsx'], true)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::REPORT_EXPORT_FAILED, __('Unsupported report export format.', 'coffeepos'));
        }

        $from = sanitize_file_name((string) ($report['range']['date_from'] ?? 'report'));
        $to = sanitize_file_name((string) ($report['range']['date_to'] ?? 'report'));
        $filename = 'coffeepos-sales-' . $from . '-to-' . $to . '.' . $format;

        if ($format === 'csv') {
            return [
                'content' => $this->csv($report),
                'content_type' => 'text/csv; charset=UTF-8',
                'filename' => $filename,
            ];
        }

        return [
            'content' => $this->xlsx->write($this->worksheets($report)),
            'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'filename' => $filename,
        ];
    }

    private function csv(array $report): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::REPORT_EXPORT_FAILED, __('Could not create the CSV export.', 'coffeepos'));
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['section', 'currency', 'key', 'label', 'order_count', 'quantity', 'gross', 'refund', 'net'], ',', '"', '');
        foreach ($this->normalizedRows($report) as $row) {
            fputcsv($stream, array_map([$this, 'safeCsv'], $row), ',', '"', '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        if (! is_string($content)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::REPORT_EXPORT_FAILED, __('Could not finalize the CSV export.', 'coffeepos'));
        }
        return $content;
    }

    private function normalizedRows(array $report): array
    {
        $rows = [];
        foreach ((array) ($report['currency_groups'] ?? []) as $group) {
            $currency = (string) ($group['currency'] ?? '');
            $rows[] = ['summary', $currency, 'totals', 'Sales totals', (int) $group['order_count'], (int) $group['products_sold'], $group['gross_revenue']['amount'], $group['refund_total']['amount'], $group['net_revenue']['amount']];
            $rows[] = ['summary', $currency, 'aov', 'Average order value', (int) $group['order_count'], '', '', '', $group['aov']['amount']];
            foreach ((array) ($group['payments'] ?? []) as $payment) {
                $rows[] = ['payment', $currency, $payment['method'], $payment['label'], (int) $payment['order_count'], '', $payment['gross']['amount'], $payment['refund']['amount'], $payment['net']['amount']];
            }
            foreach ((array) ($group['top_by_revenue'] ?? []) as $product) {
                $rows[] = ['product', $currency, $product['key'], $product['name'], '', (int) $product['quantity'], '', '', $product['revenue']['amount']];
            }
            foreach ((array) ($group['peak_hours'] ?? []) as $hour) {
                $rows[] = ['peak_hour', $currency, $hour['key'], $hour['label'], (int) $hour['order_count'], '', '', '', $hour['net']['amount']];
            }
        }
        foreach ((array) ($report['warnings'] ?? []) as $index => $warning) {
            $rows[] = ['warning', '', 'warning_' . ($index + 1), (string) $warning, '', '', '', '', ''];
        }
        return $rows;
    }

    private function safeCsv($value): string
    {
        $value = (string) $value;
        if (preg_match('/^[=+\-@]/', $value) === 1) {
            return "'" . $value;
        }
        return $value;
    }

    private function worksheets(array $report): array
    {
        $summary = [['Date from', 'Date to', 'Timezone', 'Currency', 'Orders', 'Products sold', 'Gross revenue', 'Refunds', 'Net revenue', 'AOV']];
        $payments = [['Currency', 'Method', 'Orders', 'Gross revenue', 'Refunds', 'Net revenue']];
        $products = [['Currency', 'Product key', 'Product', 'Quantity', 'Net item revenue', 'Ranking']];
        $hours = [['Currency', 'Hour', 'Orders', 'Net revenue']];
        $warnings = [['Warning']];

        foreach ((array) ($report['currency_groups'] ?? []) as $group) {
            $currency = (string) $group['currency'];
            $summary[] = [
                (string) $report['range']['date_from'], (string) $report['range']['date_to'], (string) $report['range']['timezone'], $currency,
                (int) $group['order_count'], (int) $group['products_sold'],
                $this->number($group['gross_revenue']['amount']), $this->number($group['refund_total']['amount']),
                $this->number($group['net_revenue']['amount']), $this->number($group['aov']['amount']),
            ];
            foreach ((array) $group['payments'] as $payment) {
                $payments[] = [$currency, (string) $payment['label'], (int) $payment['order_count'], $this->number($payment['gross']['amount']), $this->number($payment['refund']['amount']), $this->number($payment['net']['amount'])];
            }
            foreach ((array) $group['top_by_revenue'] as $product) {
                $products[] = [$currency, (string) $product['key'], (string) $product['name'], (int) $product['quantity'], $this->number($product['revenue']['amount']), 'revenue'];
            }
            foreach ((array) $group['top_by_quantity'] as $product) {
                $products[] = [$currency, (string) $product['key'], (string) $product['name'], (int) $product['quantity'], $this->number($product['revenue']['amount']), 'quantity'];
            }
            foreach ((array) $group['peak_hours'] as $hour) {
                $hours[] = [$currency, (string) $hour['label'], (int) $hour['order_count'], $this->number($hour['net']['amount'])];
            }
        }
        foreach ((array) ($report['warnings'] ?? []) as $warning) {
            $warnings[] = [(string) $warning];
        }

        return [
            'Summary' => $summary,
            'Payments' => $payments,
            'Products' => $products,
            'Peak Hours' => $hours,
            'Warnings' => $warnings,
        ];
    }

    private function number(string $value): array
    {
        return ['value' => $value, 'type' => 'number'];
    }
}
