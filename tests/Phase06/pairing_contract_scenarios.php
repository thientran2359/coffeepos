<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$cashierSync = (string) file_get_contents($root . '/assets/js/components/cashier-sync.js');
$customer = (string) file_get_contents($root . '/assets/js/screens/customer.js');
$cashierHeader = (string) file_get_contents($root . '/templates/cashier/header.php');
$assetLoader = (string) file_get_contents($root . '/includes/Infrastructure/Assets/AssetLoader.php');
$syncContract = (string) file_get_contents($root . '/docs/04-api/SYNC-API.md');
$failures = [];

$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$assert(strpos($cashierSync, "window.open(url.href, 'coffeepos-customer-display')") !== false, 'Cashier must target the named Customer Display tab.');
$assert(strpos($cashierHeader, 'data-component="customer-display-status"') !== false, 'Cashier header must expose Customer Display connection status.');
$assert(strpos($cashierSync, "setDisplayConnection('connected')") !== false, 'A valid Customer Display heartbeat must update Cashier connection status.');
$assert(strpos($cashierSync, "setDisplayConnection('disconnected')") !== false, 'Cashier connection status must expire when the display stops responding.');
$assert(strpos($cashierSync, "url.searchParams.set('pos_session_id'") === false, 'Cashier must not place pos_session_id in the Customer Display URL.');
$assert(strpos($customer, "window.name = 'coffeepos-customer-display'") !== false, 'Customer Display must claim the named browsing context.');
$assert(strpos($customer, "pairing.post('display.control.ready'") !== false, 'Customer Display must announce pairing readiness.');
$assert(strpos($customer, "cleanUrl.searchParams.delete('pos_session_id')") !== false, 'Legacy pairing URLs must be cleaned after startup.');
$assert(strpos($assetLoader, "'displayPairingScope'") !== false, 'Asset configuration must include the opaque user pairing scope.');
$assert(strpos($syncContract, 'The control channel carries pairing only.') !== false, 'The pairing-only data boundary must remain documented.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Phase 06 automatic pairing contract scenarios passed.\n");
