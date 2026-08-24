<?php

declare(strict_types=1);

use CoffeePOS\Application\Projection\CartView;
use CoffeePOS\Domain\Cart\Cart;
use CoffeePOS\Integration\WooCommerce\WooCommerceCartSerializer;
use CoffeePOS\Support\Capabilities;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

if (! function_exists('__')) { function __($value) { return (string) $value; } }
if (! function_exists('sanitize_key')) { function sanitize_key($value) { return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) $value)); } }
if (! function_exists('sanitize_text_field')) { function sanitize_text_field($value) { return trim(strip_tags((string) $value)); } }
if (! function_exists('absint')) { function absint($value) { return abs((int) $value); } }
$phase12Options = [];
if (! function_exists('get_option')) { function get_option($name, $default = false) { global $phase12Options; return array_key_exists($name, $phase12Options) ? $phase12Options[$name] : $default; } }

final class Phase12Role
{
    public array $caps = [];
    public function add_cap(string $cap): void { $this->caps[$cap] = true; }
    public function remove_cap(string $cap): void { unset($this->caps[$cap]); }
}

$phase12Roles = ['administrator' => new Phase12Role(), 'shop_manager' => new Phase12Role()];
if (! function_exists('get_role')) { function get_role($name) { global $phase12Roles; return $phase12Roles[$name] ?? null; } }
if (! function_exists('add_role')) { function add_role($name, $label, $caps) { global $phase12Roles; $role = new Phase12Role(); foreach ($caps as $cap => $grant) { if ($grant) { $role->add_cap((string) $cap); } } $phase12Roles[$name] = $role; return $role; } }

$failures = [];
$assert = static function (bool $ok, string $message) use (&$failures): void { if (! $ok) { $failures[] = $message; } };

Capabilities::register();
$assert(isset($phase12Roles['coffeepos_cashier'], $phase12Roles['coffeepos_kitchen'], $phase12Roles['coffeepos_supervisor'], $phase12Roles['coffeepos_manager']), 'TC-01 default staff roles were not registered.');
$assert(isset($phase12Roles['coffeepos_cashier']->caps[Capabilities::ACCESS_CASHIER]) && ! isset($phase12Roles['coffeepos_cashier']->caps[Capabilities::REFUND_ORDERS]), 'TC-02 cashier capability bundle is incorrect.');
$assert(isset($phase12Roles['coffeepos_kitchen']->caps[Capabilities::ACCESS_KDS]) && ! isset($phase12Roles['coffeepos_kitchen']->caps[Capabilities::ACCESS_CASHIER]), 'TC-03 kitchen capability bundle is incorrect.');
$assert(isset($phase12Roles['coffeepos_supervisor']->caps[Capabilities::CANCEL_ORDERS]) && ! isset($phase12Roles['coffeepos_supervisor']->caps[Capabilities::REFUND_ORDERS]), 'TC-04 supervisor capability bundle is incorrect.');
$assert(count(array_intersect(Capabilities::all(), array_keys($phase12Roles['coffeepos_manager']->caps))) === count(Capabilities::all()), 'TC-05 manager lacks CoffeePOS capabilities.');
$assert(count(array_intersect(Capabilities::all(), array_keys($phase12Roles['administrator']->caps))) === count(Capabilities::all()), 'TC-06 administrator migration is incomplete.');
$assert(Capabilities::forScreen('settings') === Capabilities::MANAGE_SETTINGS, 'TC-06 frontend Settings route capability is missing.');

$cart = Cart::createSession('VND', 'phase12-session-abcdef', '2026-08-23T12:00:00Z');
$cart->setOrderNote('Deliver all drinks together');
$view = CartView::fromDomain($cart)->toArray();
$assert($view['order_note'] === 'Deliver all drinks together', 'TC-07 staff CartView omitted the order note.');
$assert(! array_key_exists('order_note', $view['customer_display']), 'TC-08 Customer Display leaked the private order note.');
$serializer = new WooCommerceCartSerializer();
$restored = $serializer->fromPayload($serializer->toPayload($cart));
$assert($restored->orderNote() === 'Deliver all drinks together', 'TC-09 session serializer lost the order note.');
$cart->clearItems();
$assert($cart->orderNote() === '', 'TC-10 clearing the cart did not clear its order note.');
try {
    $restored->setOrderNote(str_repeat('a', 2001));
    $assert(false, 'TC-11 oversized order note was accepted.');
} catch (InvalidArgumentException $exception) {
    $assert(true, 'TC-11 oversized order note was rejected.');
}

$root = dirname(__DIR__, 2);
$source = static function (string $path) use ($root): string { return (string) file_get_contents($root . '/' . $path); };
$router = $source('includes/POS/Router.php');
$assert(strpos($router, "=entry") !== false && strpos($router, 'wp_signon') !== false && strpos($router, 'safeReturnTarget') !== false, 'TC-12 /pos/ WordPress login/routing contract is incomplete.');
$assert(strpos($router, "screen === 'customer'") !== false && strpos($router, 'currentUserCanAccessScreen') !== false, 'TC-13 protected route and Customer Display boundaries are missing.');
$controllers = $source('includes/REST/OperationalOrderController.php') . $source('includes/REST/OrderHistoryController.php') . $source('includes/REST/ReportController.php');
foreach ([Capabilities::ACCESS_KDS, Capabilities::ACCESS_ORDER_QUEUE, Capabilities::CANCEL_ORDERS, Capabilities::REFUND_ORDERS, Capabilities::REORDER_ORDERS, Capabilities::VIEW_REPORTS] as $capability) {
    $assert(strpos($controllers, strtoupper(str_replace('coffeepos_', '', $capability))) !== false, 'TC-14 granular endpoint capability missing: ' . $capability);
}
$settings = $source('includes/Infrastructure/Settings/Settings.php');
$assert(strpos($settings, "'less_sugar', 'label' => 'Ít đường'") !== false && strpos($settings, 'OPTION_RECEIPT_PRINT_ORDER_NOTE') !== false, 'TC-15 Phase-12 quick-note/receipt defaults are missing.');
$assert(strpos($settings, 'if ($ids === [] || $ids ===') !== false, 'TC-15 Phase-12 upgrade must seed quick notes when the legacy option is empty.');
$settingsUi = $source('templates/settings/content.php') . $source('includes/POS/SettingsScreen.php');
$assert(strpos($settingsUi, '[product_ids]') !== false && strpos($settings, 'sanitizeIdList') !== false, 'TC-15 quick-note applicability must be settings-editable and normalized.');
$assert(strpos($settingsUi, 'service_tables_text') !== false && strpos($settingsUi, 'coffeepos_save_settings') !== false && strpos($settings, 'serviceTablesFromText') !== false, 'TC-15 Dine-in table textarea/settings save contract is incomplete.');
$phase12Options[\CoffeePOS\Infrastructure\Settings\Settings::OPTION_SERVICE_TABLES] = [
    ['id' => 2, 'label' => 'Table 02', 'enabled' => true, 'sort_order' => 20],
    ['id' => 5, 'label' => 'Garden', 'enabled' => true, 'sort_order' => 50],
];
$parsedTables = \CoffeePOS\Infrastructure\Settings\Settings::serviceTablesFromText("Table 02\nPatio\nPatio\n");
$assert(count($parsedTables) === 2 && $parsedTables[0]['id'] === 2 && $parsedTables[1]['id'] === 6 && $parsedTables[1]['sort_order'] === 20, 'TC-15 table textarea parsing did not preserve stable IDs, deduplicate labels, or order new tables.');
$adminBootstrap = $source('includes/Admin/AdminBootstrap.php');
$assert(strpos($router, "'settings'") !== false && strpos($router, "routeUrl('settings')") !== false && strpos($adminBootstrap, 'add_submenu_page') === false && strpos($adminBootstrap, 'admin_menu') === false, 'TC-15 frontend Settings routing or legacy-admin removal is incomplete.');
$cartApi = $source('includes/REST/CartController.php') . $source('assets/js/api/client.js');
$assert(strpos($cartApi, '/cart/order-note') !== false && strpos($cartApi, 'setOrderNote') !== false && strpos($cartApi, 'clearOrderNote') !== false, 'TC-16 order-note REST/client wiring is incomplete.');
$cartUi = $source('templates/cashier/cart-panel.php') . $source('templates/cashier/overlay-root.php') . $source('templates/components/order-note-dialog.php') . $source('assets/js/components/cart-panel.js');
$assert(strpos($cartUi, 'data-component="cart-context-row"') !== false && strpos($cartUi, 'data-component="order-note-trigger"') !== false && strpos($cartUi, 'data-component="order-note-dialog"') !== false && strpos($cartUi, 'openOrderNote') !== false, 'TC-16 compact cart context/order-note dialog wiring is incomplete.');
$orderGateway = $source('includes/Integration/WooCommerce/WooCommerceOrderGateway.php');
$assert(strpos($orderGateway, "'_coffeepos_order_note'") !== false && strpos($orderGateway, 'quickNoteMetadata') !== false, 'TC-17 note order persistence is incomplete.');
$receipt = $source('templates/receipt/receipt.php') . $source('assets/js/components/receipt-printer.js');
$assert(strpos($receipt, 'coffeepos-receipt-item-template') !== false && strpos($receipt, 'Receipt data is incomplete') !== false && strpos($receipt, 'innerHTML') === false, 'TC-18 authoritative PHP-owned receipt rendering is incomplete.');
$navigation = $source('templates/components/staff-navigation.php') . $source('templates/components/screen-shell.php') . $source('assets/css/app.css') . $source('assets/js/app.js');
$assert(strpos($navigation, 'staff-navigation') !== false && strpos($navigation, "['login', 'customer']") !== false && strpos($navigation, 'coffeepos-staff-nav__icon') !== false && strpos($navigation, 'grid-template-columns: 220px') !== false && strpos($navigation, 'toggle-staff-navigation') !== false && strpos($navigation, 'update(true)') !== false && strpos($navigation, "class=\"<?php echo \$isStaffScreen ? 'is-staff-nav-collapsed' : ''; ?>\"") !== false, 'TC-19 shared default-collapsed left-sidebar navigation boundary is incomplete.');
$managementHeaders = $source('templates/shifts/content.php') . $source('templates/order-history/content.php') . $source('templates/reports/content.php') . $source('templates/settings/content.php');
$assert(substr_count($managementHeaders, 'coffeepos-operations__header') === 4 && substr_count($managementHeaders, 'coffeepos-operations__tools') === 4 && strpos($managementHeaders, 'coffeepos-operations-header') === false && strpos($managementHeaders, 'coffeepos-reports-header') === false && strpos($managementHeaders, 'coffeepos-settings__header') === false, 'TC-20 management screens do not share the Order Queue header contract.');

if ($failures !== []) {
    foreach ($failures as $failure) { echo '[FAIL] ' . $failure . PHP_EOL; }
    exit(1);
}

echo "Phase 12 core scenarios passed.\n";
