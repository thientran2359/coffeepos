<?php

declare(strict_types=1);

namespace CoffeePOS\Integration\WooCommerce;

use CoffeePOS\Application\Contracts\MembershipProviderInterface;
use CoffeePOS\Application\Contracts\MembershipCouponProviderInterface;
use CoffeePOS\Infrastructure\Settings\Settings;

final class WooCommerceMembershipProvider implements MembershipProviderInterface, MembershipCouponProviderInterface
{
    public const OVERRIDE_META = '_coffeepos_tier_override';

    public static function validateWooCoupon(bool $valid, $coupon, $discounts): bool
    {
        if (! $valid) { return false; }
        try {
            $object = $discounts->get_object();
            $customerId = $object instanceof \WC_Order ? (int) $object->get_customer_id()
                : (WC()->customer ? (int) WC()->customer->get_id() : 0);
            return (new self())->couponAllowed((string) $coupon->get_code(), $customerId);
        } catch (\Throwable $error) {
            return false;
        }
    }

    public static function tiers(): array
    {
        return (array) Settings::get(Settings::OPTION_MEMBERSHIP_TIERS);
    }

    public function membershipForCustomer(array $customer): ?array
    {
        if (! Settings::get(Settings::OPTION_MEMBERSHIP_ENABLED) || self::tiers() === []) {
            return null;
        }
        $id = (int) ($customer['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $member = new \WC_Customer($id);
        $override = (array) $member->get_meta(self::OVERRIDE_META, true);
        $tier = self::selectTier(self::tiers(), $this->netSpend($id), (string) ($override['code'] ?? ''));
        return $tier === null ? null : ['tier_code' => $tier['code'], 'tier_label' => $tier['label']];
    }

    public static function selectTier(array $tiers, int $spendMinor, string $override): ?array
    {
        $selected = null;
        $money = new WooCommerceMoney();
        foreach ($tiers as $tier) {
            if ($override !== '' && $tier['code'] === $override) {
                return $tier;
            }
            $threshold = $money->toMinor((string) $tier['minimum']);
            if ($threshold <= $spendMinor && ($selected === null || $threshold > $money->toMinor((string) $selected['minimum']))) {
                $selected = $tier;
            }
        }
        // Removed manual tiers fail closed until the manager resets the override.
        return $override !== '' ? null : $selected;
    }

    public function netSpend(int $customerId): int
    {
        $total = 0;
        $money = new WooCommerceMoney();
        $page = 1;
        do {
            $orders = wc_get_orders([
                'customer_id' => $customerId, 'type' => 'shop_order',
                'status' => ['wc-processing', 'wc-completed'],
                'limit' => 100, 'page' => $page++, 'orderby' => 'ID', 'order' => 'ASC',
            ]);
            foreach ($orders as $order) {
                if ($order->get_created_via() !== 'coffeepos' || ! $order->get_date_paid()
                    || $order->get_currency() !== get_woocommerce_currency()) {
                    continue;
                }
                $total += max(0, $money->toMinor((string) $order->get_total()) - $money->toMinor((string) $order->get_total_refunded()));
            }
        } while (count($orders) === 100);
        return $total;
    }

    public function couponAllowed(string $code, int $customerId): bool
    {
        $assigned = [];
        foreach (self::tiers() as $tier) {
            if (strtolower($tier['coupon']) === strtolower(trim($code)) && trim($code) !== '') {
                $assigned[] = $tier['code'];
            }
        }
        if ($assigned === []) {
            return true;
        }
        $membership = $this->membershipForCustomer(['id' => $customerId]);
        return $membership !== null && in_array($membership['tier_code'], $assigned, true);
    }

    public function preferredCouponForMembership(?array $membership): string
    {
        $tierCode = sanitize_key((string) ($membership['tier_code'] ?? ''));
        if ($tierCode === '') {
            return '';
        }

        foreach (self::tiers() as $tier) {
            if ((string) ($tier['code'] ?? '') === $tierCode) {
                return trim((string) ($tier['coupon'] ?? ''));
            }
        }

        return '';
    }
}
