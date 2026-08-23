<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\ReportOrderGatewayInterface;

final class WooCommerceReportOrderGateway implements ReportOrderGatewayInterface
{
    private const BATCH_SIZE = 100;

    public function orders(array $criteria): iterable
    {
        if (! function_exists('wc_get_orders')) {
            return;
        }

        $page = 1;
        do {
            $result = wc_get_orders([
                'type' => 'shop_order',
                'status' => ['processing', 'completed', 'refunded'],
                'date_created' => (int) $criteria['date_from_ts'] . '...' . (int) $criteria['date_to_ts'],
                'limit' => self::BATCH_SIZE,
                'page' => $page,
                'paginate' => true,
                'return' => 'objects',
                'orderby' => 'date',
                'order' => 'ASC',
            ]);

            $orders = is_object($result) && isset($result->orders) ? (array) $result->orders : [];
            foreach ($orders as $order) {
                if (! is_object($order) || ! $this->isCoffeePosOrder($order)) {
                    continue;
                }
                yield $this->project($order);
            }

            $pages = is_object($result) ? max(0, (int) ($result->max_num_pages ?? 0)) : 0;
            $page++;
        } while ($page <= $pages);
    }

    private function isCoffeePosOrder($order): bool
    {
        $createdVia = method_exists($order, 'get_created_via') ? (string) $order->get_created_via() : '';
        return $createdVia === 'coffeepos' || (string) $order->get_meta('_coffeepos_pos_session_id', true) !== '';
    }

    private function project($order): array
    {
        $date = $order->get_date_created();
        $zone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
        $hour = $date ? (int) $date->setTimezone($zone)->format('G') : 0;
        $items = [];

        foreach ($order->get_items('line_item') as $item) {
            $itemId = (int) $item->get_id();
            $quantityRefunded = method_exists($order, 'get_qty_refunded_for_item')
                ? abs((int) $order->get_qty_refunded_for_item($itemId))
                : 0;
            $totalRefunded = method_exists($order, 'get_total_refunded_for_item')
                ? abs((float) $order->get_total_refunded_for_item($itemId))
                : 0.0;
            $items[] = [
                'product_id' => (int) $item->get_product_id(),
                'variation_id' => (int) $item->get_variation_id(),
                'name' => (string) $item->get_name(),
                'quantity' => max(0, (int) $item->get_quantity()),
                'refunded_quantity' => $quantityRefunded,
                'total' => $this->decimal((float) $item->get_total()),
                'refunded' => $this->decimal($totalRefunded),
            ];
        }

        return [
            'id' => (int) $order->get_id(),
            'currency' => strtoupper((string) $order->get_currency()),
            'decimals' => function_exists('wc_get_price_decimals') ? max(0, (int) wc_get_price_decimals()) : 2,
            'total' => $this->decimal((float) $order->get_total()),
            'refunded' => $this->decimal((float) $order->get_total_refunded()),
            'payment_method' => sanitize_key((string) $order->get_meta('_coffeepos_payment_method', true)),
            'hour' => $hour,
            'items' => $items,
        ];
    }

    private function decimal(float $amount): string
    {
        $decimals = function_exists('wc_get_price_decimals') ? max(0, (int) wc_get_price_decimals()) : 2;
        return number_format($amount, $decimals, '.', '');
    }
}
