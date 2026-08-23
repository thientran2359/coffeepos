<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Checkout;

use CoffeePOS\Application\Cart\CartValidationService;
use CoffeePOS\Application\Contracts\CartSessionStoreInterface;
use CoffeePOS\Application\Contracts\MoneyFormatterInterface;
use CoffeePOS\Application\Contracts\OrderGatewayInterface;
use CoffeePOS\Application\Contracts\PaymentGatewayInterface;
use CoffeePOS\Application\Contracts\PricingGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Projection\CartView;
use CoffeePOS\Domain\Cart\Cart;

final class CheckoutService
{
    private CartSessionStoreInterface $store;
    private CartValidationService $validator;
    private PricingGatewayInterface $pricing;
    private OrderGatewayInterface $orders;
    private PaymentGatewayInterface $bankGateway;
    private MoneyFormatterInterface $formatter;

    public function __construct(CartSessionStoreInterface $store, CartValidationService $validator, PricingGatewayInterface $pricing, OrderGatewayInterface $orders, PaymentGatewayInterface $bankGateway, MoneyFormatterInterface $formatter)
    {
        $this->store = $store;
        $this->validator = $validator;
        $this->pricing = $pricing;
        $this->orders = $orders;
        $this->bankGateway = $bankGateway;
        $this->formatter = $formatter;
    }

    public function checkout(string $sessionId, int $revision, string $clientOperationId, array $payment, int $cashierId): array
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $clientOperationId)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::DUPLICATE_OPERATION_CONFLICT, 'A valid client operation id is required.');
        }
        $method = (string) ($payment['method'] ?? '');
        if (! in_array($method, ['cash', 'bank_transfer'], true)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_PAYMENT, 'Payment method must be cash or bank transfer.');
        }
        foreach (['change', 'paid', 'total', 'cashier_id'] as $forbidden) {
            if (array_key_exists($forbidden, $payment)) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_PAYMENT, 'Checkout contains an authoritative client field.');
            }
        }
        $received = $method === 'cash' ? $this->normalizeMoney((string) ($payment['received_amount'] ?? '')) : '';
        $operationId = hash('sha256', $cashierId . '|' . $sessionId . '|' . $clientOperationId);
        $fingerprint = hash('sha256', wp_json_encode([$sessionId, $revision, $method, $received]));

        return $this->withLock($operationId, function () use ($operationId, $fingerprint, $sessionId, $revision, $method, $received, $cashierId): array {
            $existing = $this->orders->findByOperation($operationId);
            if ($existing !== null) {
                if (! hash_equals((string) $existing['fingerprint'], $fingerprint)) {
                    throw Phase01Exception::withCode(Phase01ErrorCodes::DUPLICATE_OPERATION_CONFLICT, 'Operation id was already used with different checkout input.');
                }
                return $this->resultFromOrder($existing);
            }
            $cart = $this->store->load($sessionId);
            if ($cart === null) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::CART_SESSION_NOT_FOUND, 'Cart session was not found.');
            }
            if ($cart->state() !== Cart::STATE_ACTIVE) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_CART, 'Cart is not active.');
            }
            if ($revision < 0 || $cart->revision() !== $revision) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::CART_REVISION_CONFLICT, 'Cart revision is out of date.', ['current_revision' => $cart->revision(), 'cart' => $this->project($cart)]);
            }
            if (! $cart->hasItems()) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::EMPTY_CART, 'Cart is empty.');
            }
            $this->validator->validateCart($cart);
            $pricing = $this->pricing->calculate($cart, $cart->paymentContext()->couponCode());
            $receivedMinor = 0;
            $changeMinor = 0;
            if ($method === 'cash') {
                $receivedMinor = $this->toMinor($received);
                if ($receivedMinor < (int) $pricing['total_minor']) {
                    throw Phase01Exception::withCode(Phase01ErrorCodes::INSUFFICIENT_CASH, 'Cash received is below the authoritative order total.', ['required_minor' => (int) $pricing['total_minor']]);
                }
                $changeMinor = $receivedMinor - (int) $pricing['total_minor'];
            }
            $order = $this->orders->create($cart, $pricing, [
                'cashier_id' => $cashierId, 'payment_method' => $method,
                'operation_id' => $operationId, 'fingerprint' => $fingerprint,
                'received_amount' => $this->fromMinor($receivedMinor),
                'change' => $this->fromMinor($changeMinor),
            ]);
            $cart->beginCheckout((int) $order['id']);
            $cart = $this->store->save($cart, $revision);
            if ($method === 'cash') {
                $cart->completeCheckout();
                $this->store->save($cart, $cart->revision());
                $fresh = $this->store->create($cart->currency());
                $this->orders->setNextSessionId((int) $order['id'], $fresh->posSessionId());
                $order['next_pos_session_id'] = $fresh->posSessionId();
                return $this->resultFromOrder($order, $fresh);
            }
            return $this->resultFromOrder($order);
        });
    }

    public function paymentStatus(int $orderId): array
    {
        return $this->resultFromOrder($this->orders->project($orderId));
    }

    public function receipt(int $orderId): array
    {
        return $this->orders->receipt($orderId);
    }

    private function resultFromOrder(array $order, ?Cart $fresh = null): array
    {
        $payment = (array) $order['payment'];
        if (($payment['method'] ?? '') === 'bank_transfer' && ($payment['state'] ?? '') !== 'paid') {
            $payment = $this->bankGateway->initialize($order, $payment);
        }
        if ($fresh === null && ! empty($order['next_pos_session_id'])) {
            $fresh = $this->store->load((string) $order['next_pos_session_id']);
        }
        return [
            'order' => ['id' => $order['id'], 'number' => $order['number'], 'status' => $order['status'], 'total' => $order['total'], 'currency' => $order['currency']],
            'payment' => $payment,
            'receipt' => ['available' => ($payment['state'] ?? '') === 'paid'],
            'next_cart' => $fresh ? $this->project($fresh) : null,
        ];
    }

    private function normalizeMoney(string $value): string
    {
        $value = trim($value);
        if (! preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,6}))?$/', $value, $matches)) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_PAYMENT, 'Cash received must be a normalized non-negative amount.');
        }
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        if (isset($matches[2]) && strlen($matches[2]) > $decimals) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_PAYMENT, 'Cash received exceeds the configured currency precision.');
        }
        return number_format((float) $value, $decimals, '.', '');
    }

    private function toMinor(string $amount): int
    {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        return (int) round((float) $amount * (10 ** $decimals));
    }

    private function fromMinor(int $minor): string
    {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        return number_format($minor / (10 ** $decimals), $decimals, '.', '');
    }

    private function project(Cart $cart): array
    {
        return CartView::fromDomain($cart, $this->formatter)->toArray();
    }

    private function withLock(string $operationId, callable $callback): array
    {
        global $wpdb;
        $lockName = 'coffeepos:' . substr($operationId, 0, 40);
        $locked = false;
        if (isset($wpdb) && is_object($wpdb)) {
            $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lockName)) === 1;
            if (! $locked) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::PAYMENT_PENDING, 'Checkout is already being processed.');
            }
        }
        try {
            return $callback();
        } finally {
            if ($locked) {
                $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lockName));
            }
        }
    }
}
