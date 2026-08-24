<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
$storeName = \CoffeePOS\Infrastructure\Settings\Settings::getStoreName();
$branchName = trim((string) \CoffeePOS\Infrastructure\Settings\Settings::get(\CoffeePOS\Infrastructure\Settings\Settings::OPTION_BRANCH_NAME));
$logoUrl = \CoffeePOS\Infrastructure\Settings\Settings::getLogoUrl();
?>
<section class="coffeepos-customer-menu" data-component="customer-menu" data-state="loading">
    <header class="coffeepos-customer-menu__header">
        <div class="coffeepos-customer-brand"><?php if ($logoUrl !== '') : ?><img class="coffeepos-store-logo" src="<?php echo esc_url($logoUrl); ?>" alt=""><?php endif; ?><div><span class="coffeepos-eyebrow"><?php esc_html_e('Welcome to', 'coffeepos'); ?></span><h1><?php echo esc_html($storeName); ?></h1><?php if ($branchName !== '') : ?><small><?php echo esc_html($branchName); ?></small><?php endif; ?></div></div>
        <div data-component="customer-connection" data-state="connecting"><?php esc_html_e('Connecting display…', 'coffeepos'); ?></div>
    </header>
    <div class="coffeepos-customer-catalog" data-component="catalog-scroll" tabindex="0">
        <div data-component="customer-catalog-sections"></div>
        <div data-component="customer-catalog-status" role="status"><?php esc_html_e('Loading menu…', 'coffeepos'); ?></div>
        <button type="button" class="coffeepos-btn" data-action="retry-customer-catalog" hidden><?php esc_html_e('Retry menu', 'coffeepos'); ?></button>
    </div>
</section>
