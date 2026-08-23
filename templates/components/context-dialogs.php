<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="coffeepos-modal" data-component="customer-lookup" data-state="closed" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-customer-lookup"></div>
    <section class="coffeepos-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-customer-title" tabindex="-1">
        <header class="coffeepos-modal-header">
            <h3 id="coffeepos-customer-title"><?php esc_html_e('Find customer', 'coffeepos'); ?></h3>
            <button type="button" class="coffeepos-icon-button" data-action="close-customer-lookup" aria-label="<?php esc_attr_e('Close customer lookup', 'coffeepos'); ?>">&#215;</button>
        </header>
        <form data-component="customer-lookup-form">
            <label class="coffeepos-field">
                <span><?php esc_html_e('Phone number', 'coffeepos'); ?></span>
                <input type="tel" data-component="customer-phone-input" autocomplete="tel" required>
            </label>
            <button type="submit" class="coffeepos-btn coffeepos-btn-primary" data-action="lookup-customer"><?php esc_html_e('Search', 'coffeepos'); ?></button>
        </form>
        <p data-component="customer-lookup-status" role="status"></p>
        <div data-component="customer-lookup-result"></div>
    </section>
</div>

<template id="coffeepos-customer-result-template">
    <article class="coffeepos-customer-result" data-key="customer_id" data-attr="data-customer-id:customer_id">
        <strong data-field="display_name"></strong>
        <span data-field="phone"></span>
        <span data-field="membership_label"></span>
        <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="select-customer" data-attr="data-customer-id:customer_id"><?php esc_html_e('Select customer', 'coffeepos'); ?></button>
    </article>
</template>

<div class="coffeepos-modal" data-component="table-selector" data-state="closed" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-table-selector"></div>
    <section class="coffeepos-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-table-title" tabindex="-1">
        <header class="coffeepos-modal-header">
            <h3 id="coffeepos-table-title"><?php esc_html_e('Select table', 'coffeepos'); ?></h3>
            <button type="button" class="coffeepos-icon-button" data-action="close-table-selector" aria-label="<?php esc_attr_e('Close table selector', 'coffeepos'); ?>">&#215;</button>
        </header>
        <p data-component="table-selector-status" role="status"></p>
        <div class="coffeepos-table-list" data-component="table-list"></div>
    </section>
</div>

<template id="coffeepos-table-option-template">
    <button type="button" class="coffeepos-btn coffeepos-btn-light coffeepos-table-option" data-component="table-option" data-action="select-table" data-key="id" data-attr="data-table-id:id">
        <span data-field="label"></span>
    </button>
</template>
