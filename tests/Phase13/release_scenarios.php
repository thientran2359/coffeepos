<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$main = (string) file_get_contents($root . '/coffeepos.php');
$readme = (string) file_get_contents($root . '/readme.txt');
$composer = json_decode((string) file_get_contents($root . '/composer.json'), true);

preg_match('/^ \* Version:\s*([^\r\n]+)/m', $main, $pluginVersion);
preg_match("/define\('COFFEEPOS_VERSION',\s*'([^']+)'\)/", $main, $constantVersion);
preg_match('/^Stable tag:\s*([^\r\n]+)/mi', $readme, $stableTag);

$assert(($pluginVersion[1] ?? '') === '1.0.0', 'Plugin header version is not 1.0.0.');
$assert(($pluginVersion[1] ?? '') === ($constantVersion[1] ?? ''), 'Plugin header and constant versions differ.');
$assert(($pluginVersion[1] ?? '') === ($stableTag[1] ?? ''), 'Plugin version and Stable tag differ.');
$assert(($composer['license'] ?? '') === 'GPL-2.0-or-later', 'Composer license is not GPL-2.0-or-later.');
$assert(stripos($main . $readme . json_encode($composer), 'proprietary') === false, 'Proprietary release metadata remains.');
$assert(strpos($main, 'Text Domain: coffeepos') !== false, 'Plugin text domain is missing.');
$assert(strpos($main, 'Domain Path: /languages') !== false, 'Plugin domain path is missing.');
$assert(strpos($main, 'Requires Plugins: woocommerce') !== false, 'WooCommerce dependency header is missing.');
$assert(strpos($readme, 'Tested up to: 7.0') !== false, 'Tested up to does not match the verified WordPress major version.');
$assert(strpos($readme, '== External services ==') !== false && strpos($readme, 'https://vietqr.app/') !== false, 'VietQR disclosure is incomplete.');
$assert(is_file($root . '/LICENSE'), 'LICENSE is missing.');
$assert(is_file($root . '/languages/coffeepos.pot'), 'POT catalog is missing.');
$assert(is_file($root . '/vendor/autoload.php'), 'Production Composer autoloader is missing.');

$settings = (string) file_get_contents($root . '/includes/Infrastructure/Settings/Settings.php');
foreach (['Ít đường', 'Nhiều đường', 'Ít sữa', 'Ít đá'] as $legacyDefault) {
    $assert(strpos($settings, $legacyDefault) === false, 'Vietnamese built-in Quick Note default remains: ' . $legacyDefault);
}

$assetLoader = (string) file_get_contents($root . '/includes/Infrastructure/Assets/AssetLoader.php');
$assert(strpos($assetLoader, "['wp-i18n']") !== false, 'Core JavaScript does not depend on wp-i18n.');
$assert(strpos($assetLoader, 'wp_set_script_translations') !== false, 'JavaScript translations are not registered.');

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Phase 13 release scenarios passed.\n");
