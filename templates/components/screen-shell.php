<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$context = \CoffeePOS\Infrastructure\Templates\TemplateLoader::context();
$screen = (string) ($context['screen'] ?? 'unknown');
$title = isset($title) ? (string) $title : ucfirst(str_replace('-', ' ', $screen));
$route = (string) ($context['route'] ?? '');
$contentTemplate = COFFEEPOS_PATH . 'templates/' . sanitize_key($screen) . '/content.php';
$isStaffScreen = ! in_array($screen, ['login', 'customer'], true);
$navCollapsed = \CoffeePOS\Infrastructure\Settings\Settings::isStaffNavCollapsed();
$appClasses = [
    'is-density-' . \CoffeePOS\Infrastructure\Settings\Settings::getInterfaceDensity(),
    \CoffeePOS\Infrastructure\Settings\Settings::shouldShowProductImages() ? 'is-product-images-visible' : 'is-product-images-hidden',
];

if ($isStaffScreen && $navCollapsed) {
    $appClasses[] = 'is-staff-nav-collapsed';
}

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html($title); ?> | <?php echo esc_html(\CoffeePOS\Infrastructure\Settings\Settings::getStoreName()); ?></title>
    <?php wp_head(); ?>
</head>
<body <?php body_class(['coffeepos', 'coffeepos-screen-' . $screen]); ?>>
<main
    id="coffeepos-app"
    data-coffeepos-screen="<?php echo esc_attr($screen); ?>"
    data-coffeepos-route="<?php echo esc_url($route); ?>"
    data-screen="<?php echo esc_attr($screen); ?>"
    class="<?php echo esc_attr(implode(' ', $appClasses)); ?>"
>
    <?php if ($isStaffScreen) : ?>
        <?php require COFFEEPOS_PATH . 'templates/components/staff-navigation.php'; ?>
    <?php endif; ?>
    <?php if (is_readable($contentTemplate)) : ?>
        <?php require $contentTemplate; ?>
    <?php else : ?>
        <header class="coffeepos-header">
            <h1><?php echo esc_html($title); ?></h1>
        </header>

        <section class="coffeepos-foundation-message">
            <p><?php esc_html_e('CoffeePOS foundation template loaded successfully.', 'coffeepos'); ?></p>
        </section>
    <?php endif; ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
