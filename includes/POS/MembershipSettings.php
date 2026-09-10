<?php

declare(strict_types=1);

namespace CoffeePOS\POS;

use CoffeePOS\Infrastructure\Settings\Settings;
use CoffeePOS\Integration\WooCommerce\WooCommerceMembershipProvider;
use CoffeePOS\Integration\WooCommerce\WooCommerceCustomerGateway;
use CoffeePOS\Application\Customer\CustomerPhone;

final class MembershipSettings
{
    public static function validateTiers($input): array
    {
        return \CoffeePOS\Infrastructure\Settings\MembershipTierConfiguration::validateTiers($input);
    }

    public static function handle(array $input): array
    {
        try {
            if (! current_user_can(\CoffeePOS\Support\Capabilities::MANAGE_SETTINGS)) {
                throw new \RuntimeException(__('Forbidden', 'coffeepos'));
            }
            $action = (string) ($input['settings_action'] ?? '');
            if ($action === 'membership-tiers') {
                $tiers = self::validateTiers($input['tiers'] ?? []);
                Settings::update(Settings::OPTION_MEMBERSHIP_TIERS, $tiers);
                return ['type' => 'success', 'message' => __('Membership tiers saved.', 'coffeepos')];
            }
            $phone = CustomerPhone::normalize((string) ($input['member_phone'] ?? ''));
            $customer = $phone === '' ? null : (new WooCommerceCustomerGateway())->findByPhone($phone);
            if (! $customer || ! empty($customer['ambiguous'])) {
                throw new \RuntimeException(__('Find one member by phone before changing their tier.', 'coffeepos'));
            }
            if ($action === 'membership-override') {
                $code = (string) ($input['member_tier'] ?? '');
                if ($code !== '' && ! in_array($code, array_column(WooCommerceMembershipProvider::tiers(), 'code'), true)) {
                    throw new \RuntimeException(__('Invalid membership tiers.', 'coffeepos'));
                }
                $reason = sanitize_text_field((string) ($input['member_reason'] ?? ''));
                if (strlen($reason) < 3 || strlen($reason) > 500) {
                    throw new \RuntimeException(__('Enter a reason for the tier change.', 'coffeepos'));
                }
                $member = new \WC_Customer((int) $customer['id']);
                $member->update_meta_data(WooCommerceMembershipProvider::OVERRIDE_META, [
                    'code' => $code, 'reason' => $reason, 'actor' => get_current_user_id(), 'at' => gmdate('c'),
                ]);
                $member->save();
            }
            return [
                'type' => 'success', 'message' => $action === 'membership-override' ? __('Member tier saved.', 'coffeepos') : __('Member tier loaded.', 'coffeepos'),
                'tier_member' => $customer,
                'tier_membership' => (new WooCommerceMembershipProvider())->membershipForCustomer($customer),
                'tier_override' => (array) (new \WC_Customer((int) $customer['id']))->get_meta(WooCommerceMembershipProvider::OVERRIDE_META, true),
                'tier_spend' => (new \CoffeePOS\Integration\WooCommerce\WooCommerceMoney())->formatMinor((new WooCommerceMembershipProvider())->netSpend((int) $customer['id']), get_woocommerce_currency()),
            ];
        } catch (\Throwable $error) {
            return ['type' => 'error', 'message' => $error instanceof \InvalidArgumentException || $error instanceof \RuntimeException ? $error->getMessage() : __('Membership update failed.', 'coffeepos'), 'tier_rows' => $input['tiers'] ?? null];
        }
    }
}
