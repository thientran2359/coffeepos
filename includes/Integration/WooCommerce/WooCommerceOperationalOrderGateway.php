<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\OperationalOrderGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Support\Capabilities;

final class WooCommerceOperationalOrderGateway implements OperationalOrderGatewayInterface
{
    public const META_STATE = '_coffeepos_kds_state';
    public const META_REVISION = '_coffeepos_kds_revision';
    public const META_RECEIVED_AT = '_coffeepos_kds_received_at';
    public const META_STARTED_AT = '_coffeepos_kds_started_at';
    public const META_READY_AT = '_coffeepos_kds_ready_at';
    public const META_COMPLETED_AT = '_coffeepos_kds_completed_at';
    public const META_CANCELLED_AT = '_coffeepos_kds_cancelled_at';
    public const META_OPERATIONS = '_coffeepos_kds_operations';

    public function listActive(int $limit): array
    {
        if (! function_exists('wc_get_orders')) {
            return [];
        }
        $orders = wc_get_orders([
            'limit' => max(1, min(200, $limit)),
            'return' => 'objects',
            'status' => ['processing', 'on-hold'],
            'orderby' => 'date',
            'order' => 'ASC',
            'meta_key' => '_coffeepos_pos_session_id',
            'meta_compare' => 'EXISTS',
        ]);
        $items = [];
        foreach ($orders as $order) {
            if (! is_object($order)) {
                continue;
            }
            $projection = $this->project($order);
            if (! empty($projection['eligible']) && in_array((string) $projection['kds']['state'], ['new', 'preparing', 'ready'], true)) {
                $items[] = $projection;
            }
        }
        usort($items, static function (array $left, array $right): int {
            $time = strcmp((string) $left['received_at'], (string) $right['received_at']);
            return $time !== 0 ? $time : ((int) $left['id'] <=> (int) $right['id']);
        });
        return array_slice($items, 0, $limit);
    }

    public function find(int $orderId): ?array
    {
        $order = function_exists('wc_get_order') ? wc_get_order($orderId) : false;
        return $order ? $this->project($order) : null;
    }

    public function saveTransition(int $orderId, int $expectedRevision, string $expectedState, array $changes): array
    {
        $order = function_exists('wc_get_order') ? wc_get_order($orderId) : false;
        if (! $order) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_NOT_FOUND, 'Order was not found.');
        }
        $current = $this->project($order);
        if ((int) $current['kds']['revision'] !== $expectedRevision || (string) $current['kds']['state'] !== $expectedState) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_STATE_CONFLICT, 'Order state changed on another screen.');
        }

        $state = (string) ($changes['state'] ?? '');
        $timestamp = (string) ($changes['timestamp'] ?? gmdate('c'));
        $order->update_meta_data(self::META_STATE, $state);
        $order->update_meta_data(self::META_REVISION, max(0, (int) ($changes['revision'] ?? $expectedRevision + 1)));
        $order->update_meta_data(self::META_OPERATIONS, wp_json_encode(array_slice(array_values((array) ($changes['operations'] ?? [])), -8)));
        if ($state === 'preparing' && (string) $order->get_meta(self::META_STARTED_AT, true) === '') {
            $order->update_meta_data(self::META_STARTED_AT, $timestamp);
        }
        if ($state === 'ready' && (string) $order->get_meta(self::META_READY_AT, true) === '') {
            $order->update_meta_data(self::META_READY_AT, $timestamp);
        }
        if ($state === 'completed') {
            if ((string) $order->get_meta(self::META_COMPLETED_AT, true) === '') {
                $order->update_meta_data(self::META_COMPLETED_AT, $timestamp);
            }
            $order->set_status('completed');
        }
        if ($state === 'cancelled') {
            if ((string) $order->get_meta(self::META_CANCELLED_AT, true) === '') {
                $order->update_meta_data(self::META_CANCELLED_AT, $timestamp);
            }
            $order->set_status('cancelled');
        }
        $order->save();

        $userId = max(0, (int) ($changes['user_id'] ?? 0));
        $surface = (string) ($changes['surface'] ?? 'kds');
        $reason = trim((string) ($changes['reason'] ?? ''));
        if (method_exists($order, 'add_order_note')) {
            $message = sprintf('CoffeePOS: %s via %s by user #%d.', $state, $surface, $userId);
            if ($reason !== '') {
                $message .= ' Reason: ' . $reason;
            }
            $order->add_order_note($message, false, true);
        }
        return $this->project($order);
    }

    private function project($order): array
    {
        $status = (string) $order->get_status();
        $sessionId = (string) $order->get_meta('_coffeepos_pos_session_id', true);
        $createdVia = method_exists($order, 'get_created_via') ? (string) $order->get_created_via() : '';
        $eligible = ($sessionId !== '' || $createdVia === 'coffeepos') && ($order->is_paid() || $order->get_date_paid());
        $storedState = sanitize_key((string) $order->get_meta(self::META_STATE, true));
        $state = in_array($storedState, ['new', 'preparing', 'ready', 'completed', 'cancelled'], true) ? $storedState : 'new';
        if ($status === 'completed') {
            $state = 'completed';
        } elseif (in_array($status, ['cancelled', 'refunded', 'failed', 'trash'], true)) {
            $state = 'cancelled';
        }

        $created = $order->get_date_created();
        $paid = $order->get_date_paid();
        $receivedAt = (string) $order->get_meta(self::META_RECEIVED_AT, true);
        if ($receivedAt === '') {
            $anchor = $paid ?: $created;
            $receivedAt = $anchor ? $anchor->date('c') : gmdate('c');
        }
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = $this->itemProjection($item);
        }
        $orderType = sanitize_key((string) $order->get_meta('_coffeepos_order_type', true));
        if (! in_array($orderType, ['dine_in', 'takeaway'], true)) {
            $orderType = 'takeaway';
        }
        $tableLabel = (string) $order->get_meta('_coffeepos_table_label', true);
        $serviceLabel = $orderType === 'dine_in' ? 'Dine-in' . ($tableLabel !== '' ? ' — ' . $tableLabel : '') : 'Takeaway';
        $customerName = trim((string) $order->get_formatted_billing_full_name());
        $currency = (string) $order->get_currency();
        $amount = wc_format_decimal((string) $order->get_total(), wc_get_price_decimals());
        $operations = json_decode((string) $order->get_meta(self::META_OPERATIONS, true), true);

        return [
            'id' => (int) $order->get_id(),
            'number' => (string) $order->get_order_number(),
            'created_at' => $created ? $created->date('c') : '',
            'received_at' => $receivedAt,
            'received_time' => $this->displayTime($receivedAt),
            'eligible' => $eligible,
            'woocommerce_status' => $status,
            'customer' => ['display_name' => $customerName !== '' ? $customerName : 'Guest', 'is_guest' => $customerName === ''],
            'service' => ['order_type' => $orderType, 'table_label' => $tableLabel],
            'service_label' => $serviceLabel,
            'total' => ['amount' => $amount, 'currency' => $currency, 'display' => $this->plainText(wc_price((float) $order->get_total(), ['currency' => $currency]))],
            'receipt_available' => $eligible && current_user_can(Capabilities::REPRINT_RECEIPTS),
            'cancel_allowed' => current_user_can(Capabilities::CANCEL_ORDERS),
            'order_note' => (string) $order->get_meta('_coffeepos_order_note', true),
            'items' => $items,
            'kds' => [
                'state' => $state,
                'revision' => max(0, (int) $order->get_meta(self::META_REVISION, true)),
                'started_at' => $this->nullableMeta($order, self::META_STARTED_AT),
                'ready_at' => $this->nullableMeta($order, self::META_READY_AT),
                'completed_at' => $this->nullableMeta($order, self::META_COMPLETED_AT),
                'cancelled_at' => $this->nullableMeta($order, self::META_CANCELLED_AT),
            ],
            '_operations' => is_array($operations) ? array_slice(array_values($operations), -8) : [],
        ];
    }

    private function itemProjection($item): array
    {
        $quickIds = json_decode((string) $item->get_meta('_coffeepos_quick_notes', true), true);
        $quickLabels = [];
        foreach ((array) Settings::get(Settings::OPTION_QUICK_NOTES) as $definition) {
            if (is_array($definition) && isset($definition['id'], $definition['label'])) {
                $quickLabels[(string) $definition['id']] = (string) $definition['label'];
            }
        }
        $quick = [];
        foreach (is_array($quickIds) ? $quickIds : [] as $value) {
            $id = is_array($value) ? (string) ($value['id'] ?? '') : (string) $value;
            $label = is_array($value) ? (string) ($value['label'] ?? '') : '';
            if ($id !== '') {
                $quick[] = $label !== '' ? $label : ($quickLabels[$id] ?? $id);
            }
        }
        $modifierData = json_decode((string) $item->get_meta('_coffeepos_modifiers', true), true);
        $modifiers = [];
        foreach ((array) ($modifierData['groups'] ?? []) as $group) {
            if (! is_array($group)) {
                continue;
            }
            $options = array_map(static function ($option): string { return is_array($option) ? (string) ($option['label'] ?? '') : ''; }, (array) ($group['options'] ?? []));
            $options = array_values(array_filter($options, static function (string $value): bool { return $value !== ''; }));
            if ($options !== []) {
                $modifiers[] = (string) ($group['label'] ?? '') . ': ' . implode(', ', $options);
            }
        }
        $variation = [];
        foreach ($item->get_formatted_meta_data('') as $meta) {
            if (isset($meta->key) && strpos((string) $meta->key, '_coffeepos_') === 0) {
                continue;
            }
            $label = $this->plainText((string) ($meta->display_key ?? ''));
            $value = $this->plainText((string) ($meta->display_value ?? ''));
            if ($value !== '') {
                $variation[] = ($label !== '' ? $label . ': ' : '') . $value;
            }
        }
        return [
            'order_item_id' => (int) $item->get_id(),
            'product_name' => $this->plainText((string) $item->get_name()),
            'quantity' => max(1, (int) $item->get_quantity()),
            'variation_summary' => implode(' · ', $variation),
            'modifier_summary' => implode(' · ', $modifiers),
            'quick_note_summary' => implode(', ', $quick),
            'custom_note' => (string) $item->get_meta('_coffeepos_note', true),
        ];
    }

    private function nullableMeta($order, string $key): ?string
    {
        $value = trim((string) $order->get_meta($key, true));
        return $value === '' ? null : $value;
    }

    private function displayTime(string $value): string
    {
        $timestamp = strtotime($value);
        return $timestamp === false ? '' : (function_exists('wp_date') ? wp_date(get_option('time_format', 'H:i'), $timestamp) : gmdate('H:i', $timestamp));
    }

    private function plainText(string $value): string
    {
        return trim(html_entity_decode(wp_strip_all_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
