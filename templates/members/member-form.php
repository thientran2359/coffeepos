<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<form id="<?php echo esc_attr($formId); ?>" class="coffeepos-members__edit-form" method="post" action="<?php echo esc_url(add_query_arg('member_id', (int) $detail['id'], $listUrl)); ?>">
    <?php wp_nonce_field('coffeepos_manage_membership', 'coffeepos_membership_nonce'); ?>
    <input type="hidden" name="customer_id" value="<?php echo esc_attr((string) $detail['id']); ?>">
    <div class="coffeepos-members__edit-grid">
        <label><span><?php esc_html_e('Name', 'coffeepos'); ?></span><input name="member_name" required value="<?php echo esc_attr((string) $detail['name']); ?>"></label>
        <label><span><?php esc_html_e('Phone number', 'coffeepos'); ?></span><input name="member_phone" required value="<?php echo esc_attr((string) $detail['phone']); ?>"></label>
        <label><span><?php esc_html_e('Email', 'coffeepos'); ?></span><input type="email" name="member_email" value="<?php echo esc_attr((string) $detail['email']); ?>"></label>
        <label><span><?php esc_html_e('Member tier', 'coffeepos'); ?></span><select name="member_tier"><option value=""><?php esc_html_e('Automatic tier', 'coffeepos'); ?></option><?php foreach (\CoffeePOS\Integration\WooCommerce\WooCommerceMembershipProvider::tiers() as $tier) : ?><option value="<?php echo esc_attr($tier['code']); ?>" <?php selected((string) $detail['override_code'], $tier['code']); ?>><?php echo esc_html($tier['label']); ?></option><?php endforeach; ?></select></label>
    </div>
    <label class="coffeepos-members__reason"><span><?php esc_html_e('Reason when changing tier', 'coffeepos'); ?></span><input name="member_reason" maxlength="500"></label>
    <div class="coffeepos-members__actions"><button class="coffeepos-btn coffeepos-btn-primary" name="membership_action" value="member-update"><?php esc_html_e('Update member', 'coffeepos'); ?></button></div>
</form>
