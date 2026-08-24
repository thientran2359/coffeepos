<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Reports;

use CoffeePOS\Application\Contracts\MoneyFormatterInterface;
use CoffeePOS\Application\Contracts\ReportOrderGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;

final class SalesReportService
{
    private const PRESETS = ['today', 'yesterday', 'last_7_days', 'current_month', 'custom'];

    private ReportOrderGatewayInterface $orders;

    private MoneyFormatterInterface $moneyFormatter;

    /** @var callable|null */
    private $nowProvider;
    private \DateTimeZone $timezone;

    public function __construct(
        ReportOrderGatewayInterface $orders,
        MoneyFormatterInterface $moneyFormatter,
        ?callable $nowProvider = null,
        ?\DateTimeZone $timezone = null
    ) {
        $this->orders = $orders;
        $this->moneyFormatter = $moneyFormatter;
        $this->nowProvider = $nowProvider;
        $this->timezone = $timezone ?? (function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC'));
    }

    public function generate(array $input): array
    {
        $range = $this->range($input);
        $productLimit = max(5, min(100, (int) ($input['product_limit'] ?? 10)));
        $groups = [];
        $warnings = [];

        try {
            $orders = $this->orders->orders([
                'date_from_ts' => $range['date_from_ts'],
                'date_to_ts' => $range['date_to_ts'],
            ]);

            foreach ($orders as $order) {
                if (! is_array($order)) {
                    continue;
                }

                $currency = strtoupper(sanitize_key((string) ($order['currency'] ?? '')));
                $currency = $currency !== '' ? $currency : 'UNKNOWN';
                $decimals = max(0, min(6, (int) ($order['decimals'] ?? 2)));

                if (! isset($groups[$currency])) {
                    $groups[$currency] = $this->emptyGroup($currency, $decimals);
                }

                $group = &$groups[$currency];
                $gross = $this->minor((string) ($order['total'] ?? '0'), $decimals);
                $refund = min($gross, max(0, $this->minor((string) ($order['refunded'] ?? '0'), $decimals)));
                $net = max(0, $gross - $refund);
                $group['gross_minor'] += $gross;
                $group['refund_minor'] += $refund;
                $group['net_minor'] += $net;
                $group['order_count']++;

                $method = sanitize_key((string) ($order['payment_method'] ?? ''));
                if (! in_array($method, ['cash', 'bank_transfer'], true)) {
                    $method = 'unknown';
                }
                $group['payments'][$method]['order_count']++;
                $group['payments'][$method]['gross_minor'] += $gross;
                $group['payments'][$method]['refund_minor'] += $refund;
                $group['payments'][$method]['net_minor'] += $net;

                $hour = max(0, min(23, (int) ($order['hour'] ?? 0)));
                $group['hours'][$hour]['order_count']++;
                $group['hours'][$hour]['net_minor'] += $net;

                $itemRefundTotal = 0;
                foreach ((array) ($order['items'] ?? []) as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $productId = max(0, (int) ($item['product_id'] ?? 0));
                    $variationId = max(0, (int) ($item['variation_id'] ?? 0));
                    $key = ($variationId > 0 ? 'v:' . $variationId : 'p:' . $productId);
                    $name = trim(wp_strip_all_tags((string) ($item['name'] ?? '')));
                    if ($name === '') {
                        $name = sprintf(__('Deleted product #%d', 'coffeepos'), $variationId > 0 ? $variationId : $productId);
                    }

                    $quantity = max(0, (int) ($item['quantity'] ?? 0));
                    $refundedQuantity = min($quantity, max(0, (int) ($item['refunded_quantity'] ?? 0)));
                    $netQuantity = max(0, $quantity - $refundedQuantity);
                    $itemGross = max(0, $this->minor((string) ($item['total'] ?? '0'), $decimals));
                    $itemRefund = min($itemGross, max(0, $this->minor((string) ($item['refunded'] ?? '0'), $decimals)));
                    $itemRefundTotal += $itemRefund;

                    if (! isset($group['products'][$key])) {
                        $group['products'][$key] = [
                            'key' => $key,
                            'product_id' => $productId,
                            'variation_id' => $variationId,
                            'name' => $name,
                            'quantity' => 0,
                            'revenue_minor' => 0,
                        ];
                    }
                    $group['products'][$key]['quantity'] += $netQuantity;
                    $group['products'][$key]['revenue_minor'] += max(0, $itemGross - $itemRefund);
                    $group['products_sold'] += $netQuantity;

                }

                $group['unallocated_refund_minor'] += max(0, $refund - $itemRefundTotal);
                unset($group);
            }
        } catch (Phase01Exception $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw new Phase01Exception(
                Phase01ErrorCodes::REPORT_QUERY_FAILED,
                __('CoffeePOS could not generate the sales report.', 'coffeepos'),
                [],
                $throwable
            );
        }

        ksort($groups);
        $currencyGroups = [];
        $globalOrderCount = 0;
        $globalProductsSold = 0;

        foreach ($groups as $group) {
            $currencyGroups[] = $this->projectGroup($group, $productLimit);
            $globalOrderCount += (int) $group['order_count'];
            $globalProductsSold += (int) $group['products_sold'];
        }

        if (count($currencyGroups) > 1) {
            $warnings[] = __('Orders use multiple currencies. Monetary totals are shown separately and are never converted or combined.', 'coffeepos');
        }

        foreach ($currencyGroups as $group) {
            if ((int) $group['unknown_payment_orders'] > 0) {
                $warnings[] = sprintf(
                    /* translators: 1: count, 2: currency code */
                    __('%1$d %2$s orders have unknown legacy payment metadata.', 'coffeepos'),
                    (int) $group['unknown_payment_orders'],
                    (string) $group['currency']
                );
            }
            if ((int) $group['unallocated_refund']['minor'] > 0) {
                $warnings[] = sprintf(
                    /* translators: 1: amount, 2: currency code */
                    __('%1$s in %2$s refunds cannot be allocated to individual products.', 'coffeepos'),
                    (string) $group['unallocated_refund']['display'],
                    (string) $group['currency']
                );
            }
        }

        return [
            'range' => [
                'preset' => $range['preset'],
                'date_from' => $range['date_from'],
                'date_to' => $range['date_to'],
                'label' => $range['date_from'] . ' — ' . $range['date_to'],
                'timezone' => $range['timezone'],
            ],
            'summary' => [
                'order_count' => $globalOrderCount,
                'products_sold' => $globalProductsSold,
                'currency_count' => count($currencyGroups),
            ],
            'currency_groups' => $currencyGroups,
            'warnings' => array_values(array_unique($warnings)),
            'generated_at' => gmdate('c'),
        ];
    }

    private function range(array $input): array
    {
        $preset = sanitize_key((string) ($input['preset'] ?? 'today'));
        if (! in_array($preset, self::PRESETS, true)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_REPORT_RANGE, __('Unknown report date preset.', 'coffeepos'));
        }

        $zone = $this->timezone;
        $now = $this->nowProvider !== null
            ? ($this->nowProvider)()
            : new \DateTimeImmutable('now', $zone);
        if (! $now instanceof \DateTimeImmutable) {
            $now = new \DateTimeImmutable('now', $zone);
        }
        $today = $now->setTimezone($zone)->setTime(0, 0, 0);

        if ($preset === 'custom') {
            $from = $this->date((string) ($input['date_from'] ?? ''), $zone);
            $to = $this->date((string) ($input['date_to'] ?? ''), $zone);
        } elseif ($preset === 'yesterday') {
            $from = $today->modify('-1 day');
            $to = $from;
        } elseif ($preset === 'last_7_days') {
            $from = $today->modify('-6 days');
            $to = $today;
        } elseif ($preset === 'current_month') {
            $from = $today->modify('first day of this month');
            $to = $today;
        } else {
            $from = $today;
            $to = $today;
        }

        if ($from > $to) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_REPORT_RANGE, __('Report start date must not be after end date.', 'coffeepos'));
        }
        if ((int) $from->diff($to)->format('%a') + 1 > 366) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::REPORT_RANGE_TOO_LARGE, __('Report date range cannot exceed 366 days.', 'coffeepos'));
        }

        return [
            'preset' => $preset,
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
            'date_from_ts' => $from->getTimestamp(),
            'date_to_ts' => $to->setTime(23, 59, 59)->getTimestamp(),
            'timezone' => $zone->getName(),
        ];
    }

    private function date(string $value, \DateTimeZone $zone): \DateTimeImmutable
    {
        $value = trim($value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_REPORT_RANGE, __('Custom report dates must use YYYY-MM-DD.', 'coffeepos'));
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $zone);
        if (! $date || $date->format('Y-m-d') !== $value) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_REPORT_RANGE, __('Custom report date is invalid.', 'coffeepos'));
        }
        return $date;
    }

    private function emptyGroup(string $currency, int $decimals): array
    {
        $payments = [];
        foreach (['cash', 'bank_transfer', 'unknown'] as $method) {
            $payments[$method] = ['method' => $method, 'order_count' => 0, 'gross_minor' => 0, 'refund_minor' => 0, 'net_minor' => 0];
        }
        $hours = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $hours[$hour] = ['hour' => $hour, 'order_count' => 0, 'net_minor' => 0];
        }
        return [
            'currency' => $currency,
            'decimals' => $decimals,
            'order_count' => 0,
            'products_sold' => 0,
            'gross_minor' => 0,
            'refund_minor' => 0,
            'net_minor' => 0,
            'unallocated_refund_minor' => 0,
            'payments' => $payments,
            'products' => [],
            'hours' => $hours,
        ];
    }

    private function projectGroup(array $group, int $limit): array
    {
        $currency = (string) $group['currency'];
        $decimals = (int) $group['decimals'];
        $orderCount = (int) $group['order_count'];
        $aov = $orderCount > 0 ? (int) round((int) $group['net_minor'] / $orderCount) : 0;
        $products = array_values($group['products']);

        $byRevenue = $products;
        usort($byRevenue, static function (array $a, array $b): int {
            return ((int) $b['revenue_minor'] <=> (int) $a['revenue_minor'])
                ?: ((int) $b['quantity'] <=> (int) $a['quantity'])
                ?: strcasecmp((string) $a['name'], (string) $b['name'])
                ?: ((string) $a['key'] <=> (string) $b['key']);
        });
        $byQuantity = $products;
        usort($byQuantity, static function (array $a, array $b): int {
            return ((int) $b['quantity'] <=> (int) $a['quantity'])
                ?: ((int) $b['revenue_minor'] <=> (int) $a['revenue_minor'])
                ?: strcasecmp((string) $a['name'], (string) $b['name'])
                ?: ((string) $a['key'] <=> (string) $b['key']);
        });

        $projectProduct = function (array $product) use ($currency, $decimals): array {
            return [
                'key' => (string) $product['key'],
                'product_id' => (int) $product['product_id'],
                'variation_id' => (int) $product['variation_id'],
                'name' => (string) $product['name'],
                'quantity' => (int) $product['quantity'],
                'revenue' => $this->money((int) $product['revenue_minor'], $currency, $decimals),
            ];
        };

        $paymentLabels = [
            'cash' => __('Cash', 'coffeepos'),
            'bank_transfer' => __('Bank transfer', 'coffeepos'),
            'unknown' => __('Unknown', 'coffeepos'),
        ];
        $payments = [];
        foreach ($group['payments'] as $payment) {
            $payments[] = [
                'method' => (string) $payment['method'],
                'label' => $paymentLabels[$payment['method']] ?? ucfirst((string) $payment['method']),
                'order_count' => (int) $payment['order_count'],
                'gross' => $this->money((int) $payment['gross_minor'], $currency, $decimals),
                'refund' => $this->money((int) $payment['refund_minor'], $currency, $decimals),
                'net' => $this->money((int) $payment['net_minor'], $currency, $decimals),
            ];
        }

        $hours = [];
        foreach ($group['hours'] as $hour) {
            $hours[] = [
                'key' => sprintf('%02d', (int) $hour['hour']),
                'label' => sprintf('%02d:00', (int) $hour['hour']),
                'order_count' => (int) $hour['order_count'],
                'net' => $this->money((int) $hour['net_minor'], $currency, $decimals),
            ];
        }

        return [
            'currency' => $currency,
            'decimals' => $decimals,
            'order_count' => $orderCount,
            'products_sold' => (int) $group['products_sold'],
            'gross_revenue' => $this->money((int) $group['gross_minor'], $currency, $decimals),
            'refund_total' => $this->money((int) $group['refund_minor'], $currency, $decimals),
            'net_revenue' => $this->money((int) $group['net_minor'], $currency, $decimals),
            'aov' => $this->money($aov, $currency, $decimals),
            'unallocated_refund' => $this->money((int) $group['unallocated_refund_minor'], $currency, $decimals),
            'unknown_payment_orders' => (int) $group['payments']['unknown']['order_count'],
            'payments' => $payments,
            'top_by_revenue' => array_map($projectProduct, array_slice($byRevenue, 0, $limit)),
            'top_by_quantity' => array_map($projectProduct, array_slice($byQuantity, 0, $limit)),
            'peak_hours' => $hours,
        ];
    }

    private function money(int $minor, string $currency, int $decimals): array
    {
        return [
            'minor' => $minor,
            'amount' => $this->decimal($minor, $decimals),
            'display' => $this->moneyFormatter->format($minor, $currency),
            'currency' => $currency,
        ];
    }

    private function minor(string $amount, int $decimals): int
    {
        $amount = trim($amount);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $amount)) {
            return 0;
        }
        $negative = strpos($amount, '-') === 0;
        $parts = explode('.', ltrim($amount, '-'), 2);
        $whole = ltrim($parts[0], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = isset($parts[1]) ? preg_replace('/\D/', '', $parts[1]) : '';
        $fraction = substr(str_pad((string) $fraction, $decimals, '0'), 0, $decimals);
        $minor = (int) ($whole . $fraction);
        return $negative ? -$minor : $minor;
    }

    private function decimal(int $minor, int $decimals): string
    {
        $negative = $minor < 0;
        $digits = str_pad((string) abs($minor), $decimals + 1, '0', STR_PAD_LEFT);
        if ($decimals === 0) {
            return ($negative ? '-' : '') . $digits;
        }
        return ($negative ? '-' : '') . substr($digits, 0, -$decimals) . '.' . substr($digits, -$decimals);
    }
}
