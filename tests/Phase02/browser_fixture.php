<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(404);
    exit;
}

define('ABSPATH', dirname(__DIR__, 5));
define('COFFEEPOS_PATH', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);

function __(string $text, string $domain = ''): string
{
    return $text;
}

function esc_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_attr(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_url(string $url): string
{
    return filter_var($url, FILTER_VALIDATE_URL) ? esc_attr($url) : '';
}

function esc_html_e(string $text, string $domain = ''): void
{
    echo esc_html($text);
}

function esc_attr_e(string $text, string $domain = ''): void
{
    echo esc_attr($text);
}

function sanitize_key(string $key): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '';
}

function _n(string $single, string $plural, int $number, string $domain = ''): string
{
    return $number === 1 ? $single : $plural;
}

function disabled(bool $disabled): void
{
    if ($disabled) {
        echo ' disabled="disabled"';
    }
}

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CoffeePOS Phase 02 Browser Fixture</title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="coffeepos coffeepos-screen-cashier">
<main id="coffeepos-app" data-screen="cashier" data-coffeepos-screen="cashier">
    <?php require COFFEEPOS_PATH . 'templates/cashier/content.php'; ?>
</main>
<script>window.CoffeePOSConfig = {screen: 'cashier', restBase: '', restNonce: ''};</script>
<script src="/assets/js/core/app.js"></script>
<script src="/assets/js/api/client.js"></script>
<script src="/assets/js/state/cashier-store.js"></script>
<script src="/assets/js/ui/template-renderer.js"></script>
<script src="/assets/js/ui/modal.js"></script>
<script src="/assets/js/ui/toast.js"></script>
<script src="/assets/js/components/category-nav.js"></script>
<script src="/assets/js/components/product-search.js"></script>
<script src="/assets/js/components/product-card.js"></script>
<script src="/assets/js/components/catalog-renderer.js"></script>
<script src="/assets/js/components/cart-panel.js"></script>
<script src="/assets/js/components/product-modal.js"></script>
<script src="/assets/js/components/cart-context.js"></script>
<script src="/assets/js/components/order-type.js"></script>
<script src="/assets/js/screens/cashier.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
