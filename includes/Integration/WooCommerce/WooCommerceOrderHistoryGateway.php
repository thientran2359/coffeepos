<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\OrderHistoryGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Support\Capabilities;

final class WooCommerceOrderHistoryGateway implements OrderHistoryGatewayInterface
{
    private const META_REFUND_OPERATIONS = '_coffeepos_refund_operations';
    private const META_REORDER_OPERATIONS = '_coffeepos_reorder_operations';
    private const META_REFUND_OPERATION_ID = '_coffeepos_refund_operation_id';
    private const META_REFUND_FINGERPRINT = '_coffeepos_refund_fingerprint';

    public function list(array $criteria): array
    {
        if (! function_exists('wc_get_orders')) {
            return ['items' => [], 'page' => 1, 'pages' => 0, 'total' => 0];
        }
        $args = [
            'type' => 'shop_order', 'created_via' => 'coffeepos', 'return' => 'objects', 'paginate' => true,
            'limit' => (int) $criteria['per_page'], 'page' => (int) $criteria['page'], 'orderby' => 'date', 'order' => 'DESC',
        ];
        if ((string) $criteria['status'] !== 'all') {
            $args['status'] = [(string) $criteria['status']];
        }
        if ((string) $criteria['search'] !== '') {
            $args['s'] = (string) $criteria['search'];
        }
        if ((int) $criteria['date_from_ts'] > 0 || (int) $criteria['date_to_ts'] > 0) {
            $start = (int) $criteria['date_from_ts'] > 0 ? (int) $criteria['date_from_ts'] : 0;
            $end = (int) $criteria['date_to_ts'] > 0 ? (int) $criteria['date_to_ts'] : time();
            $args['date_created'] = $start . '...' . $end;
        }
        if ((string) $criteria['order_type'] !== 'all') {
            $args['meta_query'] = [['key' => '_coffeepos_order_type', 'value' => (string) $criteria['order_type']]];
        }
        $result = wc_get_orders($args);
        $orders = is_object($result) && isset($result->orders) ? $result->orders : [];
        return [
            'items' => array_map(function ($order): array { return $this->summary($order); }, (array) $orders),
            'page' => (int) $criteria['page'],
            'pages' => is_object($result) ? (int) ($result->max_num_pages ?? 0) : 0,
            'total' => is_object($result) ? (int) ($result->total ?? 0) : count((array) $orders),
        ];
    }

    public function find(int $orderId): ?array
    {
        $order = $this->loadPosOrder($orderId, false);
        return $order ? $this->detail($order) : null;
    }

    public function refund(int $orderId, string $amount, string $reason, string $operationId, string $fingerprint, int $userId): array
    {
        $order = $this->loadPosOrder($orderId);
        $operations = $this->operations($order, self::META_REFUND_OPERATIONS);
        foreach ($operations as $operation) {
            if ((string) ($operation['id'] ?? '') !== $operationId) {
                continue;
            }
            if (! hash_equals((string) ($operation['fingerprint'] ?? ''), $fingerprint)) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::DUPLICATE_OPERATION_CONFLICT, 'Refund operation ID was reused with different input.');
            }
            return $this->detail($order);
        }
        foreach ((array) $order->get_refunds() as $existingRefund) {
            if (! is_object($existingRefund) || (string) $existingRefund->get_meta(self::META_REFUND_OPERATION_ID, true) !== $operationId) {
                continue;
            }
            if (! hash_equals((string) $existingRefund->get_meta(self::META_REFUND_FINGERPRINT, true), $fingerprint)) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::DUPLICATE_OPERATION_CONFLICT, 'Refund operation ID was reused with different input.');
            }
            return $this->detail($order);
        }
        $refundable = max(0.0, (float) $order->get_total() - (float) $order->get_total_refunded());
        $requested = (float) $amount;
        if (! $order->is_paid() || $requested <= 0 || $requested > $refundable + 0.000001 || in_array($order->get_status(), ['cancelled', 'failed', 'refunded'], true)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_REFUND, 'The requested refund is not allowed for this order.', ['refundable' => $this->decimal($refundable)]);
        }
        if (! function_exists('wc_create_refund')) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::REFUND_FAILED, 'WooCommerce refund API is unavailable.');
        }
        $refund = wc_create_refund([
            'order_id' => $orderId, 'amount' => $amount, 'reason' => $reason,
            'refund_payment' => false, 'restock_items' => false,
        ]);
        if ($refund instanceof \WP_Error) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::REFUND_FAILED, 'WooCommerce could not create the refund.', ['reason' => $refund->get_error_message()]);
        }
        if (! is_object($refund) || ! method_exists($refund, 'get_id')) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::REFUND_FAILED, 'WooCommerce returned an invalid refund.');
        }
        $refund->update_meta_data(self::META_REFUND_OPERATION_ID, $operationId);
        $refund->update_meta_data(self::META_REFUND_FINGERPRINT, $fingerprint);
        $refund->save();
        $operations[] = ['id' => $operationId, 'fingerprint' => $fingerprint, 'refund_id' => (int) $refund->get_id(), 'user_id' => $userId, 'created_at' => gmdate('c')];
        $order->update_meta_data(self::META_REFUND_OPERATIONS, wp_json_encode(array_slice($operations, -12)));
        $order->save();
        $order->add_order_note(sprintf('CoffeePOS refund %s created by user #%d. %s', $amount, $userId, $reason !== '' ? 'Reason: ' . $reason : ''), false, true);
        return $this->detail(wc_get_order($orderId));
    }

    public function reorderItems(int $orderId): array
    {
        $order = $this->loadPosOrder($orderId);
        $items = [];
        foreach ($order->get_items('line_item') as $item) {
            $modifierData = json_decode((string) $item->get_meta('_coffeepos_modifiers', true), true);
            $modifiers = [];
            foreach ((array) ($modifierData['groups'] ?? []) as $group) {
                if (! is_array($group) || (string) ($group['id'] ?? '') === '') { continue; }
                $modifiers[(string) $group['id']] = array_values(array_filter(array_map(static function ($option): string {
                    return is_array($option) ? (string) ($option['id'] ?? '') : '';
                }, (array) ($group['options'] ?? []))));
            }
            $quickNotes = $this->quickNoteIds(json_decode((string) $item->get_meta('_coffeepos_quick_notes', true), true));
            $items[] = [
                'product_id' => (int) $item->get_product_id(), 'variation_id' => (int) $item->get_variation_id(),
                'quantity' => max(1, (int) $item->get_quantity()), 'modifiers' => $modifiers,
                'quick_notes' => $quickNotes,
                'custom_note' => (string) $item->get_meta('_coffeepos_note', true),
            ];
        }
        return $items;
    }

    public function findReorderOperation(int $orderId, string $operationId): ?array
    {
        $order = $this->loadPosOrder($orderId);
        foreach ($this->operations($order, self::META_REORDER_OPERATIONS) as $operation) {
            if ((string) ($operation['id'] ?? '') === $operationId) { return $operation; }
        }
        return null;
    }

    public function recordReorderOperation(int $orderId, string $operationId, string $fingerprint, string $posSessionId): void
    {
        $order = $this->loadPosOrder($orderId);
        $operations = $this->operations($order, self::META_REORDER_OPERATIONS);
        $operations[] = ['id' => $operationId, 'fingerprint' => $fingerprint, 'pos_session_id' => $posSessionId, 'created_at' => gmdate('c')];
        $order->update_meta_data(self::META_REORDER_OPERATIONS, wp_json_encode(array_slice($operations, -8)));
        $order->save();
    }

    private function loadPosOrder(int $orderId, bool $throw = true)
    {
        $order = function_exists('wc_get_order') ? wc_get_order($orderId) : false;
        $eligible = $order && ((string) $order->get_created_via() === 'coffeepos' || (string) $order->get_meta('_coffeepos_pos_session_id', true) !== '');
        if (! $eligible && $throw) { throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_NOT_FOUND, 'CoffeePOS order was not found.'); }
        return $eligible ? $order : null;
    }

    private function summary($order): array
    {
        $detail = $this->detail($order);
        unset($detail['items'], $detail['notes']);
        return $detail;
    }

    private function detail($order): array
    {
        $currency = (string) $order->get_currency();
        $created = $order->get_date_created();
        $status = (string) $order->get_status();
        $kdsState = sanitize_key((string) $order->get_meta(WooCommerceOperationalOrderGateway::META_STATE, true));
        if (! in_array($kdsState, ['new', 'preparing', 'ready', 'completed', 'cancelled'], true)) { $kdsState = $status === 'completed' ? 'completed' : 'new'; }
        if (in_array($status, ['cancelled', 'failed', 'refunded'], true)) { $kdsState = 'cancelled'; }
        $refunded = (float) $order->get_total_refunded();
        $refundable = max(0.0, (float) $order->get_total() - $refunded);
        $items = [];
        foreach ($order->get_items('line_item') as $item) {
            $quickMetadata = json_decode((string) $item->get_meta('_coffeepos_quick_notes', true), true);
            $quickLabels = [];
            $configuredQuickLabels = [];
            foreach ((array) Settings::get(Settings::OPTION_QUICK_NOTES) as $definition) {
                if (is_array($definition) && ! empty($definition['id'])) { $configuredQuickLabels[(string) $definition['id']] = (string) ($definition['label'] ?? $definition['id']); }
            }
            foreach (is_array($quickMetadata) ? $quickMetadata : [] as $value) {
                if (is_array($value)) {
                    $label = trim((string) ($value['label'] ?? $value['id'] ?? ''));
                } else {
                    $id = trim((string) $value);
                    $label = trim((string) ($configuredQuickLabels[$id] ?? $id));
                }
                if ($label !== '') { $quickLabels[] = $label; }
            }
            $items[] = [
                'id' => (int) $item->get_id(), 'product_id' => (int) $item->get_product_id(), 'variation_id' => (int) $item->get_variation_id(),
                'name' => wp_strip_all_tags((string) $item->get_name()), 'quantity' => (int) $item->get_quantity(),
                'subtotal' => $this->money((float) $item->get_subtotal(), $currency), 'total' => $this->money((float) $item->get_total(), $currency),
                'note' => (string) $item->get_meta('_coffeepos_note', true),
                'quick_note_summary' => implode(', ', $quickLabels),
            ];
        }
        $customerName = trim((string) $order->get_formatted_billing_full_name());
        $orderType = (string) $order->get_meta('_coffeepos_order_type', true);
        $canCancel = in_array($status, ['processing', 'on-hold'], true) && in_array($kdsState, ['new', 'preparing'], true);
        $canRefund = $order->is_paid() && $refundable > 0 && ! in_array($status, ['cancelled', 'failed', 'refunded'], true);
        return [
            'id' => (int) $order->get_id(), 'number' => (string) $order->get_order_number(),
            'created_at' => $created ? $created->date('c') : '',
            'created_at_display' => $created ? Settings::formatTimestamp($created->getTimestamp()) : '',
            'status' => $status,
            'status_label' => function_exists('wc_get_order_status_name') ? wc_get_order_status_name($status) : ucfirst($status),
            'customer' => ['display_name' => $customerName !== '' ? $customerName : 'Guest', 'phone' => (string) $order->get_billing_phone()],
            'service' => ['order_type' => in_array($orderType, ['dine_in', 'takeaway'], true) ? $orderType : 'takeaway', 'table_label' => (string) $order->get_meta('_coffeepos_table_label', true)],
            'payment' => ['method' => (string) $order->get_meta('_coffeepos_payment_method', true), 'method_label' => (string) $order->get_payment_method_title()],
            'shift_id' => (int) $order->get_meta('_coffeepos_shift_id', true), 'items' => $items,
            'order_note' => (string) $order->get_meta('_coffeepos_order_note', true),
            'totals' => [
                'subtotal' => $this->money((float) $order->get_subtotal(), $currency), 'discount' => $this->money((float) $order->get_discount_total(), $currency),
                'total' => $this->money((float) $order->get_total(), $currency), 'refunded' => $this->money($refunded, $currency),
                'refundable' => $this->money($refundable, $currency), 'refundable_amount' => $this->decimal($refundable), 'currency' => $currency,
            ],
            'kds' => ['state' => $kdsState, 'revision' => max(0, (int) $order->get_meta(WooCommerceOperationalOrderGateway::META_REVISION, true))],
            'actions' => [
                'can_cancel' => $canCancel && current_user_can(Capabilities::CANCEL_ORDERS),
                'can_refund' => $canRefund && current_user_can(Capabilities::REFUND_ORDERS),
                'can_reorder' => $items !== [] && current_user_can(Capabilities::REORDER_ORDERS),
                'can_reprint' => current_user_can(Capabilities::REPRINT_RECEIPTS),
            ],
        ];
    }

    private function quickNoteIds($metadata): array
    {
        $ids = [];
        foreach (is_array($metadata) ? $metadata : [] as $value) {
            $id = is_array($value) ? (string) ($value['id'] ?? '') : (string) $value;
            if ($id !== '') { $ids[] = sanitize_key($id); }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function operations($order, string $key): array
    {
        $decoded = json_decode((string) $order->get_meta($key, true), true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function money(float $amount, string $currency): array
    {
        $html = wc_price($amount, ['currency' => $currency]);
        $display = html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return ['amount' => $this->decimal($amount), 'display' => trim(preg_replace('/\s+/u', ' ', $display) ?? $display)];
    }

    private function decimal(float $amount): string
    {
        return number_format($amount, wc_get_price_decimals(), '.', '');
    }
}
