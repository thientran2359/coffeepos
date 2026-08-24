<?php

declare(strict_types=1);

namespace CoffeePOS\Application\OrderHistory;

use CoffeePOS\Application\Contracts\CartReconstructorInterface;
use CoffeePOS\Application\Contracts\LockProviderInterface;
use CoffeePOS\Application\Contracts\OrderHistoryGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Operations\OperationalOrderService;

final class OrderHistoryService
{
    private OrderHistoryGatewayInterface $orders;
    private CartReconstructorInterface $carts;
    private OperationalOrderService $operations;
    private LockProviderInterface $locks;
    private \DateTimeZone $timezone;

    public function __construct(OrderHistoryGatewayInterface $orders, CartReconstructorInterface $carts, OperationalOrderService $operations, LockProviderInterface $locks, ?\DateTimeZone $timezone = null)
    {
        $this->orders = $orders; $this->carts = $carts; $this->operations = $operations; $this->locks = $locks;
        $this->timezone = $timezone ?? (function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC'));
    }

    public function list(array $input): array
    {
        $page = max(1, (int) ($input['page'] ?? 1));
        $perPage = max(1, min(50, (int) ($input['per_page'] ?? 20)));
        $status = sanitize_key((string) ($input['status'] ?? 'all'));
        $known = array_map(static function (string $key): string { return preg_replace('/^wc-/', '', $key) ?: ''; }, array_keys(function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : []));
        if ($status !== 'all' && ! in_array($status, $known, true)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_ORDER_FILTER, 'Unknown order status filter.');
        }
        $orderType = sanitize_key((string) ($input['order_type'] ?? 'all'));
        if (! in_array($orderType, ['all', 'takeaway', 'dine_in'], true)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_ORDER_FILTER, 'Unknown order type filter.');
        }
        $from = $this->date((string) ($input['date_from'] ?? ''), false);
        $to = $this->date((string) ($input['date_to'] ?? ''), true);
        if ($from > 0 && $to > 0 && $from > $to) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_ORDER_FILTER, 'Date from must not be after date to.');
        }
        return $this->orders->list([
            'page' => $page, 'per_page' => $perPage, 'status' => $status, 'order_type' => $orderType,
            'date_from_ts' => $from, 'date_to_ts' => $to,
            'search' => substr(sanitize_text_field((string) ($input['search'] ?? '')), 0, 100),
        ]);
    }

    public function detail(int $orderId): array
    {
        $order = $this->orders->find($orderId);
        if ($order === null) { throw Phase01Exception::withCode(Phase01ErrorCodes::ORDER_NOT_FOUND, 'CoffeePOS order was not found.'); }
        return $order;
    }

    public function cancel(int $orderId, string $expectedState, int $expectedRevision, string $operationId, int $userId, string $reason): array
    {
        return $this->operations->transition($orderId, $expectedState, $expectedRevision, 'cancelled', $this->operation($operationId), $userId, 'order_history', sanitize_text_field($reason))['order'];
    }

    public function refund(int $orderId, string $amount, string $reason, string $operationId, int $userId): array
    {
        $amount = $this->money($amount);
        $reason = substr(sanitize_text_field($reason), 0, 500);
        $operationId = $this->operation($operationId);
        $fingerprint = hash('sha256', wp_json_encode([$orderId, $amount, $reason]));
        return $this->locks->synchronized('refund-order-' . $orderId, function () use ($orderId, $amount, $reason, $operationId, $fingerprint, $userId): array {
            return $this->orders->refund($orderId, $amount, $reason, $operationId, $fingerprint, $userId);
        });
    }

    public function reorder(int $orderId, string $operationId): array
    {
        $operationId = $this->operation($operationId);
        $fingerprint = hash('sha256', (string) $orderId);
        return $this->locks->synchronized('reorder-order-' . $orderId . '-' . $operationId, function () use ($orderId, $operationId, $fingerprint): array {
            $existing = $this->orders->findReorderOperation($orderId, $operationId);
            if ($existing !== null) {
                if (! hash_equals((string) ($existing['fingerprint'] ?? ''), $fingerprint)) {
                    throw Phase01Exception::withCode(Phase01ErrorCodes::DUPLICATE_OPERATION_CONFLICT, 'Reorder operation ID was reused.');
                }
                try { return ['cart' => $this->carts->getSession((string) ($existing['pos_session_id'] ?? ''))->toArray(), 'replayed' => true]; }
                catch (\Throwable $ignored) { /* Reconstruct if the recorded session expired. */ }
            }
            $cart = $this->carts->reconstruct(function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '', $this->orders->reorderItems($orderId));
            $this->orders->recordReorderOperation($orderId, $operationId, $fingerprint, $cart->toArray()['pos_session_id']);
            return ['cart' => $cart->toArray(), 'replayed' => false];
        });
    }

    private function date(string $value, bool $end): int
    {
        $value = trim($value); if ($value === '') { return 0; }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) { throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_ORDER_FILTER, 'Date filter must use YYYY-MM-DD.'); }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $this->timezone);
        if (! $date || $date->format('Y-m-d') !== $value) { throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_ORDER_FILTER, 'Date filter is invalid.'); }
        return $end ? $date->setTime(23, 59, 59)->getTimestamp() : $date->getTimestamp();
    }

    private function money(string $value): string
    {
        $value = trim($value); $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        if (! preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,6}))?$/', $value, $matches) || (isset($matches[2]) && strlen($matches[2]) > $decimals) || (float) $value <= 0) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_REFUND, 'Refund amount must be a normalized positive amount.');
        }
        return number_format((float) $value, $decimals, '.', '');
    }

    private function operation(string $value): string
    {
        $value = sanitize_text_field($value);
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $value)) { throw Phase01Exception::withCode(Phase01ErrorCodes::DUPLICATE_OPERATION_CONFLICT, 'A valid client operation ID is required.'); }
        return $value;
    }
}
