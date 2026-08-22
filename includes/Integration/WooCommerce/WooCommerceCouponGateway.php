<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\CouponGatewayInterface;
use WC_Coupon;

final class WooCommerceCouponGateway implements CouponGatewayInterface
{
    public function validate(string $couponCode, array $context = []): array
    {
        $normalizedCode = strtoupper(trim($couponCode));

        if ($normalizedCode === '') {
            return [
                'is_valid' => false,
                'message' => 'Coupon code is required.',
            ];
        }

        if (! class_exists(WC_Coupon::class)) {
            return [
                'is_valid' => false,
                'message' => 'WooCommerce coupon API is unavailable.',
            ];
        }

        $coupon = new WC_Coupon($normalizedCode);

        if ($coupon->get_id() <= 0) {
            return [
                'is_valid' => false,
                'message' => 'Coupon does not exist.',
            ];
        }

        $isValid = true;
        $message = '';

        if (method_exists($coupon, 'is_valid')) {
            $isValid = (bool) $coupon->is_valid();
        }

        if (! $isValid && method_exists($coupon, 'get_error_message')) {
            $message = (string) $coupon->get_error_message();
        }

        return [
            'is_valid' => $isValid,
            'message' => $message,
        ];
    }
}
