<?php

declare(strict_types=1);

namespace CoffeePOS\Application\Customer;

final class CustomerPhone
{
    public static function normalize(string $phone): string
    {
        $value = trim($phone);

        if ($value === '' || preg_match('/^\+?[0-9\s.()\-]+$/', $value) !== 1) {
            return '';
        }

        $hasInternationalPrefix = strpos($value, '+') === 0;
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (($hasInternationalPrefix || strlen($digits) === 11) && strpos($digits, '84') === 0 && strlen($digits) === 11) {
            $digits = '0' . substr($digits, 2);
        }

        $length = strlen($digits);

        if ($length < 9 || $length > 15) {
            return '';
        }

        return $digits;
    }

    public static function mask(string $phone): string
    {
        $normalized = self::normalize($phone);

        if (strlen($normalized) < 8) {
            return '';
        }

        return substr($normalized, 0, 4) . '***' . substr($normalized, -3);
    }
}
