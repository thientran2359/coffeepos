<?php

declare(strict_types=1);

use CoffeePOS\Application\Cart\CartValidationService;
use CoffeePOS\Application\Checkout\CheckoutService;
use CoffeePOS\Application\Contracts\CartSessionStoreInterface;
use CoffeePOS\Application\Contracts\MoneyFormatterInterface;
use CoffeePOS\Application\Contracts\OrderGatewayInterface;
use CoffeePOS\Application\Contracts\PaymentGatewayInterface;
use CoffeePOS\Application\Contracts\PricingGatewayInterface;
use CoffeePOS\Application\Coupon\CartCouponService;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Domain\Cart\Cart;
use CoffeePOS\Domain\Cart\CartItem;
use CoffeePOS\Domain\Product\ModifierSelection;
use CoffeePOS\Domain\Product\QuickNoteSelection;
use CoffeePOS\Domain\Shared\Money;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
if (! function_exists('wp_json_encode')) {
    function wp_json_encode($value): string { return (string) json_encode($value); }
}

$failures = [];
$test = static function (string $name, callable $callback) use (&$failures): void {
    try { $callback(); echo '[PASS] ' . $name . PHP_EOL; }
    catch (Throwable $error) { $failures[] = $name . ': ' . $error->getMessage(); echo '[FAIL] ' . end($failures) . PHP_EOL; }
};
$assert = static function (bool $condition, string $message): void { if (! $condition) { throw new RuntimeException($message); } };

$store = new class implements CartSessionStoreInterface {
    public array $carts = [];
    public function create(string $currency): Cart { $id = 'phase05-session-' . (count($this->carts) + 1000); $cart = Cart::createSession($currency, $id, 'now'); $this->carts[$id] = $cart; return $cart; }
    public function load(string $posSessionId): ?Cart { return $this->carts[$posSessionId] ?? null; }
    public function save(Cart $cart, int $expectedRevision): Cart { if ($cart->revision() !== $expectedRevision) { throw Phase01Exception::withCode(Phase01ErrorCodes::CART_REVISION_CONFLICT, 'Stale.'); } $cart->advanceRevision('later'); return $this->carts[$cart->posSessionId()] = $cart; }
};
$formatter = new class implements MoneyFormatterInterface { public function format(int $amountMinor, string $currency): string { return $amountMinor . ' ' . $currency; } };
$pricing = new class implements PricingGatewayInterface {
    public function calculate(Cart $cart, ?string $couponCode = null): array { return ['subtotal_minor' => 10000, 'discount_minor' => $couponCode ? 1500 : 0, 'total_minor' => $couponCode ? 8500 : 10000, 'currency' => 'VND', 'coupon_code' => $couponCode]; }
    public function applicableCoupons(Cart $cart): array { return [['code' => 'SAVE15', 'label' => '<b>SAVE15</b>']]; }
};
$orders = new class implements OrderGatewayInterface {
    public array $orders = [];
    public int $creates = 0;
    public function findByOperation(string $operationId): ?array { return $this->orders[$operationId] ?? null; }
    public function create(Cart $cart, array $pricing, array $context): array { $this->creates++; return $this->orders[$context['operation_id']] = ['id' => 501, 'number' => '501', 'status' => 'processing', 'total' => '85.00', 'currency' => 'VND', 'operation_id' => $context['operation_id'], 'fingerprint' => $context['fingerprint'], 'pos_session_id' => $cart->posSessionId(), 'next_pos_session_id' => '', 'payment' => ['method' => $context['payment_method'], 'state' => 'paid', 'amount' => '85.00', 'received_amount' => $context['received_amount'], 'change' => $context['change'], 'reference' => (string) ($context['payment_reference'] ?? '')]]; }
    public function project(int $orderId): array { foreach ($this->orders as $order) { if ($order['id'] === $orderId) { return $order; } } throw new RuntimeException('Missing order'); }
    public function receipt(int $orderId): array { return ['order' => ['id' => $orderId], 'items' => []]; }
    public function setNextSessionId(int $orderId, string $posSessionId): void { foreach ($this->orders as &$order) { if ($order['id'] === $orderId) { $order['next_pos_session_id'] = $posSessionId; } } }
};
$bank = new class implements PaymentGatewayInterface {
    public function initialize(array $order, array $paymentContext): array { return ['method' => 'bank_transfer', 'state' => 'awaiting_cashier_confirmation', 'amount' => $order['total'], 'currency' => $order['currency'], 'reference' => $order['reference'], 'provider_available' => false, 'qr' => null]; }
    public function getStatus(array $order): array { return $this->initialize($order, []); }
    public function verifyCompletion(array $order, array $trustedInput): array { throw Phase01Exception::withCode(Phase01ErrorCodes::PAYMENT_VERIFICATION_FAILED, 'Unavailable'); }
};

$cart = $store->create('VND');
$cart->addItem(CartItem::create(1, 0, 1, Money::fromMinor(10000, 'VND'), ModifierSelection::empty(), QuickNoteSelection::empty(), '', ['product_name' => 'Coffee']));
$couponService = new CartCouponService($store, $pricing, $formatter);

$test('TC-01/03/06 coupon list and canonical mutation', static function () use ($assert, $couponService, $cart): void {
    $items = $couponService->applicable($cart->posSessionId());
    $assert($items[0]['label'] === '<b>SAVE15</b>', 'Gateway projection changed before safe text rendering.');
    $view = $couponService->apply($cart->posSessionId(), 0, 'save15')->toArray();
    $assert($view['revision'] === 1 && $view['discount']['amount_minor'] === 1500, 'Coupon did not revise/recalculate cart.');
});

$checkout = new CheckoutService($store, new CartValidationService(), $pricing, $orders, $bank, $formatter);
$test('TC-12 stale checkout rejected before order', static function () use ($assert, $checkout, $cart, $orders): void {
    try { $checkout->checkout($cart->posSessionId(), 0, 'operation-stale-001', ['method' => 'cash', 'received_amount' => '100.00'], 9); }
    catch (Phase01Exception $error) { $assert($error->errorCode() === Phase01ErrorCodes::CART_REVISION_CONFLICT && $orders->creates === 0, 'Stale checkout side effect occurred.'); return; }
    throw new RuntimeException('Stale checkout accepted.');
});
$test('TC-25 insufficient cash preserves active cart', static function () use ($assert, $checkout, $cart): void {
    try { $checkout->checkout($cart->posSessionId(), 1, 'operation-lowcash-01', ['method' => 'cash', 'received_amount' => '80.00'], 9); }
    catch (Phase01Exception $error) { $assert($error->errorCode() === Phase01ErrorCodes::INSUFFICIENT_CASH && $cart->state() === Cart::STATE_ACTIVE, 'Insufficient cash changed cart.'); return; }
    throw new RuntimeException('Insufficient cash accepted.');
});
$test('TC-26/28/49 cash checkout is paid and idempotent', static function () use ($assert, $checkout, $cart, $orders): void {
    $first = $checkout->checkout($cart->posSessionId(), 1, 'operation-cash-0001', ['method' => 'cash', 'received_amount' => '100.00'], 9);
    $second = $checkout->checkout($cart->posSessionId(), 1, 'operation-cash-0001', ['method' => 'cash', 'received_amount' => '100'], 9);
    $assert($first['payment']['state'] === 'paid' && $first['payment']['change'] === '15.00', 'Cash result is not authoritative.');
    $assert($first['order']['total_display'] === '8500 VND', 'Checkout order total is missing its server-formatted display value.');
    $assert($first['payment']['amount_display'] === '8500 VND' && $first['payment']['change_display'] === '1500 VND', 'Checkout payment amounts are missing server-formatted display values.');
    $assert($orders->creates === 1 && $first['order']['id'] === $second['order']['id'], 'Duplicate checkout created another order.');
    $assert($cart->state() === Cart::STATE_COMPLETED && $first['next_cart']['state'] === Cart::STATE_ACTIVE, 'Cart finalization/fresh cart failed.');
});
$test('TC-51 operation ID payload conflict', static function () use ($assert, $checkout, $cart): void {
    try { $checkout->checkout($cart->posSessionId(), 1, 'operation-cash-0001', ['method' => 'cash', 'received_amount' => '101.00'], 9); }
    catch (Phase01Exception $error) { $assert($error->errorCode() === Phase01ErrorCodes::DUPLICATE_OPERATION_CONFLICT, 'Wrong operation conflict.'); return; }
    throw new RuntimeException('Conflicting operation was accepted.');
});

$bankCart = $store->create('VND');
$bankCart->addItem(CartItem::create(2, 0, 1, Money::fromMinor(10000, 'VND'), ModifierSelection::empty(), QuickNoteSelection::empty(), '', ['product_name' => 'Tea']));
$test('TC-29 pre-order VietQR preview creates no order', static function () use ($assert, $checkout, $bankCart, $orders): void {
    $creates = $orders->creates;
    $result = $checkout->previewBankTransfer($bankCart->posSessionId(), 0);
    $assert($result['payment']['state'] === 'awaiting_cashier_confirmation', 'Preview state is wrong.');
    $assert(($result['payment']['summary']['total']['amount_minor'] ?? -1) === 10000, 'Preview summary does not match authoritative VietQR pricing.');
    $assert(($result['payment']['amount_display'] ?? '') === '10000 VND', 'VietQR preview amount is missing its server-formatted display value.');
    $assert($orders->creates === $creates && $bankCart->state() === Cart::STATE_ACTIVE, 'Preview created an order or froze the cart.');
});
$test('TC-30 bank checkout requires explicit cashier confirmation', static function () use ($assert, $checkout, $bankCart): void {
    try { $checkout->checkout($bankCart->posSessionId(), 0, 'operation-bank-no-confirm', ['method' => 'bank_transfer'], 9); }
    catch (Phase01Exception $error) { $assert($error->errorCode() === Phase01ErrorCodes::INVALID_PAYMENT, 'Missing confirmation returned wrong error.'); return; }
    throw new RuntimeException('Unconfirmed bank transfer created an order.');
});
$test('TC-31 confirmed bank transfer creates one paid order', static function () use ($assert, $checkout, $bankCart): void {
    $result = $checkout->checkout($bankCart->posSessionId(), 0, 'operation-bank-confirmed', ['method' => 'bank_transfer', 'confirmed_received' => true], 9);
    $assert($result['payment']['state'] === 'paid', 'Manual bank confirmation did not produce a paid order.');
    $assert($bankCart->state() === Cart::STATE_COMPLETED && $result['next_cart']['state'] === Cart::STATE_ACTIVE, 'Confirmed bank cart finalization failed.');
});

$test('TC-66/67 PHP templates and client authority boundary', static function () use ($assert): void {
    $root = dirname(__DIR__, 2);
    $coupon = file_get_contents($root . '/templates/components/coupon-selector.php');
    $checkoutJs = file_get_contents($root . '/assets/js/components/checkout.js');
    $gateway = file_get_contents($root . '/includes/Integration/Payment/PendingVietQrGateway.php');
    $assert(strpos((string) $coupon, 'coffeepos-coupon-option-template') !== false, 'Coupon native template missing.');
    $assert(strpos((string) $checkoutJs, 'payment.received_amount') !== false && strpos((string) $checkoutJs, 'payment.paid') === false, 'Client payment boundary is invalid.');
    $assert(strpos((string) $gateway, 'https://vietqr.app/img?') !== false, 'Approved VietQR preview URL is missing.');
});

$test('TC-70 POS pricing cart is isolated from the storefront cart', static function () use ($assert): void {
    $root = dirname(__DIR__, 2);
    $pricing = file_get_contents($root . '/includes/Integration/WooCommerce/WooCommercePricingGateway.php');
    $assert(strpos((string) $pricing, "did_action('woocommerce_load_cart_from_session')") !== false, 'Pricing does not complete the storefront session load before creating its scratch cart.');
    $assert(strpos((string) $pricing, '$wooCart->set_cart_contents($cartContents)') !== false, 'Pricing does not populate an isolated scratch cart from CoffeePOS items.');
});

exit($failures === [] ? 0 : 1);
