<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<div class="coffeepos-modal" data-component="coupon-selector" data-state="closed" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-coupon-selector"></div>
    <section class="coffeepos-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-coupon-title">
        <header class="coffeepos-modal-header">
            <h3 id="coffeepos-coupon-title"><?php esc_html_e('Apply coupon', 'coffeepos'); ?></h3>
            <button type="button" class="coffeepos-icon-button" data-action="close-coupon-selector" aria-label="<?php esc_attr_e('Close', 'coffeepos'); ?>">&times;</button>
        </header>
        <form data-component="coupon-form" class="coffeepos-payment-form">
            <label for="coffeepos-coupon-code"><?php esc_html_e('Coupon code', 'coffeepos'); ?></label>
            <div class="coffeepos-inline-form">
                <input id="coffeepos-coupon-code" name="coupon_code" autocomplete="off" maxlength="100">
                <button type="submit" class="coffeepos-btn coffeepos-btn-primary" data-action="apply-coupon"><?php esc_html_e('Apply', 'coffeepos'); ?></button>
            </div>
        </form>
        <p data-component="coupon-status" role="status"></p>
        <div data-component="coupon-list" class="coffeepos-coupon-list"></div>
    </section>
</div>
<template id="coffeepos-coupon-option-template">
    <button type="button" class="coffeepos-btn coffeepos-btn-light" data-component="coupon-option" data-action="apply-coupon-option" data-attr="data-coupon-code:code">
        <strong data-field="label"></strong>
    </button>
</template>
