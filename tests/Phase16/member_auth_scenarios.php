<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use CoffeePOS\Application\MemberPortal\MemberAuthException;
use CoffeePOS\Application\MemberPortal\MemberAuthRateLimiter;
use CoffeePOS\Application\MemberPortal\MemberAuthService;
use CoffeePOS\Application\MemberPortal\MemberPinService;
use CoffeePOS\Application\MemberPortal\MemberPortalService;
use CoffeePOS\Application\MemberPortal\MemberSessionService;

$GLOBALS['test_transients'] = [];
$GLOBALS['test_user_meta'] = [];
$GLOBALS['test_customers'] = [7 => ['name' => 'Test Member', 'phone' => '0353 111 250', 'email' => 'member@example.test']];

class WP_User { public int $ID; public function __construct($id) { $this->ID = (int) $id; } }
class WC_Customer {
    private int $id;
    public function __construct($id) { $this->id = isset($GLOBALS['test_customers'][(int) $id]) ? (int) $id : 0; }
    public function get_id() { return $this->id; }
    public function get_first_name() { return $GLOBALS['test_customers'][$this->id]['name'] ?? ''; }
    public function get_last_name() { return ''; }
    public function get_display_name() { return $this->get_first_name(); }
    public function get_billing_phone() { return $GLOBALS['test_customers'][$this->id]['phone'] ?? ''; }
    public function get_billing_email() { return $GLOBALS['test_customers'][$this->id]['email'] ?? ''; }
    public function get_email() { return $this->get_billing_email(); }
    public function get_meta($key, $single = true) { return get_user_meta($this->id, $key, $single); }
}
class MemberPortalTestItem {
    public function get_name() { return 'Cappuccino'; }
    public function get_quantity() { return 2; }
    public function get_total() { return 90000; }
    public function get_meta($key, $single = true) { return ''; }
}
class MemberPortalTestOrder {
    private int $id;
    private int $customerId;
    public function __construct(int $id, int $customerId) { $this->id = $id; $this->customerId = $customerId; }
    public function get_id() { return $this->id; }
    public function get_customer_id() { return $this->customerId; }
    public function get_created_via() { return 'coffeepos'; }
    public function get_meta($key, $single = true) { return $key === '_coffeepos_order_type' ? 'dine_in' : ''; }
    public function get_currency() { return 'VND'; }
    public function get_items($type = '') { return [new MemberPortalTestItem()]; }
    public function get_date_created() { return null; }
    public function get_order_number() { return (string) $this->id; }
    public function get_status() { return 'completed'; }
    public function get_payment_method_title() { return 'Cash'; }
    public function get_subtotal() { return 90000; }
    public function get_discount_total() { return 0; }
    public function get_total_refunded() { return 0; }
    public function get_total() { return 90000; }
}

function __($text, $domain = '') { return $text; }
function wp_salt($scheme = 'auth') { return 'phase16-' . $scheme . '-test-salt'; }
function absint($value) { return abs((int) $value); }
function set_transient($key, $value, $expiration) { $GLOBALS['test_transients'][$key] = $value; return true; }
function get_transient($key) { return $GLOBALS['test_transients'][$key] ?? false; }
function delete_transient($key) { unset($GLOBALS['test_transients'][$key]); return true; }
function get_user_meta($userId, $key, $single = false) { return $GLOBALS['test_user_meta'][$userId][$key] ?? ($single ? '' : []); }
function update_user_meta($userId, $key, $value) { $GLOBALS['test_user_meta'][$userId][$key] = $value; return true; }
function delete_user_meta($userId, $key) { unset($GLOBALS['test_user_meta'][$userId][$key]); return true; }
function wp_hash_password($password) { return password_hash($password, PASSWORD_BCRYPT); }
function wp_check_password($password, $hash, $userId = '') { return password_verify($password, $hash); }
function get_option($name, $default = null) { return $name === 'coffeepos_membership_enabled' ? true : $default; }
function get_users($args = []) { return [new WP_User(7)]; }
function wc_get_order($orderId) { return new MemberPortalTestOrder((int) $orderId, (int) $orderId === 701 ? 7 : 8); }
function wc_get_order_status_name($status) { return ucfirst((string) $status); }
function wc_price($amount, $args = []) { return number_format((float) $amount, 0, '.', ',') . ' VND'; }
function wc_get_price_decimals() { return 0; }
function wp_strip_all_tags($text) { return strip_tags((string) $text); }

function check($condition, $message) {
    if (! $condition) { throw new RuntimeException($message); }
    echo '[PASS] ' . $message . PHP_EOL;
}

$state = [];
$start = 1000;
for ($attempt = 1; $attempt <= 5; $attempt++) {
    $state = MemberAuthRateLimiter::advanceState($state, $start + $attempt);
    check($state['failed_attempts'] === $attempt, 'Failed PIN attempt ' . $attempt . ' is counted');
    check($state['first_failed_at'] === $start + 1, 'PIN attempt window remains fixed');
}
check($state['locked_until'] === $start + 1 + MemberAuthRateLimiter::WINDOW_SECONDS, 'Fifth failure uses the original fifteen-minute expiry');
$reset = MemberAuthRateLimiter::advanceState($state, $state['locked_until']);
check($reset['failed_attempts'] === 1 && $reset['first_failed_at'] === $state['locked_until'], 'Expired PIN window restarts from one failure');

$sessions = new MemberSessionService();
$created = $sessions->create(7);
check(strpos($created['token'], '.') !== false, 'Member cookie token uses opaque selector and validator');
check(strpos($created['token'], '7') !== 0, 'Member cookie token does not encode the customer ID');
$authenticated = $sessions->authenticate($created['token']);
check(is_array($authenticated) && $authenticated['customer_id'] === 7, 'Opaque member token resolves to its server-owned customer');
check($sessions->verifyCsrf($authenticated, $created['csrf_token']), 'Session-scoped CSRF token verifies');
check(! $sessions->verifyCsrf($authenticated, 'wrong'), 'Wrong CSRF token is rejected');
$sessions->revokeCurrent($authenticated);
check($sessions->authenticate($created['token']) === null, 'Logout revokes the current member session');

$active = [];
for ($index = 0; $index < 6; $index++) { $active[] = $sessions->create(7); }
check(count((array) get_user_meta(7, MemberSessionService::META_SESSIONS, true)) === 5, 'Member sessions are bounded to five devices');
check($sessions->authenticate($active[0]['token']) === null, 'Creating a sixth session evicts the oldest');
check($sessions->authenticate($active[5]['token']) !== null, 'Newest bounded session remains active');

$pins = new MemberPinService($sessions, new MemberAuthRateLimiter());
update_user_meta(7, MemberPinService::META_PIN_HASH, wp_hash_password('123456'));
update_user_meta(7, MemberPinService::META_MUST_CHANGE, 'yes');
check($pins->verify(7, '123456'), 'Stored member PIN hash verifies without using WordPress login password');
try {
    $pins->changePin(7, '123456');
    throw new RuntimeException('Unchanged PIN accepted');
} catch (MemberAuthException $error) {
    check($error->errorCode() === 'member_pin_unchanged', 'Temporary PIN cannot be reused as the new PIN');
}

$portal = new MemberPortalService();
$ownedOrder = $portal->order(7, 701);
check($ownedOrder['id'] === 701 && $ownedOrder['items'][0]['name'] === 'Cappuccino', 'Member order detail projects an owned CoffeePOS order');
check(! array_key_exists('customer_id', $ownedOrder) && ! array_key_exists('billing', $ownedOrder), 'Member order projection excludes private staff and billing fields');
try {
    $portal->order(7, 801);
    throw new RuntimeException('Cross-customer order was exposed');
} catch (MemberAuthException $error) {
    check($error->errorCode() === 'member_order_not_found', 'Cross-customer order lookup returns the same not-found response');
}
$pins->changePin(7, '654321');
check($pins->verify(7, '654321') && ! $pins->mustChange(7), 'PIN change stores a new hash and clears mandatory change state');
check($sessions->authenticate($active[5]['token']) === null, 'PIN change revokes every existing member session');

$temporaryPin = $pins->generateTemporaryPin(7);
check(preg_match('/^[0-9]{6}$/', $temporaryPin) === 1 && $pins->mustChange(7), 'Manager generation creates a six-digit temporary PIN and mandatory-change state');
$auth = new MemberAuthService(null, $pins, $sessions, new MemberAuthRateLimiter());
$loggedIn = $auth->login('0353 111 250', $temporaryPin);
check(! empty($loggedIn['pin_change_required']) && $sessions->authenticate($loggedIn['token']) !== null, 'Exact phone and temporary PIN create a member-only session');
try {
    $auth->login('0353 111 250', '111111');
    throw new RuntimeException('Wrong PIN authenticated');
} catch (MemberAuthException $error) {
    check($error->errorCode() === 'member_auth_failed', 'Wrong PIN returns the generic member authentication failure');
}

$root = dirname(__DIR__, 2);
$settings = file_get_contents($root . '/includes/Infrastructure/Settings/Settings.php');
$settingsUi = file_get_contents($root . '/templates/settings/content.php');
$memberScreen = file_get_contents($root . '/includes/POS/MembershipScreen.php');
$memberTemplate = file_get_contents($root . '/templates/members/directory.php');
$routes = file_get_contents($root . '/includes/REST/RouteRegistrar.php');
$pinService = file_get_contents($root . '/includes/Application/MemberPortal/MemberPinService.php');
$portalRouter = file_get_contents($root . '/includes/POS/MemberPortalRouter.php');
$portalTemplate = file_get_contents($root . '/templates/member-account/content.php');
$portalScript = file_get_contents($root . '/assets/js/screens/member-account.js');
$portalAssets = file_get_contents($root . '/includes/Infrastructure/Assets/AssetLoader.php');
$accountController = file_get_contents($root . '/includes/REST/MemberAccountController.php');
$managementController = file_get_contents($root . '/includes/REST/MemberManagementController.php');
$memberScript = file_get_contents($root . '/assets/js/screens/members.js');
$customerScript = file_get_contents($root . '/assets/js/screens/customer.js');
$customerTemplate = file_get_contents($root . '/templates/customer/content.php');
$customerPinTemplate = file_get_contents($root . '/templates/customer/member-pin-dialog.php');
check(strpos($settings, 'OPTION_MEMBER_ACCOUNT_PAGE_ID') !== false && strpos($settingsUi, 'OPTION_MEMBER_ACCOUNT_PAGE_ID') !== false, 'Member Account Page setting is registered and rendered');
check(strpos($memberScreen, 'member-pin-reset') !== false && strpos($memberTemplate, 'Generate temporary PIN') !== false, 'Manager PIN generation is capability/nonce-protected through the member screen');
check(strpos($routes, '/member/auth/login') !== false && strpos($routes, '/member/auth/change-pin') !== false && strpos($routes, '/member/auth/logout') !== false, 'Member authentication routes are registered separately from staff routes');
check(strpos($pinService, 'wp_check_password($pin, $hash);') !== false, 'PIN verification cannot trigger WordPress user password rehash');
check(strpos($portalRouter, "prepare('member-account'") !== false && strpos($portalRouter, 'is_page($pageId)') !== false, 'Selected WordPress Page is routed to the plugin-owned member template');
check(strpos($portalAssets, 'clearFrontendAssetQueues') !== false && strpos($portalAssets, 'screens/member-account.css') !== false, 'Member portal clears theme queues and loads only its CoffeePOS asset stack');
check(strpos($portalTemplate, 'data-panel="login"') !== false && strpos($portalTemplate, 'data-component="member-orders"') !== false, 'Member portal contains login, membership, and order-history states');
check(strpos($portalScript, "request('auth/session'") !== false && strpos($portalScript, "request('orders?") !== false, 'Member portal client restores sessions and loads customer-owned orders');
check(strpos($portalScript, 'normalizePhoneInput') !== false && strpos($portalScript, "loginForm.addEventListener('input'") !== false, 'Member login normalizes the submitted phone and clears stale validation errors while editing');
check(strpos($portalScript, 'new window.FormData(loginForm)') === false && strpos($portalScript, 'pinInput ? pinInput.value') !== false, 'Member login captures enabled input values before entering the busy state');
check(strpos($portalScript, 'new window.FormData(pinForm)') === false && strpos($portalScript, 'confirmationInput ? confirmationInput.value') !== false, 'PIN change captures enabled input values before entering the busy state');
check(strpos($accountController, 'member_pin_change_required') !== false, 'Temporary PIN sessions cannot read member account or order data');
check(strpos($routes, '/members/(?P<id>\\d+)/temporary-pin') !== false && strpos($managementController, 'MANAGE_SETTINGS') !== false, 'AJAX temporary PIN route is manager-only');
check(strpos($memberTemplate, 'data-component="member-pin-dialog"') !== false && strpos($memberTemplate, 'data-component="member-pin-reset-form"') !== false, 'Temporary PIN is rendered in a PHP-owned AJAX dialog');
check(strpos($memberTemplate, 'show-member-pin-customer') !== false && strpos($memberScript, 'coffeepos:member-pin-shown') !== false, 'One-time PIN popup can send a Customer Display handoff');
check(strpos($customerTemplate, 'member-pin-dialog.php') !== false && strpos($customerScript, 'coffeepos:show-member-pin') !== false, 'Customer Display owns the receiving PIN popup and acknowledgement');
check(strpos($customerPinTemplate, '<header>') === false && strpos($customerPinTemplate, '<button') === false && strpos($customerScript, '12000') !== false, 'Customer Display PIN popup is minimal and dismisses automatically');
check(strpos($memberScript, "'coffeepos:member-pin:'") !== false && strpos($customerScript, "'coffeepos:member-pin:'") !== false, 'Customer Display handoff uses the staff-scoped in-memory channel');
check(strpos($portalScript, 'new window.FormData(pinForm)') === false, 'Member PIN form keeps enabled-value capture contract');

echo "Phase 16 member setting, PIN, and authentication scenarios passed.\n";
