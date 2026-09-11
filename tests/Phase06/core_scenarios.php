<?php
/**
 * Phase 06 implementation contract scenarios.
 * Run: php tests/Phase06/core_scenarios.php
 */
declare(strict_types=1);

$root = dirname(__DIR__, 2);
require_once $root . '/includes/Application/Projection/CustomerCartView.php';

use CoffeePOS\Application\Projection\CustomerCartView;

$failures = array();

function phase06_assert(bool $condition, string $message): void
{
	global $failures;
	if (!$condition) {
		$failures[] = $message;
	}
}

function phase06_source(string $relative): string
{
	global $root;
	$content = file_get_contents($root . '/' . $relative);
	if ($content === false) {
		throw new RuntimeException('Cannot read ' . $relative);
	}
	return $content;
}

$projection = CustomerCartView::fromArray(array(
	'pos_session_id' => '1234567890abcdef',
	'revision' => 9,
	'state' => 'active',
	'service_type' => 'dine_in',
	'table' => array('table_id' => 4, 'table_label' => 'B4', 'secret' => 'hidden'),
	'customer' => array('customer_id' => 55, 'display_name' => 'Lan', 'phone' => '0900000000', 'is_guest' => false, 'membership' => array('tier' => 'Gold')),
	'items' => array(array(
		'key' => 'line-1', 'product_id' => 12, 'variation_id' => 0, 'name' => 'Latte',
		'quantity' => 2,
		'unit_price' => array('amount_minor' => 45000, 'display' => '45.000 ₫'),
		'unit_price_display' => '45.000 ₫',
		'line_total' => array('amount_minor' => 90000, 'display' => '90.000 ₫'),
		'line_total_display' => '90.000 ₫',
		'image' => 'https://example.test/latte.jpg', 'note' => 'staff-only note',
		'configuration' => array('cost' => 'secret'),
	)),
	'total_quantity' => 2,
	'subtotal' => array('amount_minor' => 90000, 'display' => '90.000 ₫'),
	'discount' => array('amount_minor' => 0, 'display' => '0 ₫'),
	'total' => array('amount_minor' => 90000, 'display' => '90.000 ₫'),
	'currency' => 'VND',
));

phase06_assert($projection['revision'] === 9, 'Customer projection must preserve the server cart revision.');
phase06_assert($projection['total']['amount_minor'] === 90000, 'Customer projection must preserve trusted totals.');
phase06_assert(!isset($projection['customer']['customer_id']), 'Customer projection must not expose the customer ID.');
phase06_assert(!isset($projection['customer']['phone']), 'Customer projection must not expose the customer phone.');
phase06_assert(!isset($projection['items'][0]['note']), 'Customer projection must not expose staff item notes.');
phase06_assert(!isset($projection['items'][0]['configuration']), 'Customer projection must not expose internal item configuration.');

$asset_loader = phase06_source('includes/Infrastructure/Assets/AssetLoader.php');
$cart_controller = phase06_source('includes/REST/CartController.php');
$customer_screen = phase06_source('assets/js/screens/customer.js');
$cashier_sync = phase06_source('assets/js/components/cashier-sync.js');
$customer_content = phase06_source('templates/customer/content.php');
$customer_menu = phase06_source('templates/customer/menu.php');
$customer_cart = phase06_source('templates/customer/cart.php');
$customer_templates = phase06_source('templates/components/customer-display-templates.php');
$customer_payment = phase06_source('assets/js/components/customer-payment.js');
$checkout = phase06_source('assets/js/components/checkout.js');
$checkout_template = phase06_source('templates/components/checkout-modal.php');

phase06_assert(strpos($asset_loader, "'pairingState'") !== false, 'Customer config must expose a controlled pairing state.');
phase06_assert(strpos($cart_controller, 'view') !== false && strpos($cart_controller, 'customer') !== false, 'Cart REST GET must support the customer-safe view.');
phase06_assert(strpos($customer_screen, 'setInterval') === false, 'Customer Display must not poll the cart on an interval.');
phase06_assert(strpos($customer_screen, 'getCustomerCart') !== false, 'Customer Display must support safe REST recovery.');
phase06_assert(strpos($customer_screen, "'display.ready'") !== false, 'Customer Display must perform the ready handshake.');
phase06_assert(strpos($customer_screen, "'state.requested'") !== false, 'Customer Display must request snapshots.');
phase06_assert(strpos($customer_screen, "'display.reset'") !== false, 'Customer Display must handle session rollover.');
phase06_assert(strpos($customer_screen, 'disconnectedReloadTimer') !== false && strpos($customer_screen, '30000') !== false && strpos($customer_screen, 'window.location.reload()') !== false, 'Customer Display must reload after 30 continuous seconds without a Cashier connection.');
phase06_assert(strpos($customer_screen, "state === 'connected' || state === 'unsupported'") !== false, 'Connected and unsupported displays must not enter the disconnected reload loop.');
phase06_assert(strpos($cashier_sync, "'state.snapshot'") !== false, 'Cashier must answer with a snapshot.');
phase06_assert(strpos($cashier_sync, 'customer_display') !== false, 'Cashier must publish the safe cart projection.');
phase06_assert(strpos($customer_content . $customer_menu . $customer_cart, 'data-action="add') === false, 'Customer templates must not expose mutation controls.');
phase06_assert(strpos($customer_cart, '<span class="coffeepos-eyebrow" data-field="customer-service">') !== false, 'Customer service context must replace the static cart eyebrow.');
phase06_assert(strpos($customer_cart, 'data-component="customer-member"') !== false && strpos($customer_cart, 'coffeepos-customer-membership-badge') !== false, 'Customer name and membership tier must share a member summary with a tier badge.');
phase06_assert(strpos($customer_menu . $customer_templates, 'customer-category-nav') === false && strpos($customer_templates, 'coffeepos-customer-category-button-template') === false, 'Customer Display must render category sections without category jump navigation.');
phase06_assert(strpos($checkout, 'resumeCart') !== false && strpos($checkout, 'checkout_order_id') !== false, 'A frozen cart must resume its existing payment workflow.');
phase06_assert(strpos($checkout, "cart.state === 'completed'") !== false, 'A completed cart must recover its paid result and next-cart action after reload.');
phase06_assert(strpos($checkout, 'createCartSession') !== false && strpos($checkout_template, 'start-fresh-order') !== false, 'A pending checkout must offer an explicit fresh cart session.');
phase06_assert(strpos($checkout, 'previewVietQr') !== false && strpos($checkout, 'confirmed_received') !== false, 'Bank QR preview and explicit cashier confirmation must be wired.');
phase06_assert(strpos($checkout, "querySelector('img').src") === false, 'Cashier must never render the VietQR image.');
phase06_assert(strpos($customer_cart, 'data-component="customer-payment-items"') !== false && strpos($customer_templates, 'coffeepos-customer-payment-item-template') !== false, 'Customer payment overlay must own a PHP-rendered compact item summary.');
phase06_assert(strpos($customer_payment, "renderer.renderList('coffeepos-customer-payment-item-template'") !== false, 'Customer payment overlay must render accepted cart items through TemplateRenderer.');
phase06_assert(strpos($customer_payment, 'customer-payment-subtotal') !== false && strpos($customer_payment, 'customer-payment-discount') !== false && strpos($customer_payment, 'customer-payment-total') !== false, 'Customer payment overlay must show trusted subtotal, discount, and total values.');
phase06_assert(strpos($customer_payment, 'payment && payment.summary') !== false, 'VietQR overlay totals must prefer the authoritative server pricing summary.');
phase06_assert(strpos($customer_payment, 'payment && payment.amount_display || summary && summary.total && summary.total.display') !== false && strpos($customer_payment, "payment.amount + ' '") === false, 'Customer payment amount must use the payment display value with the trusted cart total as its compatibility fallback.');
phase06_assert(strpos($customer_payment, 'payment.change_display') !== false, 'Customer payment change must use the server-formatted display value.');
phase06_assert(strpos($checkout, 'data.order.total_display') !== false && strpos($checkout, 'data.payment.change_display') !== false, 'Cashier success amounts must use server-formatted display values.');
phase06_assert(strpos(phase06_source('assets/js/screens/customer.js'), 'scheduleThankYou') === false, 'Payment success must remain until Cashier resets the display.');

if ($failures !== array()) {
	fwrite(STDERR, "Phase 06 scenarios failed:\n- " . implode("\n- ", $failures) . "\n");
	exit(1);
}

echo "Phase 06 core scenarios passed.\n";
