<?php

declare(strict_types=1);

if (! defined('ABSPATH')) { exit; }

$context = \CoffeePOS\Infrastructure\Templates\TemplateLoader::context();
$notice = is_array($context['membership_notice'] ?? null) ? $context['membership_notice'] : [];
$data = is_array($context['membership_data'] ?? null) ? $context['membership_data'] : [];
?>
<section class="coffeepos-operations coffeepos-members" data-component="membership-screen">
    <header class="coffeepos-operations__header">
        <div><span class="coffeepos-eyebrow"><?php esc_html_e('Customer management', 'coffeepos'); ?></span><h1><?php esc_html_e('Members', 'coffeepos'); ?></h1></div>
        <div class="coffeepos-operations__tools"><a class="coffeepos-btn" href="<?php echo esc_url(\CoffeePOS\POS\Router::routeUrl('cashier')); ?>"><?php esc_html_e('Back to Cashier', 'coffeepos'); ?></a></div>
    </header>
    <?php if ($notice !== []) : ?>
        <p class="coffeepos-members__notice is-<?php echo esc_attr((string) ($notice['type'] ?? 'info')); ?>" role="<?php echo ($notice['type'] ?? '') === 'error' ? 'alert' : 'status'; ?>"><?php echo esc_html((string) ($notice['message'] ?? '')); ?></p>
        <?php if (! empty($notice['temporary_pin'])) : ?>
            <div class="coffeepos-members__temporary-pin" role="status">
                <span><?php esc_html_e('Temporary PIN', 'coffeepos'); ?></span>
                <strong><?php echo esc_html((string) $notice['temporary_pin']); ?></strong>
                <small><?php esc_html_e('Give this PIN to the member now. It is displayed once and must be changed after first login.', 'coffeepos'); ?></small>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <?php require COFFEEPOS_PATH . 'templates/members/directory.php'; ?>
</section>
