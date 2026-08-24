<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
$membershipEnabled = (bool) \CoffeePOS\Infrastructure\Settings\Settings::get(\CoffeePOS\Infrastructure\Settings\Settings::OPTION_MEMBERSHIP_ENABLED);
$memberCreateEnabled = (bool) \CoffeePOS\Infrastructure\Settings\Settings::get(\CoffeePOS\Infrastructure\Settings\Settings::OPTION_MEMBER_CREATE_ENABLED);
$requiredMemberFields = \CoffeePOS\Infrastructure\Settings\Settings::memberRequiredFields();
?>
<div class="coffeepos-modal" data-component="customer-lookup" data-state="closed" data-membership-enabled="<?php echo $membershipEnabled ? 'true' : 'false'; ?>" data-member-create-enabled="<?php echo $memberCreateEnabled ? 'true' : 'false'; ?>" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-customer-lookup"></div>
    <section class="coffeepos-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-customer-title" tabindex="-1">
        <header class="coffeepos-modal-header">
            <h3 id="coffeepos-customer-title"><?php esc_html_e('Find customer', 'coffeepos'); ?></h3>
            <button type="button" class="coffeepos-icon-button" data-action="close-customer-lookup" aria-label="<?php esc_attr_e('Close customer lookup', 'coffeepos'); ?>">&#215;</button>
        </header>
        <form class="coffeepos-member-lookup-form" data-component="customer-lookup-form">
            <label class="coffeepos-field">
                <span><?php esc_html_e('Phone number', 'coffeepos'); ?></span>
                <input type="tel" data-component="customer-phone-input" autocomplete="tel" inputmode="tel" aria-describedby="coffeepos-customer-lookup-status" required>
            </label>
            <button type="submit" class="coffeepos-btn coffeepos-btn-primary" data-action="lookup-customer"><?php esc_html_e('Search', 'coffeepos'); ?></button>
        </form>
        <p id="coffeepos-customer-lookup-status" data-component="customer-lookup-status" role="status" aria-live="polite"></p>
        <div data-component="customer-lookup-result"></div>
        <section class="coffeepos-member-create" data-component="customer-create" hidden aria-labelledby="coffeepos-member-create-title">
            <h4 id="coffeepos-member-create-title"><?php esc_html_e('Create member', 'coffeepos'); ?></h4>
            <p><?php esc_html_e('No member uses this phone yet. Enter the member details below.', 'coffeepos'); ?></p>
            <form data-component="customer-create-form">
                <label class="coffeepos-field">
                    <span><?php esc_html_e('Phone number', 'coffeepos'); ?></span>
                    <input type="tel" data-component="customer-create-phone" autocomplete="tel" inputmode="tel" required>
                </label>
                <label class="coffeepos-field">
                    <span><?php esc_html_e('Member name', 'coffeepos'); ?></span>
                    <input type="text" data-component="customer-create-name" autocomplete="name" maxlength="200" <?php echo in_array('name', $requiredMemberFields, true) ? 'required' : ''; ?>>
                </label>
                <label class="coffeepos-field">
                    <span><?php echo in_array('email', $requiredMemberFields, true) ? esc_html__('Email', 'coffeepos') : esc_html__('Email (optional)', 'coffeepos'); ?></span>
                    <input type="email" data-component="customer-create-email" autocomplete="email" maxlength="254" <?php echo in_array('email', $requiredMemberFields, true) ? 'required' : ''; ?>>
                </label>
                <button type="submit" class="coffeepos-btn coffeepos-btn-primary" data-action="create-customer"><?php esc_html_e('Create and use member', 'coffeepos'); ?></button>
            </form>
        </section>
    </section>
</div>

<template id="coffeepos-customer-result-template">
    <article class="coffeepos-customer-result" data-key="customer_id" data-attr="data-customer-id:customer_id">
        <strong data-field="display_name"></strong>
        <span data-field="phone"></span>
        <span data-field="email"></span>
        <span data-field="membership_label"></span>
        <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="select-customer" data-attr="data-customer-id:customer_id"><?php esc_html_e('Use member', 'coffeepos'); ?></button>
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
