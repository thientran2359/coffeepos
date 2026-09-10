<?php

declare(strict_types=1);

namespace CoffeePOS\Infrastructure\Settings;

final class MembershipTierConfiguration
{
    public static function defaultTiers(): array
    {
        $zeroDecimal = function_exists('wc_get_price_decimals') && wc_get_price_decimals() === 0;

        return [
            ['code' => 'member', 'label' => 'Member', 'minimum' => '0', 'coupon' => ''],
            ['code' => 'silver', 'label' => 'Silver', 'minimum' => $zeroDecimal ? '1000000' : '500', 'coupon' => ''],
            ['code' => 'gold', 'label' => 'Gold', 'minimum' => $zeroDecimal ? '3000000' : '2000', 'coupon' => ''],
        ];
    }

    public static function validateTiers($input): array
    {
        if (! is_array($input) || count($input) > 30) {
            throw new \InvalidArgumentException(__('Invalid membership tiers.', 'coffeepos'));
        }
        $rows = [];
        $codes = [];
        $thresholds = [];
        foreach ($input as $row) {
            if (! is_array($row)) {
                throw new \InvalidArgumentException(__('Invalid membership tiers.', 'coffeepos'));
            }
            foreach (['code', 'label', 'minimum', 'coupon'] as $field) {
                if (isset($row[$field]) && ! is_scalar($row[$field])) {
                    throw new \InvalidArgumentException(__('Invalid membership tiers.', 'coffeepos'));
                }
            }
            if (trim(implode('', array_map('strval', array_intersect_key($row, array_flip(['code', 'label', 'minimum', 'coupon']))))) === '') { continue; }
            $code = trim((string) ($row['code'] ?? ''));
            $label = sanitize_text_field((string) ($row['label'] ?? ''));
            $minimum = trim((string) ($row['minimum'] ?? ''));
            $coupon = wc_format_coupon_code(trim((string) ($row['coupon'] ?? '')));
            $minor = (new \CoffeePOS\Integration\WooCommerce\WooCommerceMoney())->toMinor($minimum);
            if (! preg_match('/^[a-z0-9_-]{1,40}$/', $code) || $label === '' || strlen($label) > 160
                || ! preg_match('/^\d{1,12}(?:\.\d{1,2})?$/', $minimum)
                || isset($codes[$code]) || isset($thresholds[$minor])
                || (wc_get_price_decimals() === 0 && strpos($minimum, '.') !== false)) {
                throw new \InvalidArgumentException(__('Use unique tier codes and thresholds, a name, and a non-negative amount.', 'coffeepos'));
            }
            if ($coupon !== '' && ! wc_get_coupon_id_by_code($coupon)) {
                throw new \InvalidArgumentException(__('A tier coupon does not exist in WooCommerce.', 'coffeepos'));
            }
            $codes[$code] = true;
            $thresholds[$minor] = true;
            $rows[] = compact('code', 'label', 'minimum', 'coupon');
        }
        usort($rows, static function (array $a, array $b): int { return (float) $a['minimum'] <=> (float) $b['minimum']; });
        return $rows === [] ? self::defaultTiers() : $rows;
    }

}
