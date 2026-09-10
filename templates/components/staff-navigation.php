<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$context = \CoffeePOS\Infrastructure\Templates\TemplateLoader::context();
$currentScreen = (string) ($context['screen'] ?? '');
$items = is_array($context['navigation'] ?? null) ? $context['navigation'] : \CoffeePOS\POS\Router::navigationItems();
$user = wp_get_current_user();
$icons = [
    'cashier' => 'C',
    'kds' => 'K',
    'order-queue' => 'Q',
    'shifts' => 'S',
    'order-history' => 'H',
    'reports' => 'R',
    'members' => 'M',
    'settings' => '⚙',
];
$displayName = (string) $user->display_name;
$userInitial = function_exists('mb_substr') ? mb_substr($displayName, 0, 1) : substr($displayName, 0, 1);
$isCollapsed = \CoffeePOS\Infrastructure\Settings\Settings::isStaffNavCollapsed();
$toggleLabel = $isCollapsed ? __('Expand navigation', 'coffeepos') : __('Collapse navigation', 'coffeepos');
$storeName = \CoffeePOS\Infrastructure\Settings\Settings::getStoreName();
?>
<nav class="coffeepos-staff-nav<?php echo $isCollapsed ? ' is-collapsed' : ''; ?>" data-component="staff-navigation" aria-label="<?php esc_attr_e('CoffeePOS staff navigation', 'coffeepos'); ?>">
    <a class="coffeepos-staff-nav__brand" href="<?php echo esc_url(\CoffeePOS\POS\Router::routeUrl()); ?>" aria-label="<?php esc_attr_e('CoffeePOS home', 'coffeepos'); ?>">
        <span class="coffeepos-staff-nav__brand-mark" aria-hidden="true">CP</span>
        <span class="coffeepos-staff-nav__label"><?php echo esc_html($storeName); ?></span>
    </a>
    <button
        class="coffeepos-staff-nav__toggle"
        type="button"
        data-action="toggle-staff-navigation"
        data-label-collapse="<?php esc_attr_e('Collapse navigation', 'coffeepos'); ?>"
        data-label-expand="<?php esc_attr_e('Expand navigation', 'coffeepos'); ?>"
        aria-controls="coffeepos-staff-navigation-links"
        aria-expanded="<?php echo $isCollapsed ? 'false' : 'true'; ?>"
        aria-label="<?php echo esc_attr($toggleLabel); ?>"
        title="<?php echo esc_attr($toggleLabel); ?>"
    ><span aria-hidden="true"><?php echo $isCollapsed ? '›' : '‹'; ?></span></button>
    <div class="coffeepos-staff-nav__links" id="coffeepos-staff-navigation-links">
        <?php foreach ($items as $item) :
            $itemScreen = (string) ($item['screen'] ?? '');
            $active = $itemScreen === $currentScreen;
            $itemLabel = (string) ($item['label'] ?? '');
            ?>
            <a href="<?php echo esc_url((string) ($item['url'] ?? '')); ?>" aria-label="<?php echo esc_attr($itemLabel); ?>" title="<?php echo esc_attr($itemLabel); ?>"<?php echo $active ? ' aria-current="page"' : ''; ?> class="<?php echo $active ? 'is-active' : ''; ?>">
                <span class="coffeepos-staff-nav__icon" aria-hidden="true"><?php echo esc_html($icons[$itemScreen] ?? strtoupper(substr($itemLabel, 0, 1))); ?></span>
                <span class="coffeepos-staff-nav__label"><?php echo esc_html($itemLabel); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="coffeepos-staff-nav__account">
        <span class="coffeepos-staff-nav__user">
            <span class="coffeepos-staff-nav__avatar" aria-hidden="true"><?php echo esc_html(strtoupper($userInitial)); ?></span>
            <span class="coffeepos-staff-nav__label"><?php echo esc_html($displayName); ?></span>
        </span>
        <a class="coffeepos-staff-nav__logout" href="<?php echo esc_url(wp_logout_url(\CoffeePOS\POS\Router::routeUrl())); ?>" aria-label="<?php esc_attr_e('Sign out', 'coffeepos'); ?>" title="<?php esc_attr_e('Sign out', 'coffeepos'); ?>">
            <span aria-hidden="true">↪</span>
            <span class="coffeepos-staff-nav__label"><?php esc_html_e('Sign out', 'coffeepos'); ?></span>
        </a>
    </div>
</nav>
