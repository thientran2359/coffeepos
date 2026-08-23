<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Coupon;

use CoffeePOS\Application\Contracts\CartSessionStoreInterface;
use CoffeePOS\Application\Contracts\MoneyFormatterInterface;
use CoffeePOS\Application\Contracts\PricingGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Application\Projection\CartView;
use CoffeePOS\Domain\Cart\Cart;
use CoffeePOS\Domain\Payment\PaymentContext;

final class CartCouponService
{
    private CartSessionStoreInterface $store;
    private PricingGatewayInterface $pricing;
    private MoneyFormatterInterface $formatter;

    public function __construct(CartSessionStoreInterface $store, PricingGatewayInterface $pricing, MoneyFormatterInterface $formatter)
    {
        $this->store = $store;
        $this->pricing = $pricing;
        $this->formatter = $formatter;
    }

    public function applicable(string $posSessionId): array
    {
        $cart = $this->load($posSessionId);
        return $cart->hasItems() ? $this->pricing->applicableCoupons($cart) : [];
    }

    public function apply(string $posSessionId, int $revision, string $code): CartView
    {
        $cart = $this->loadMutable($posSessionId, $revision);
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_COUPON, 'Coupon code is required.');
        }
        $pricing = $this->pricing->calculate($cart, $normalized);
        $cart->setPaymentContext(PaymentContext::withCoupon($normalized, (int) $pricing['discount_minor']));
        return $this->project($this->store->save($cart, $revision));
    }

    public function remove(string $posSessionId, int $revision): CartView
    {
        $cart = $this->loadMutable($posSessionId, $revision);
        $cart->setPaymentContext(PaymentContext::none());
        return $this->project($this->store->save($cart, $revision));
    }

    private function load(string $id): Cart
    {
        $cart = $this->store->load($id);
        if ($cart === null) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::CART_SESSION_NOT_FOUND, 'Cart session was not found.');
        }
        return $cart;
    }

    private function loadMutable(string $id, int $revision): Cart
    {
        $cart = $this->load($id);
        if ($cart->state() !== Cart::STATE_ACTIVE) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_CART, 'Cart is frozen for checkout.');
        }
        if ($revision < 0 || $cart->revision() !== $revision) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::CART_REVISION_CONFLICT, 'Cart revision is out of date.', ['current_revision' => $cart->revision(), 'cart' => $this->project($cart)->toArray()]);
        }
        return $cart;
    }

    private function project(Cart $cart): CartView
    {
        return CartView::fromDomain($cart, $this->formatter);
    }
}
