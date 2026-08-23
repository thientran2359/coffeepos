<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\OrderGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Domain\Cart\Cart;

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
                    $orderItem->add_meta_data('_coffeepos_quick_notes', wp_json_encode($cartItem->quickNoteSelection()->notes()), true);
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
            $order->update_meta_data('_coffeepos_payment_method', (string) $context['payment_method']);
            $order->update_meta_data('_coffeepos_operation_id', (string) $context['operation_id']);
            $order->update_meta_data('_coffeepos_operation_fingerprint', (string) $context['fingerprint']);
            $order->update_meta_data('_coffeepos_pos_session_id', $cart->posSessionId());
            $order->set_payment_method((string) $context['payment_method'] === 'cash' ? 'cod' : 'bacs');
            $order->set_payment_method_title((string) $context['payment_method'] === 'cash' ? 'Cash' : 'Bank transfer');
            $order->calculate_totals();

            if ((string) $context['payment_method'] === 'cash') {
                $order->update_meta_data('_coffeepos_cash_received', (string) $context['received_amount']);
                $order->update_meta_data('_coffeepos_cash_change', (string) $context['change']);
                $order->save();
                $order->payment_complete('coffeepos-cash-' . $order->get_id());
            } else {
                $order->update_meta_data('_coffeepos_payment_reference', (string) ($context['payment_reference'] ?? ''));
                $order->update_meta_data('_coffeepos_bank_confirmed_by', (int) ($context['bank_confirmed_by'] ?? 0));
                $order->update_meta_data('_coffeepos_bank_confirmed_at', gmdate('c'));
                $order->save();
                $order->payment_complete('coffeepos-bank-manual-' . $order->get_id());
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
        return $this->projectOrder($order);
    }

    public function receipt(int $orderId): array
    {
        $order = function_exists('wc_get_order') ? wc_get_order($orderId) : false;
        if (! $order) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_NOT_FOUND, 'Order was not found.');
        }
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = [
                'name' => $item->get_name(), 'quantity' => $item->get_quantity(),
                'total' => wc_format_decimal((string) $item->get_total(), wc_get_price_decimals()),
                'note' => (string) $item->get_meta('_coffeepos_note', true),
                'quick_notes' => json_decode((string) $item->get_meta('_coffeepos_quick_notes', true), true) ?: [],
                'modifiers' => json_decode((string) $item->get_meta('_coffeepos_modifiers', true), true) ?: [],
            ];
        }
        return [
            'store' => ['name' => get_bloginfo('name'), 'address' => trim((string) get_option('woocommerce_store_address', ''))],
            'order' => ['id' => $order->get_id(), 'number' => $order->get_order_number(), 'created_at' => $order->get_date_created() ? $order->get_date_created()->date('c') : ''],
            'customer' => ['name' => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()), 'phone' => $order->get_billing_phone()],
            'service' => ['order_type' => (string) $order->get_meta('_coffeepos_order_type', true), 'table_label' => (string) $order->get_meta('_coffeepos_table_label', true)],
            'items' => $items,
            'totals' => ['subtotal' => wc_format_decimal((string) $order->get_subtotal(), wc_get_price_decimals()), 'discount' => wc_format_decimal((string) $order->get_discount_total(), wc_get_price_decimals()), 'total' => wc_format_decimal((string) $order->get_total(), wc_get_price_decimals()), 'currency' => $order->get_currency()],
            'payment' => $this->paymentProjection($order),
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
            'amount' => wc_format_decimal((string) $order->get_total(), wc_get_price_decimals()),
            'received_amount' => (string) $order->get_meta('_coffeepos_cash_received', true),
            'change' => (string) $order->get_meta('_coffeepos_cash_change', true),
            'reference' => (string) $order->get_meta('_coffeepos_payment_reference', true),
        ];
    }
}
