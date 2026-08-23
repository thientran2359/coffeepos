<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$context = \CoffeePOS\Infrastructure\Templates\TemplateLoader::context();
$currentScreen = (string) ($context['screen'] ?? '');
$items = is_array($context['navigation'] ?? null) ? $context['navigation'] : \CoffeePOS\POS\Router::navigationItems();
$user = wp_get_current_user();
?>
<nav class="coffeepos-staff-nav" data-component="staff-navigation" aria-label="<?php esc_attr_e('CoffeePOS staff navigation', 'coffeepos'); ?>">
    <a class="coffeepos-staff-nav__brand" href="<?php echo esc_url(\CoffeePOS\POS\Router::routeUrl()); ?>">CoffeePOS</a>
    <div class="coffeepos-staff-nav__links">
        <?php foreach ($items as $item) :
            $itemScreen = (string) ($item['screen'] ?? '');
            $active = $itemScreen === $currentScreen;
            ?>
            <a href="<?php echo esc_url((string) ($item['url'] ?? '')); ?>"<?php echo $active ? ' aria-current="page"' : ''; ?> class="<?php echo $active ? 'is-active' : ''; ?>">
                <?php echo esc_html((string) ($item['label'] ?? '')); ?>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="coffeepos-staff-nav__account">
        <span><?php echo esc_html((string) $user->display_name); ?></span>
        <a href="<?php echo esc_url(wp_logout_url(\CoffeePOS\POS\Router::routeUrl())); ?>"><?php esc_html_e('Sign out', 'coffeepos'); ?></a>
    </div>
</nav>
