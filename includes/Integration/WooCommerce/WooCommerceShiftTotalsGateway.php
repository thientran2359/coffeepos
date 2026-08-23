<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\ShiftTotalsGatewayInterface;

final class WooCommerceShiftTotalsGateway implements ShiftTotalsGatewayInterface
{
    private const BATCH_SIZE = 100;

    public function totals(int $shiftId): array
    {
        $cash = 0.0;
        $bank = 0.0;
        $orderCount = 0;
        $page = 1;
        $pages = 0;

        if (function_exists('wc_get_orders')) {
            do {
                $result = wc_get_orders([
                    'limit' => self::BATCH_SIZE,
                    'page' => $page,
                    'paginate' => true,
                    'return' => 'objects',
                    'status' => ['processing', 'completed'],
                    'meta_key' => '_coffeepos_shift_id',
                    'meta_value' => (string) $shiftId,
                ]);
                $orders = is_object($result) && isset($result->orders) ? (array) $result->orders : [];
                $pages = is_object($result) ? max(0, (int) ($result->max_num_pages ?? 0)) : 0;

                foreach ($orders as $order) {
                    if (! is_object($order)) {
                        continue;
                    }
                    $net = max(0.0, (float) $order->get_total() - (float) $order->get_total_refunded());
                    $method = (string) $order->get_meta('_coffeepos_payment_method', true);
                    if ($method === 'cash') {
                        $cash += $net;
                    } elseif ($method === 'bank_transfer') {
                        $bank += $net;
                    }
                    $orderCount++;
                }
                $page++;
            } while ($page <= $pages);
        }

        return [
            'cash_sales' => $cash,
            'bank_sales' => $bank,
            'total_sales' => $cash + $bank,
            'order_count' => $orderCount,
        ];
    }
}
