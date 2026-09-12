<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
use CoffeePOS\Integration\WooCommerce\WooCommerceMembershipProvider as Provider;
use CoffeePOS\Infrastructure\Settings\MembershipTierConfiguration as Configuration;

function __($text, $domain = '') { return $text; }
function wc_get_price_decimals() { return 0; }
function get_woocommerce_currency() { return 'VND'; }
function sanitize_text_field($value) { return trim(strip_tags($value)); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function wc_format_coupon_code($value) { return strtolower($value); }
function wc_get_coupon_id_by_code($code) { return in_array($code, ['silver10', 'gold20'], true) ? 1 : 0; }
function get_option($name, $default = null) { return $GLOBALS['options'][$name] ?? $default; }
function wc_get_orders($args) { return array_values(array_filter($GLOBALS['orders'], static function ($order) use ($args) { return $order->customer === $args['customer_id'] && in_array('wc-' . $order->status, $args['status'], true); })); }
class WC_Customer {
    private int $id;
    public function __construct($id) { $this->id = $id; }
    public function get_meta($key, $single) { return $GLOBALS['overrides'][$this->id] ?? []; }
}
class MembershipTestOrder {
    public int $customer = 7;
    public string $status = 'completed';
    public string $source = 'coffeepos';
    public string $currency = 'VND';
    public bool $paid = true;
    public int $total = 100000;
    public int $refund = 0;
    public function get_created_via() { return $this->source; }
    public function get_currency() { return $this->currency; }
    public function get_date_paid() { return $this->paid ? '2026-09-09' : null; }
    public function get_total() { return $this->total; }
    public function get_total_refunded() { return $this->refund; }
}
$tiers = [
    ['code' => 'silver', 'label' => 'Silver', 'minimum' => '100000', 'coupon' => 'silver10'],
    ['code' => 'gold', 'label' => 'Gold', 'minimum' => '200000', 'coupon' => 'gold20'],
];
$GLOBALS['options'] = ['coffeepos_membership_enabled' => true, 'coffeepos_membership_tiers' => $tiers];
$GLOBALS['overrides'] = [];
$GLOBALS['orders'] = [];
function check($condition, $message) { if (! $condition) { throw new RuntimeException($message); } echo '[PASS] ' . $message . PHP_EOL; }
check(array_column(Configuration::defaultTiers(), 'code') === ['member', 'silver', 'gold'], 'Default member, silver and gold tiers are available');
check(count(Configuration::validateTiers([])) === 3, 'Empty tier submission restores default tiers');
check(Provider::selectTier($tiers, 99999, '') === null, 'Below first threshold has no tier');
check(Provider::selectTier($tiers, 100000, '')['code'] === 'silver', 'Exact threshold qualifies');
check(Provider::selectTier($tiers, 300000, '')['code'] === 'gold', 'Highest threshold wins');
check(Provider::selectTier($tiers, 300000, 'silver')['code'] === 'silver', 'Manual downgrade stays pinned');
check(Provider::selectTier($tiers, 0, 'gold')['code'] === 'gold', 'Manual upgrade overrides spend');
check(Provider::selectTier($tiers, 300000, 'removed') === null, 'Removed manual tier fails closed');
$provider = new Provider();
$paid = new MembershipTestOrder();
$partial = new MembershipTestOrder(); $partial->refund = 25000;
$web = new MembershipTestOrder(); $web->source = 'checkout';
$foreign = new MembershipTestOrder(); $foreign->currency = 'USD';
$unpaid = new MembershipTestOrder(); $unpaid->paid = false;
$other = new MembershipTestOrder(); $other->customer = 8;
$cancelled = new MembershipTestOrder(); $cancelled->status = 'cancelled';
$GLOBALS['orders'] = [$paid, $partial, $web, $foreign, $unpaid, $other, $cancelled];
check($provider->netSpend(7) === 175000, 'Spend excludes storefront, other currency, unpaid, cancelled and other customers; refunds deducted');
check($provider->couponAllowed('SILVER10', 7), 'Eligible tier coupon accepted case-insensitively');
check(! $provider->couponAllowed('gold20', 7), 'Higher tier coupon rejected');
check(! $provider->couponAllowed('silver10', 0), 'Guest cannot use member coupon');
check($provider->couponAllowed('ordinary', 0), 'Ordinary coupon unaffected');
check($provider->preferredCouponForMembership(['tier_code' => 'silver']) === 'silver10', 'Effective tier resolves its automatic coupon');
check($provider->preferredCouponForMembership(['tier_code' => 'missing']) === '', 'Unknown tier has no automatic coupon');
$GLOBALS['options']['coffeepos_membership_enabled'] = false;
check(! $provider->couponAllowed('silver10', 7), 'Disabled membership rejects restricted coupon');
$GLOBALS['options']['coffeepos_membership_enabled'] = true;
$GLOBALS['overrides'][7] = ['code' => 'gold'];
check($provider->couponAllowed('gold20', 7) && ! $provider->couponAllowed('silver10', 7), 'Pinned tier coupon replaces automatic tier offers');
$GLOBALS['overrides'][7] = ['code' => ''];
check($provider->couponAllowed('silver10', 7), 'Automatic reset restores calculated tier');
$paid->refund = 100000;
check(! $provider->couponAllowed('silver10', 7), 'Refund lowers automatic eligibility on next check');
check(count(Configuration::validateTiers($tiers)) === 2, 'Valid tier configuration accepted');
try { Configuration::validateTiers([$tiers[0], $tiers[0]]); throw new RuntimeException('Duplicates accepted'); }
catch (InvalidArgumentException $error) { check(true, 'Duplicate thresholds and codes rejected'); }
foreach ([['code' => [], 'label' => 'Invalid', 'minimum' => '0', 'coupon' => ''], ['code' => 'test', 'label' => 'Test', 'minimum' => '0', 'coupon' => 'missing']] as $invalid) {
    try { Configuration::validateTiers([$invalid]); throw new RuntimeException('Invalid configuration accepted'); }
    catch (InvalidArgumentException $error) { check(true, 'Malformed field or missing coupon rejected'); }
}
$router = file_get_contents(dirname(__DIR__, 2) . '/includes/POS/Router.php');
$screen = file_get_contents(dirname(__DIR__, 2) . '/includes/POS/MembershipScreen.php');
$template = file_get_contents(dirname(__DIR__, 2) . '/templates/members/content.php');
$directoryTemplate = file_get_contents(dirname(__DIR__, 2) . '/templates/members/directory.php');
$memberScript = file_get_contents(dirname(__DIR__, 2) . '/assets/js/screens/members.js');
$settingsTemplate = file_get_contents(dirname(__DIR__, 2) . '/templates/settings/content.php');
check(strpos($router, "'members'") !== false && strpos($router, "MembershipScreen::handleRequest()") !== false, 'Dedicated member route is registered');
check(strpos($screen, "coffeepos_manage_membership") !== false && strpos($screen, 'MANAGE_SETTINGS') !== false, 'Member actions require capability and nonce');
check(strpos($template, 'data-component="membership-screen"') !== false, 'Member screen uses a PHP-owned application template');
check(strpos($directoryTemplate, 'coffeepos-members__detail-page') !== false && strpos($directoryTemplate, 'coffeepos-history-dialog') !== false, 'Member detail owns order history and an order detail dialog');
check(strpos($directoryTemplate, 'data-component="member-pin-dialog"') !== false && strpos($memberScript, "temporary-pin'") !== false, 'Member PIN reset uses an AJAX one-time popup');
check(strpos($memberScript, 'loadOrderDetail') !== false && strpos($memberScript, 'member-edit-dialog') !== false, 'Member screen opens order and quick-edit dialogs');
check(strpos($directoryTemplate, 'data-action="quick-edit-member"') !== false && strpos($directoryTemplate, 'name="return_to_list"') !== false, 'Quick edit stays on the member directory before and after save');
check(strpos($settingsTemplate, 'OPTION_MEMBERSHIP_TIERS') !== false, 'Tier configuration remains inside the Settings form');
echo "Phase 14 membership scenarios passed.\n";
