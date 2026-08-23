<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\PricingGatewayInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Domain\Cart\Cart;

final class WooCommercePricingGateway implements PricingGatewayInterface
{
    public function calculate(Cart $cart, ?string $couponCode = null): array
    {
        if (! class_exists('WC_Cart') || ! function_exists('wc_get_product')) {
            throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_CONFIGURATION, 'WooCommerce cart pricing is unavailable.');
        }

        $woocommerce = function_exists('WC') ? WC() : null;
        $originalCustomer = is_object($woocommerce) && isset($woocommerce->customer) ? $woocommerce->customer : null;
        if (is_object($woocommerce) && class_exists('WC_Customer')) {
            $woocommerce->customer = new \WC_Customer($cart->customerContext()->customerId() ?? 0, true);
        }

        try {
            // A standalone WC_Cart is used only as an in-memory pricing engine.
            // Its session object must not register shutdown/cookie callbacks:
            // those callbacks dereference WC()->cart, which is intentionally not
            // initialized for every custom REST request.
            $disableCartSessionHooks = static function (bool $initialize): bool {
                return false;
            };
            add_filter('woocommerce_cart_session_initialize', $disableCartSessionHooks, 10, 1);
            try {
                $wooCart = new \WC_Cart();
            } finally {
                remove_filter('woocommerce_cart_session_initialize', $disableCartSessionHooks, 10);
            }

            foreach ($cart->items() as $item) {
                $product = wc_get_product($item->variationId() > 0 ? $item->variationId() : $item->productId());
                if (! $product || ! $product->is_purchasable()) {
                    throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_PRODUCT, 'A cart product is unavailable.', ['product_id' => $item->productId()]);
                }
                if (! $product->is_in_stock() || ! $product->has_enough_stock($item->quantity())) {
                    throw Phase01Exception::withCode(Phase01ErrorCodes::OUT_OF_STOCK, 'A cart product does not have enough stock.', ['product_id' => $item->productId()]);
                }
                $key = $wooCart->add_to_cart($item->productId(), $item->quantity(), $item->variationId());
                if (! $key) {
                    throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_CART, 'WooCommerce rejected a cart item.');
                }
            }

            $code = trim((string) $couponCode);
            if ($code !== '' && ! $wooCart->apply_coupon($code)) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::COUPON_NOT_APPLICABLE, 'Coupon is not applicable.', ['code' => strtoupper($code)]);
            }
            try {
                $wooCart->calculate_totals();
            } catch (\Throwable $throwable) {
                throw Phase01Exception::withCode(Phase01ErrorCodes::INVALID_COUPON, 'WooCommerce could not calculate this coupon.');
            }

            return [
                'subtotal_minor' => $this->toMinor((string) $wooCart->get_subtotal()),
                'discount_minor' => $this->toMinor((string) $wooCart->get_discount_total()),
                'total_minor' => $this->toMinor((string) $wooCart->get_total('edit')),
                'currency' => $cart->currency(),
                'coupon_code' => $code === '' ? null : strtoupper($code),
            ];
        } finally {
            if (is_object($woocommerce)) {
                $woocommerce->customer = $originalCustomer;
            }
        }
    }

    public function applicableCoupons(Cart $cart): array
    {
        if (! function_exists('wc_get_orders')) {
            return [];
        }
        $posts = get_posts(['post_type' => 'shop_coupon', 'post_status' => 'publish', 'numberposts' => 100, 'orderby' => 'title', 'order' => 'ASC']);
        $items = [];
        foreach ($posts as $post) {
            $code = (string) $post->post_title;
            try {
                $this->calculate($cart, $code);
                $items[] = ['code' => strtoupper($code), 'label' => strtoupper($code)];
            } catch (\Throwable $throwable) {
                continue;
            }
        }
        return $items;
    }

    private function toMinor(string $amount): int
    {
        $decimals = function_exists('wc_get_price_decimals') ? wc_get_price_decimals() : 2;
        return (int) round((float) $amount * (10 ** $decimals));
    }
}
