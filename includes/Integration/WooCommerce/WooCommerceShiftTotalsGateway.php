<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\ShiftTotalsGatewayInterface;

final class WooCommerceShiftTotalsGateway implements ShiftTotalsGatewayInterface
{
    public function totals(int $shiftId): array
    {
        $cash = 0.0;
        $bank = 0.0;
        $orders = function_exists('wc_get_orders') ? wc_get_orders([
            'limit' => -1, 'return' => 'objects', 'status' => ['processing', 'completed'],
            'meta_key' => '_coffeepos_shift_id', 'meta_value' => (string) $shiftId,
        ]) : [];
        foreach ($orders as $order) {
            $net = max(0.0, (float) $order->get_total() - (float) $order->get_total_refunded());
            $method = (string) $order->get_meta('_coffeepos_payment_method', true);
            if ($method === 'cash') {
                $cash += $net;
            } elseif ($method === 'bank_transfer') {
                $bank += $net;
            }
        }
        return ['cash_sales' => $cash, 'bank_sales' => $bank, 'total_sales' => $cash + $bank, 'order_count' => count($orders)];
    }
}
