<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\CartSessionStoreInterface;
use CoffeePOS\Application\Error\Phase01ErrorCodes;
use CoffeePOS\Application\Error\Phase01Exception;
use CoffeePOS\Domain\Cart\Cart;

final class WooCommerceCartSessionStore implements CartSessionStoreInterface
{
    private const SESSION_KEY = 'coffeepos_pos_carts_v1';

    private WooCommerceCartSerializer $serializer;

    public function __construct(?WooCommerceCartSerializer $serializer = null)
    {
        $this->serializer = $serializer ?? new WooCommerceCartSerializer();
    }

    public function create(string $currency): Cart
    {
        $carts = $this->readCarts();
        $posSessionId = '';

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $candidate = function_exists('wp_generate_uuid4')
                ? wp_generate_uuid4()
                : bin2hex(random_bytes(16));

            if (! isset($carts[$candidate])) {
                $posSessionId = $candidate;
                break;
            }
        }

        if ($posSessionId === '') {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'Unable to create a unique POS session id.'
            );
        }

        $cart = Cart::createSession($currency, $posSessionId, gmdate('c'));
        $carts[$posSessionId] = $this->serializer->toPayload($cart);
        $this->writeCarts($carts);

        return $cart;
    }

    public function load(string $posSessionId): ?Cart
    {
        $normalizedId = trim($posSessionId);
        $carts = $this->readCarts();

        if ($normalizedId === '' || ! isset($carts[$normalizedId]) || ! is_array($carts[$normalizedId])) {
            return null;
        }

        try {
            return $this->serializer->fromPayload($carts[$normalizedId]);
        } catch (\Throwable $throwable) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CART,
                'Stored cart data is invalid.',
                ['pos_session_id' => $normalizedId]
            );
        }
    }

    public function save(Cart $cart, int $expectedRevision): Cart
    {
        $posSessionId = $cart->posSessionId();
        $carts = $this->readCarts();

        if ($posSessionId === '' || ! isset($carts[$posSessionId]) || ! is_array($carts[$posSessionId])) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::CART_SESSION_NOT_FOUND,
                'Cart session was not found.',
                ['pos_session_id' => $posSessionId]
            );
        }

        $currentRevision = (int) ($carts[$posSessionId]['revision'] ?? -1);

        if ($expectedRevision < 0 || $currentRevision !== $expectedRevision || $cart->revision() !== $expectedRevision) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::CART_REVISION_CONFLICT,
                'Cart revision is out of date.',
                [
                    'pos_session_id' => $posSessionId,
                    'expected_revision' => $expectedRevision,
                    'current_revision' => $currentRevision,
                ]
            );
        }

        $cart->advanceRevision(gmdate('c'));
        $carts[$posSessionId] = $this->serializer->toPayload($cart);
        $this->writeCarts($carts);

        return $cart;
    }

    private function readCarts(): array
    {
        $session = $this->session();
        $carts = $session->get(self::SESSION_KEY, []);

        return is_array($carts) ? $carts : [];
    }

    private function writeCarts(array $carts): void
    {
        $this->session()->set(self::SESSION_KEY, $carts);
    }

    private function session()
    {
        if (! function_exists('WC')) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'WooCommerce session is unavailable.'
            );
        }

        $woocommerce = WC();

        if (! is_object($woocommerce) || ! isset($woocommerce->session) || $woocommerce->session === null) {
            throw Phase01Exception::withCode(
                Phase01ErrorCodes::INVALID_CONFIGURATION,
                'WooCommerce session is unavailable.'
            );
        }

        return $woocommerce->session;
    }
}
