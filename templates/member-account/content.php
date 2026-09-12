<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

use CoffeePOS\Infrastructure\Settings\Settings;

$logoUrl = Settings::getLogoUrl();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <title><?php esc_html_e('Member account', 'coffeepos'); ?> | <?php echo esc_html(Settings::getStoreName()); ?></title>
    <?php wp_head(); ?>
</head>
<body class="coffeepos coffeepos-screen-member-account">
<main id="coffeepos-app" data-coffeepos-screen="member-account">
    <section class="coffeepos-member-account" data-component="member-account" data-state="loading">
        <header class="coffeepos-member-account__header">
            <div class="coffeepos-member-account__brand">
                <?php if ($logoUrl !== '') : ?><img src="<?php echo esc_url($logoUrl); ?>" alt=""><?php endif; ?>
                <div><span><?php esc_html_e('Member account', 'coffeepos'); ?></span><strong><?php echo esc_html(Settings::getStoreName()); ?></strong></div>
            </div>
            <button type="button" class="coffeepos-btn" data-action="logout" hidden><?php esc_html_e('Log out', 'coffeepos'); ?></button>
        </header>

        <div class="coffeepos-member-account__status" data-component="global-status" role="status" aria-live="polite"></div>

        <section class="coffeepos-member-account__auth-card" data-panel="login" hidden>
            <span class="coffeepos-eyebrow"><?php esc_html_e('Welcome back', 'coffeepos'); ?></span>
            <h1><?php esc_html_e('Sign in to your membership account', 'coffeepos'); ?></h1>
            <p><?php esc_html_e('Use the phone number registered at the store and your six-digit member PIN.', 'coffeepos'); ?></p>
            <form data-component="member-login-form">
                <label><span><?php esc_html_e('Phone number', 'coffeepos'); ?></span><input type="tel" name="phone" autocomplete="tel" maxlength="40" required></label>
                <label><span><?php esc_html_e('Member PIN', 'coffeepos'); ?></span><input type="password" name="pin" inputmode="numeric" autocomplete="current-password" pattern="[0-9]{6}" maxlength="6" required></label>
                <p class="coffeepos-member-account__error" data-component="login-error" role="alert" hidden></p>
                <button class="coffeepos-btn coffeepos-btn-primary" type="submit"><?php esc_html_e('Sign in', 'coffeepos'); ?></button>
            </form>
        </section>

        <section class="coffeepos-member-account__auth-card" data-panel="change-pin" hidden>
            <span class="coffeepos-eyebrow"><?php esc_html_e('Account security', 'coffeepos'); ?></span>
            <h1><?php esc_html_e('Choose a new member PIN', 'coffeepos'); ?></h1>
            <p><?php esc_html_e('Your temporary PIN must be replaced before account information can be shown.', 'coffeepos'); ?></p>
            <form data-component="member-pin-form">
                <label><span><?php esc_html_e('New six-digit PIN', 'coffeepos'); ?></span><input type="password" name="new_pin" inputmode="numeric" autocomplete="new-password" pattern="[0-9]{6}" maxlength="6" required></label>
                <label><span><?php esc_html_e('Confirm new PIN', 'coffeepos'); ?></span><input type="password" name="new_pin_confirmation" inputmode="numeric" autocomplete="new-password" pattern="[0-9]{6}" maxlength="6" required></label>
                <p class="coffeepos-member-account__error" data-component="pin-error" role="alert" hidden></p>
                <button class="coffeepos-btn coffeepos-btn-primary" type="submit"><?php esc_html_e('Save new PIN', 'coffeepos'); ?></button>
                <button class="coffeepos-btn" type="button" data-action="cancel-change-pin" hidden><?php esc_html_e('Cancel', 'coffeepos'); ?></button>
            </form>
        </section>

        <section class="coffeepos-member-account__dashboard" data-panel="dashboard" hidden>
            <section class="coffeepos-member-account__welcome">
                <div><span class="coffeepos-eyebrow"><?php esc_html_e('Hello', 'coffeepos'); ?></span><h1 data-field="member-name"></h1><p data-field="member-phone"></p></div>
                <span class="coffeepos-member-account__tier" data-field="tier-label"></span>
            </section>
            <div class="coffeepos-member-account__grid">
                <section class="coffeepos-member-account__card" aria-labelledby="coffeepos-membership-summary-title">
                    <h2 id="coffeepos-membership-summary-title"><?php esc_html_e('Membership', 'coffeepos'); ?></h2>
                    <dl><div><dt><?php esc_html_e('Lifetime spend', 'coffeepos'); ?></dt><dd data-field="lifetime-spend"></dd></div><div data-component="next-tier-row"><dt><?php esc_html_e('Next tier', 'coffeepos'); ?></dt><dd data-field="next-tier"></dd></div></dl>
                    <div class="coffeepos-member-account__progress" data-component="tier-progress"><span data-component="tier-progress-bar"></span></div>
                    <p data-field="tier-progress-copy"></p>
                </section>
                <section class="coffeepos-member-account__card coffeepos-member-account__orders" aria-labelledby="coffeepos-member-orders-title">
                    <div class="coffeepos-member-account__card-heading"><h2 id="coffeepos-member-orders-title"><?php esc_html_e('Order history', 'coffeepos'); ?></h2><button type="button" class="coffeepos-btn" data-action="refresh-orders"><?php esc_html_e('Refresh', 'coffeepos'); ?></button></div>
                    <p data-component="orders-message" role="status"></p>
                    <div data-component="member-orders"></div>
                    <nav class="coffeepos-member-account__pagination" aria-label="<?php esc_attr_e('Order pages', 'coffeepos'); ?>"><button type="button" class="coffeepos-btn" data-action="previous-orders"><?php esc_html_e('Previous', 'coffeepos'); ?></button><span data-field="order-page"></span><button type="button" class="coffeepos-btn" data-action="next-orders"><?php esc_html_e('Next', 'coffeepos'); ?></button></nav>
                </section>
            </div>
            <button type="button" class="coffeepos-btn" data-action="show-change-pin"><?php esc_html_e('Change PIN', 'coffeepos'); ?></button>
        </section>
    </section>
</main>

<dialog class="coffeepos-member-order-dialog" data-component="member-order-dialog" aria-labelledby="coffeepos-member-order-title">
    <header><div><span data-field="order-status"></span><h2 id="coffeepos-member-order-title" data-field="order-number"></h2></div><button type="button" data-action="close-order" aria-label="<?php esc_attr_e('Close order', 'coffeepos'); ?>">×</button></header>
    <div class="coffeepos-member-order-dialog__body"><p data-field="order-created"></p><div data-component="order-items"></div><dl><div><dt><?php esc_html_e('Subtotal', 'coffeepos'); ?></dt><dd data-field="order-subtotal"></dd></div><div><dt><?php esc_html_e('Discount', 'coffeepos'); ?></dt><dd data-field="order-discount"></dd></div><div><dt><?php esc_html_e('Refunded', 'coffeepos'); ?></dt><dd data-field="order-refunded"></dd></div><div><dt><?php esc_html_e('Total', 'coffeepos'); ?></dt><dd data-field="order-total"></dd></div></dl></div>
</dialog>

<template id="coffeepos-member-order-row"><button type="button" class="coffeepos-member-account__order" data-action="view-order"><span><strong data-field="number"></strong><small data-field="created_at_display"></small><small data-field="item_summary"></small></span><span><small data-field="status_label"></small><strong data-field="total_display"></strong></span></button></template>
<template id="coffeepos-member-order-item"><article class="coffeepos-member-order-dialog__item"><div><strong data-field="name"></strong><small data-field="options"></small></div><span data-field="quantity_display"></span><strong data-field="total_display"></strong></article></template>
<?php wp_footer(); ?>
</body>
</html>
