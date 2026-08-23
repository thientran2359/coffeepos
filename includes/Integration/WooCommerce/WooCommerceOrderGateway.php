<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\OrderGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Domain\Cart\Cart;
use CoffeePOS\Infrastructure\Settings\Settings;

final class WooCommerceOrderGateway implements OrderGatewayInterface
{
    public function findByOperation(string $operationId): ?array
    {
        if (! function_exists('wc_get_orders')) {
            return null;
        }
        $orders = wc_get_orders(['limit' => 1, 'return' => 'objects', 'meta_key' => '_coffeepos_operation_id', 'meta_value' => $operationId]);
        if ($orders === [] || ! is_object($orders[0])) {
            return null;
        }
        return $this->projectOrder($orders[0]);
    }

    public function create(Cart $cart, array $pricing, array $context): array
    {
        if (! function_exists('wc_create_order') || ! function_exists('wc_get_product')) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_CREATION_FAILED, 'WooCommerce order API is unavailable.');
        }
        try {
            $order = wc_create_order(['customer_id' => $cart->customerContext()->customerId() ?? 0, 'created_via' => 'coffeepos']);
            if ($order instanceof \WP_Error) {
                throw new \RuntimeException($order->get_error_message());
            }
            foreach ($cart->items() as $cartItem) {
                $product = wc_get_product($cartItem->variationId() > 0 ? $cartItem->variationId() : $cartItem->productId());
                if (! $product) {
                    throw new \RuntimeException('Product is unavailable.');
                }
                $itemId = $order->add_product($product, $cartItem->quantity());
                $orderItem = $order->get_item($itemId);
                if ($orderItem) {
                    $snapshot = $cartItem->displaySnapshot();
                    if ($cartItem->customNote() !== '') {
                        $orderItem->add_meta_data('_coffeepos_note', $cartItem->customNote(), true);
                    }
                    $orderItem->add_meta_data('_coffeepos_quick_notes', wp_json_encode($this->quickNoteMetadata(
                        $cartItem->quickNoteSelection()->notes(),
                        (array) ($snapshot['quick_note_labels'] ?? [])
                    )), true);
                    $orderItem->add_meta_data('_coffeepos_modifiers', wp_json_encode($this->modifierMetadata(
                        $cartItem->modifierSelection()->groups(),
                        (array) ($snapshot['modifier_labels'] ?? [])
                    )), true);
                    $orderItem->save();
                }
            }
            if (! empty($pricing['coupon_code'])) {
                $result = $order->apply_coupon((string) $pricing['coupon_code']);
                if ($result instanceof \WP_Error) {
                    throw new \RuntimeException($result->get_error_message());
                }
            }
            $order->set_currency($cart->currency());
            $this->applyCustomer($order, $cart->customerContext()->customerId());
            $order->update_meta_data('_coffeepos_order_type', $cart->orderType()->value());
            if ($cart->orderType()->isDineIn()) {
                $order->update_meta_data('_coffeepos_table_id', $cart->tableContext()->tableId());
                $order->update_meta_data('_coffeepos_table_label', $cart->tableContext()->tableLabel());
            }
            $order->update_meta_data('_coffeepos_cashier_id', (int) ($context['cashier_id'] ?? 0));
            $order->update_meta_data('_coffeepos_shift_id', (int) ($context['shift_id'] ?? 0));
            $order->update_meta_data('_coffeepos_payment_method', (string) $context['payment_method']);
            $order->update_meta_data('_coffeepos_operation_id', (string) $context['operation_id']);
            $order->update_meta_data('_coffeepos_operation_fingerprint', (string) $context['fingerprint']);
            $order->update_meta_data('_coffeepos_pos_session_id', $cart->posSessionId());
            if ($cart->orderNote() !== '') {
                $order->update_meta_data('_coffeepos_order_note', $cart->orderNote());
            }
            $order->set_payment_method((string) $context['payment_method'] === 'cash' ? 'cod' : 'bacs');
            $order->set_payment_method_title((string) $context['payment_method'] === 'cash' ? 'Cash' : 'Bank transfer');
            $order->calculate_totals();
            $this->initializeOperationalMetadata($order);

            if ((string) $context['payment_method'] === 'cash') {
                $order->update_meta_data('_coffeepos_cash_received', (string) $context['received_amount']);
                $order->update_meta_data('_coffeepos_cash_change', (string) $context['change']);
                $order->save();
                $this->completePaymentForPreparation($order, 'coffeepos-cash-' . $order->get_id());
            } else {
                $order->update_meta_data('_coffeepos_payment_reference', (string) ($context['payment_reference'] ?? ''));
                $order->update_meta_data('_coffeepos_bank_confirmed_by', (int) ($context['bank_confirmed_by'] ?? 0));
                $order->update_meta_data('_coffeepos_bank_confirmed_at', gmdate('c'));
                $order->save();
                $this->completePaymentForPreparation($order, 'coffeepos-bank-manual-' . $order->get_id());
            }
            return $this->projectOrder($order);
        } catch (Phase01Exception $exception) {
            throw $exception;
        } catch (\Throwable $throwable) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_CREATION_FAILED, 'WooCommerce order creation failed.', ['reason' => $throwable->getMessage()]);
        }
    }

    public function project(int $orderId): array
    {
        $order = function_exists('wc_get_order') ? wc_get_order($orderId) : false;
        if (! $order) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_NOT_FOUND, 'Order was not found.');
        }
        $this->assertCoffeePosOrder($order);
        return $this->projectOrder($order);
    }

    public function receipt(int $orderId): array
    {
        $order = function_exists('wc_get_order') ? wc_get_order($orderId) : false;
        if (! $order) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_NOT_FOUND, 'Order was not found.');
        }
        $this->assertCoffeePosOrder($order);
        $items = [];
        foreach ($order->get_items() as $item) {
            $quantity = max(1, (int) $item->get_quantity());
            $quickNotes = json_decode((string) $item->get_meta('_coffeepos_quick_notes', true), true);
            $quickLabels = [];
            $configuredQuickLabels = [];
            foreach ((array) Settings::get(Settings::OPTION_QUICK_NOTES) as $definition) {
                if (is_array($definition) && ! empty($definition['id'])) { $configuredQuickLabels[(string) $definition['id']] = (string) ($definition['label'] ?? $definition['id']); }
            }
            foreach (is_array($quickNotes) ? $quickNotes : [] as $quickNote) {
                $id = is_array($quickNote) ? (string) ($quickNote['id'] ?? '') : (string) $quickNote;
                $label = is_array($quickNote) ? (string) ($quickNote['label'] ?? '') : '';
                if ($label === '') { $label = $configuredQuickLabels[$id] ?? $id; }
                if (trim($label) !== '') { $quickLabels[] = trim($label); }
            }
            $modifierData = json_decode((string) $item->get_meta('_coffeepos_modifiers', true), true);
            $modifierLabels = [];
            foreach ((array) ($modifierData['groups'] ?? []) as $group) {
                if (! is_array($group)) { continue; }
                $options = array_values(array_filter(array_map(static function ($option): string {
                    return is_array($option) ? trim((string) ($option['label'] ?? '')) : '';
                }, (array) ($group['options'] ?? []))));
                if ($options !== []) { $modifierLabels[] = trim((string) ($group['label'] ?? '')) . ': ' . implode(', ', $options); }
            }
            $variation = [];
            foreach ($item->get_formatted_meta_data('') as $meta) {
                if (strpos((string) ($meta->key ?? ''), '_coffeepos_') === 0) { continue; }
                $key = wp_strip_all_tags((string) ($meta->display_key ?? ''));
                $value = wp_strip_all_tags((string) ($meta->display_value ?? ''));
                if ($value !== '') { $variation[] = ($key !== '' ? $key . ': ' : '') . $value; }
            }
            $lineTotal = (float) $item->get_total();
            $items[] = [
                'name' => wp_strip_all_tags((string) $item->get_name()), 'quantity' => $quantity,
                'unit_total' => $this->receiptMoney($lineTotal / $quantity, (string) $order->get_currency()),
                'total' => $this->receiptMoney($lineTotal, (string) $order->get_currency()),
                'note' => (string) $item->get_meta('_coffeepos_note', true),
                'quick_notes' => $quickLabels,
                'quick_note_summary' => implode(', ', $quickLabels),
                'modifiers' => $modifierLabels,
                'modifier_summary' => implode(' · ', $modifierLabels),
                'variation_summary' => implode(' · ', $variation),
            ];
        }
        $currency = (string) $order->get_currency();
        $cashierId = (int) $order->get_meta('_coffeepos_cashier_id', true);
        $cashier = $cashierId > 0 ? get_userdata($cashierId) : false;
        $phone = preg_replace('/\D+/', '', (string) $order->get_billing_phone()) ?? '';
        $maskedPhone = strlen($phone) > 6 ? substr($phone, 0, 4) . '***' . substr($phone, -3) : $phone;
        $address = array_filter([
            (string) get_option('woocommerce_store_address', ''),
            (string) get_option('woocommerce_store_address_2', ''),
            (string) get_option('woocommerce_store_city', ''),
        ]);
        $orderNote = Settings::shouldPrintOrderNote() ? (string) $order->get_meta('_coffeepos_order_note', true) : '';
        return [
            'store' => ['name' => get_bloginfo('name'), 'address' => implode(', ', $address)],
            'order' => ['id' => $order->get_id(), 'number' => $order->get_order_number(), 'created_at' => $order->get_date_created() ? $order->get_date_created()->date('c') : ''],
            'cashier' => ['id' => $cashierId, 'display_name' => $cashier ? (string) $cashier->display_name : ''],
            'customer' => ['name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) ?: __('Guest', 'coffeepos'), 'phone_masked' => $maskedPhone],
            'service' => ['order_type' => (string) $order->get_meta('_coffeepos_order_type', true), 'table_label' => (string) $order->get_meta('_coffeepos_table_label', true)],
            'items' => $items,
            'totals' => [
                'subtotal' => $this->receiptMoney((float) $order->get_subtotal(), $currency),
                'discount' => $this->receiptMoney((float) $order->get_discount_total(), $currency),
                'refunded' => $this->receiptMoney((float) $order->get_total_refunded(), $currency),
                'total' => $this->receiptMoney((float) $order->get_total(), $currency),
                'currency' => $currency,
            ],
            'payment' => $this->paymentProjection($order),
            'order_note' => $orderNote,
            'show_order_note' => $orderNote !== '',
        ];
    }

    public function setNextSessionId(int $orderId, string $posSessionId): void
    {
        $order = wc_get_order($orderId);
        if ($order) {
            $order->update_meta_data('_coffeepos_next_pos_session_id', $posSessionId);
            $order->save();
        }
    }

    private function applyCustomer($order, ?int $customerId): void
    {
        if (! $customerId || ! class_exists('WC_Customer')) {
            return;
        }
        $customer = new \WC_Customer($customerId);
        $order->set_address($customer->get_billing(), 'billing');
    }

    private function assertCoffeePosOrder($order): void
    {
        $createdVia = method_exists($order, 'get_created_via') ? (string) $order->get_created_via() : '';
        $sessionId = (string) $order->get_meta('_coffeepos_pos_session_id', true);
        if ($createdVia !== 'coffeepos' && $sessionId === '') {
            throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_NOT_FOUND, 'CoffeePOS order was not found.');
        }
    }

    private function initializeOperationalMetadata($order): void
    {
        if ((string) $order->get_meta(WooCommerceOperationalOrderGateway::META_STATE, true) !== '') {
            return;
        }
        $order->update_meta_data(WooCommerceOperationalOrderGateway::META_STATE, 'new');
        $order->update_meta_data(WooCommerceOperationalOrderGateway::META_REVISION, 0);
        $order->update_meta_data(WooCommerceOperationalOrderGateway::META_RECEIVED_AT, gmdate('c'));
        $order->update_meta_data(WooCommerceOperationalOrderGateway::META_OPERATIONS, '[]');
    }

    private function completePaymentForPreparation($order, string $transactionId): void
    {
        $orderId = (int) $order->get_id();
        $forceProcessing = static function (string $status, int $candidateOrderId) use ($orderId): string {
            return $candidateOrderId === $orderId ? 'processing' : $status;
        };

        // Payment is complete, but KDS preparation is not. Run after gateway
        // filters such as COD, which otherwise force virtual orders directly
        // to completed and make them disappear from the active KDS/Queue.
        add_filter('woocommerce_payment_complete_order_status', $forceProcessing, PHP_INT_MAX, 2);
        try {
            if (! $order->payment_complete($transactionId)) {
                throw new \RuntimeException('WooCommerce could not complete the payment.');
            }
        } finally {
            remove_filter('woocommerce_payment_complete_order_status', $forceProcessing, PHP_INT_MAX);
        }
    }

    private function modifierMetadata(array $groups, array $capturedLabels): array
    {
        $result = [];
        $index = 0;
        foreach ($groups as $groupId => $optionIds) {
            $captured = (string) ($capturedLabels[$index] ?? $groupId);
            $parts = array_map('trim', explode(':', $captured, 2));
            $optionLabels = isset($parts[1]) ? array_map('trim', explode(',', $parts[1])) : [];
            $options = [];
            foreach (array_values((array) $optionIds) as $optionIndex => $optionId) {
                $options[] = ['id' => (string) $optionId, 'label' => (string) ($optionLabels[$optionIndex] ?? $optionId)];
            }
            $result[] = ['id' => (string) $groupId, 'label' => (string) ($parts[0] ?? $groupId), 'options' => $options];
            $index++;
        }
        return ['groups' => $result];
    }

    private function quickNoteMetadata(array $ids, array $labels): array
    {
        $metadata = [];
        foreach (array_values($ids) as $index => $id) {
            $metadata[] = [
                'id' => (string) $id,
                'label' => (string) ($labels[$index] ?? $id),
            ];
        }

        return $metadata;
    }

    private function projectOrder($order): array
    {
        return [
            'id' => $order->get_id(), 'number' => $order->get_order_number(),
            'status' => $order->get_status(),
            'total' => wc_format_decimal((string) $order->get_total(), wc_get_price_decimals()),
            'currency' => $order->get_currency(),
            'operation_id' => (string) $order->get_meta('_coffeepos_operation_id', true),
            'fingerprint' => (string) $order->get_meta('_coffeepos_operation_fingerprint', true),
            'pos_session_id' => (string) $order->get_meta('_coffeepos_pos_session_id', true),
            'next_pos_session_id' => (string) $order->get_meta('_coffeepos_next_pos_session_id', true),
            'payment' => $this->paymentProjection($order),
        ];
    }

    private function paymentProjection($order): array
    {
        $method = (string) $order->get_meta('_coffeepos_payment_method', true);
        $paid = $order->is_paid();
        return [
            'method' => $method, 'state' => $paid ? 'paid' : 'pending',
            'method_label' => (string) $order->get_payment_method_title(),
            'amount' => wc_format_decimal((string) $order->get_total(), wc_get_price_decimals()),
            'received_amount' => (string) $order->get_meta('_coffeepos_cash_received', true),
            'change' => (string) $order->get_meta('_coffeepos_cash_change', true),
            'received_display' => (string) $order->get_meta('_coffeepos_cash_received', true) !== '' ? $this->receiptMoney((float) $order->get_meta('_coffeepos_cash_received', true), (string) $order->get_currency())['display'] : '',
            'change_display' => (string) $order->get_meta('_coffeepos_cash_change', true) !== '' ? $this->receiptMoney((float) $order->get_meta('_coffeepos_cash_change', true), (string) $order->get_currency())['display'] : '',
            'reference' => (string) $order->get_meta('_coffeepos_payment_reference', true),
        ];
    }

    private function receiptMoney(float $amount, string $currency): array
    {
        $raw = wc_format_decimal((string) $amount, wc_get_price_decimals());
        $html = wc_price($amount, ['currency' => $currency]);
        $display = html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return ['amount' => $raw, 'display' => trim(preg_replace('/\s+/u', ' ', $display) ?? $display)];
    }
}
