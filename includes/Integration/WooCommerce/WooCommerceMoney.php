<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

final class WooCommerceMoney
{
    public function toMinor(string $amount): int
    {
        $decimals = $this->decimals();
        $normalized = preg_replace('/[^0-9\.\-]/', '', $amount) ?? '0';

        if ($normalized === '' || $normalized === '-' || $normalized === '.') {
            return 0;
        }

        $isNegative = strpos($normalized, '-') === 0;
        $unsigned = ltrim($normalized, '-');
        $parts = explode('.', $unsigned, 2);
        $whole = preg_replace('/\D+/', '', $parts[0]) ?: '0';
        $fraction = isset($parts[1]) ? preg_replace('/\D+/', '', $parts[1]) : '';
        $fraction = substr(str_pad($fraction, $decimals, '0'), 0, $decimals);
        $minor = (int) ($whole . $fraction);

        return $isNegative ? -$minor : $minor;
    }

    public function amountString(string $amount): string
    {
        $decimals = $this->decimals();

        if (function_exists('wc_format_decimal')) {
            return (string) wc_format_decimal($amount, $decimals);
        }

        return $this->minorToDecimal($this->toMinor($amount), $decimals);
    }

    public function formatMinor(int $amountMinor, string $currency): string
    {
        $amount = $this->minorToDecimal($amountMinor, $this->decimals());

        if (! function_exists('wc_price')) {
            return $amount . ' ' . strtoupper($currency);
        }

        $formatted = wc_price($amount, ['currency' => strtoupper($currency)]);
        $plain = function_exists('wp_strip_all_tags') ? wp_strip_all_tags($formatted) : strip_tags($formatted);
        $plain = html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $plain));
    }

    public function currentCurrency(): string
    {
        return function_exists('get_woocommerce_currency')
            ? strtoupper((string) get_woocommerce_currency())
            : 'USD';
    }

    private function decimals(): int
    {
        return function_exists('wc_get_price_decimals') ? max(0, (int) wc_get_price_decimals()) : 2;
    }

    private function minorToDecimal(int $amountMinor, int $decimals): string
    {
        $negative = $amountMinor < 0;
        $digits = (string) abs($amountMinor);

        if ($decimals === 0) {
            return ($negative ? '-' : '') . $digits;
        }

        $digits = str_pad($digits, $decimals + 1, '0', STR_PAD_LEFT);
        $whole = substr($digits, 0, -$decimals);
        $fraction = substr($digits, -$decimals);

        return ($negative ? '-' : '') . $whole . '.' . $fraction;
    }
}
