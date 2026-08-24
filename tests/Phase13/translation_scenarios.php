<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$languages = $root . '/languages';
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (! $condition) {
        $failures[] = $message;
    }
};

$pot = (string) file_get_contents($languages . '/coffeepos.pot');
$po = (string) file_get_contents($languages . '/coffeepos-vi.po');
$assert(is_file($languages . '/coffeepos-vi.mo'), 'Vietnamese MO file is missing.');
$assert(preg_match_all('/^msgid "(.*)"$/m', $pot, $potIds) >= 575, 'POT catalog is unexpectedly small.');
$assert(preg_match_all('/^msgid "(.*)"\R(?:msgid_plural .*\R)?msgstr(?:\[0\])? "(.*)"$/m', $po, $poEntries) >= 574, 'Vietnamese PO catalog is incomplete.');

$translations = [];
foreach ($poEntries[1] as $index => $source) {
    if ($source !== '') {
        $translations[stripcslashes($source)] = stripcslashes($poEntries[2][$index]);
    }
}
$assert(count($translations) === 574, 'Vietnamese catalog must translate all 574 source messages.');

foreach ($translations as $source => $translation) {
    $assert(trim($translation) !== '', 'Empty Vietnamese translation: ' . $source);
    preg_match_all('/%(?:\d+\$)?[-+0-9.]*[bcdeEfFgGosuxX]/', $source, $sourcePlaceholders);
    preg_match_all('/%(?:\d+\$)?[-+0-9.]*[bcdeEfFgGosuxX]/', $translation, $translatedPlaceholders);
    sort($sourcePlaceholders[0]);
    sort($translatedPlaceholders[0]);
    $assert($sourcePlaceholders[0] === $translatedPlaceholders[0], 'Placeholder mismatch: ' . $source);
}

$expected = [
    'Checkout' => 'Thanh toán',
    'Refund' => 'Hoàn tiền',
    'Shift request failed.' => 'Không thể thực hiện yêu cầu về ca làm việc.',
    'Less sugar' => 'Ít đường',
    'Order Queue' => 'Hàng đợi đơn hàng',
    'Receipt' => 'Hóa đơn',
];
foreach ($expected as $source => $translation) {
    $assert(($translations[$source] ?? '') === $translation, 'Required POS glossary translation is incorrect: ' . $source);
}

$jsonFiles = glob($languages . '/coffeepos-vi-coffeepos-*.json') ?: [];
$assert(count($jsonFiles) >= 20, 'JavaScript translation catalogs are missing.');
foreach ($jsonFiles as $jsonFile) {
    $payload = json_decode((string) file_get_contents($jsonFile), true);
    $assert(is_array($payload) && isset($payload['locale_data']['messages']), 'Invalid JavaScript translation JSON: ' . basename($jsonFile));
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Phase 13 Vietnamese translation scenarios passed.\n");
