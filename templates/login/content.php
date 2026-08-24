<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$context = \CoffeePOS\Infrastructure\Templates\TemplateLoader::context();
$error = (string) ($context['error'] ?? '');
$noAccess = ! empty($context['no_access']);
$redirectTo = (string) ($context['redirect_to'] ?? '');
$storeName = \CoffeePOS\Infrastructure\Settings\Settings::getStoreName();
$logoUrl = \CoffeePOS\Infrastructure\Settings\Settings::getLogoUrl();
?>
<section class="coffeepos-login" data-component="staff-login" data-state="<?php echo $noAccess ? 'no_access' : ($error !== '' ? 'error' : 'idle'); ?>">
    <div class="coffeepos-login__card">
        <header>
            <?php if ($logoUrl !== '') : ?><img class="coffeepos-store-logo" src="<?php echo esc_url($logoUrl); ?>" alt=""><?php endif; ?>
            <p class="coffeepos-eyebrow"><?php esc_html_e('CoffeePOS staff', 'coffeepos'); ?></p>
            <h1><?php echo esc_html($storeName); ?></h1>
        </header>

        <?php if ($noAccess) : ?>
            <div class="coffeepos-login__notice" role="alert">
                <h2><?php esc_html_e('No CoffeePOS access', 'coffeepos'); ?></h2>
                <p><?php esc_html_e('Your WordPress account is signed in but has no CoffeePOS screen permission. Contact an administrator.', 'coffeepos'); ?></p>
            </div>
            <a class="coffeepos-btn coffeepos-btn-primary" href="<?php echo esc_url(wp_logout_url(\CoffeePOS\POS\Router::routeUrl())); ?>"><?php esc_html_e('Sign out', 'coffeepos'); ?></a>
        <?php else : ?>

            <?php if ($error !== '') : ?>
                <p class="coffeepos-login__error" role="alert"><?php echo esc_html($error); ?></p>
            <?php endif; ?>

            <form method="post" action="<?php echo esc_url(\CoffeePOS\POS\Router::routeUrl()); ?>" data-component="staff-login-form">
                <?php wp_nonce_field('coffeepos_staff_login', 'coffeepos_nonce'); ?>
                <input type="hidden" name="redirect_to" value="<?php echo esc_attr($redirectTo); ?>">
                <label>
                    <span><?php esc_html_e('Username or email', 'coffeepos'); ?></span>
                    <input type="text" name="log" autocomplete="username" required autofocus>
                </label>
                <label>
                    <span><?php esc_html_e('Password', 'coffeepos'); ?></span>
                    <input type="password" name="pwd" autocomplete="current-password" required>
                </label>
                <label class="coffeepos-login__remember">
                    <input type="checkbox" name="rememberme" value="1">
                    <span><?php esc_html_e('Remember me', 'coffeepos'); ?></span>
                </label>
                <button class="coffeepos-btn coffeepos-btn-primary" type="submit"><?php esc_html_e('Sign in to CoffeePOS', 'coffeepos'); ?></button>
            </form>
        <?php endif; ?>
    </div>
</section>
