<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
$list = is_array($data['list'] ?? null) ? $data['list'] : ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0, 'search' => ''];
$detail = is_array($data['detail'] ?? null) ? $data['detail'] : null;
$listUrl = \CoffeePOS\POS\Router::routeUrl('members');
?>
<?php if (! $detail) : ?>
<section class="coffeepos-members__card coffeepos-members__directory">
    <div class="coffeepos-members__card-heading"><div><h2><?php esc_html_e('Member directory', 'coffeepos'); ?></h2><p><?php echo esc_html(sprintf(__('Total: %d members', 'coffeepos'), (int) $list['total'])); ?></p></div></div>
    <form class="coffeepos-members__search" method="get" action="<?php echo esc_url($listUrl); ?>"><label><span class="screen-reader-text"><?php esc_html_e('Search members', 'coffeepos'); ?></span><input type="search" name="member_search" value="<?php echo esc_attr((string) $list['search']); ?>" placeholder="<?php esc_attr_e('Search name, email or phone', 'coffeepos'); ?>"></label><button class="coffeepos-btn"><?php esc_html_e('Search', 'coffeepos'); ?></button></form>
    <?php if ($list['items'] === []) : ?><p class="coffeepos-members__empty"><?php esc_html_e('No members found.', 'coffeepos'); ?></p><?php else : ?>
        <div class="coffeepos-members__list">
        <?php foreach ($list['items'] as $member) : $membership = (array) ($member['membership'] ?? []); $memberUrl = add_query_arg(['member_id' => (int) $member['id'], 'member_page' => (int) $list['page'], 'member_search' => (string) $list['search']], $listUrl); ?>
            <article class="coffeepos-members__member">
                <a class="coffeepos-members__member-main" href="<?php echo esc_url($memberUrl); ?>"><span><strong><?php echo esc_html((string) $member['name']); ?></strong><small><?php echo esc_html((string) $member['phone_masked']); ?><?php echo $member['email'] !== '' ? ' · ' . esc_html((string) $member['email']) : ''; ?></small></span><span class="coffeepos-status-badge"><?php echo esc_html((string) ($membership['tier_label'] ?? __('No tier', 'coffeepos'))); ?></span></a>
                <a class="coffeepos-btn coffeepos-members__quick-edit" href="<?php echo esc_url(add_query_arg('quick_edit', '1', $memberUrl)); ?>" data-action="quick-edit-member" data-member="<?php echo esc_attr(wp_json_encode(['id' => (int) $member['id'], 'name' => (string) $member['name'], 'phone' => (string) $member['phone'], 'email' => (string) $member['email'], 'tier' => (string) $member['override_code']])); ?>"><?php esc_html_e('Quick edit', 'coffeepos'); ?></a>
            </article>
        <?php endforeach; ?>
        </div>
        <nav class="coffeepos-members__pagination" aria-label="<?php esc_attr_e('Member pages', 'coffeepos'); ?>">
            <?php if ((int) $list['page'] > 1) : ?><a class="coffeepos-btn" href="<?php echo esc_url(add_query_arg(['member_page' => (int) $list['page'] - 1, 'member_search' => (string) $list['search']], $listUrl)); ?>"><?php esc_html_e('Previous', 'coffeepos'); ?></a><?php endif; ?>
            <span><?php echo esc_html(sprintf(__('%1$d / %2$d', 'coffeepos'), (int) $list['page'], (int) $list['pages'])); ?></span>
            <?php if ((int) $list['page'] < (int) $list['pages']) : ?><a class="coffeepos-btn" href="<?php echo esc_url(add_query_arg(['member_page' => (int) $list['page'] + 1, 'member_search' => (string) $list['search']], $listUrl)); ?>"><?php esc_html_e('Next', 'coffeepos'); ?></a><?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<dialog class="coffeepos-member-edit-dialog" data-component="member-edit-dialog" aria-labelledby="coffeepos-member-edit-title"><header><div><p><?php esc_html_e('Member', 'coffeepos'); ?></p><h2 id="coffeepos-member-edit-title"><?php esc_html_e('Quick edit', 'coffeepos'); ?></h2></div><button type="button" data-action="close-member-edit" aria-label="<?php esc_attr_e('Close member details', 'coffeepos'); ?>">×</button></header><div class="coffeepos-member-edit-dialog__body">
    <form class="coffeepos-members__edit-form" method="post" action="<?php echo esc_url(add_query_arg(['member_page' => (int) $list['page'], 'member_search' => (string) $list['search']], $listUrl)); ?>" data-component="member-quick-edit-form">
        <?php wp_nonce_field('coffeepos_manage_membership', 'coffeepos_membership_nonce'); ?>
        <input type="hidden" name="customer_id"><input type="hidden" name="return_to_list" value="1">
        <div class="coffeepos-members__edit-grid">
            <label><span><?php esc_html_e('Name', 'coffeepos'); ?></span><input name="member_name" required></label>
            <label><span><?php esc_html_e('Phone number', 'coffeepos'); ?></span><input name="member_phone" required></label>
            <label><span><?php esc_html_e('Email', 'coffeepos'); ?></span><input type="email" name="member_email"></label>
            <label><span><?php esc_html_e('Member tier', 'coffeepos'); ?></span><select name="member_tier"><option value=""><?php esc_html_e('Automatic tier', 'coffeepos'); ?></option><?php foreach (\CoffeePOS\Integration\WooCommerce\WooCommerceMembershipProvider::tiers() as $tier) : ?><option value="<?php echo esc_attr($tier['code']); ?>"><?php echo esc_html($tier['label']); ?></option><?php endforeach; ?></select></label>
        </div>
        <label class="coffeepos-members__reason"><span><?php esc_html_e('Reason when changing tier', 'coffeepos'); ?></span><input name="member_reason" maxlength="500"></label>
        <div class="coffeepos-members__actions"><button class="coffeepos-btn coffeepos-btn-primary" name="membership_action" value="member-update"><?php esc_html_e('Update member', 'coffeepos'); ?></button></div>
    </form>
</div></dialog>
<?php else : $membership = (array) ($detail['membership'] ?? []); ?>
<div class="coffeepos-members__detail-page" data-member-quick-edit="<?php echo isset($_GET['quick_edit']) ? '1' : '0'; ?>">
    <main class="coffeepos-members__card coffeepos-members__orders">
        <div class="coffeepos-members__card-heading"><div><h2><?php esc_html_e('Order history', 'coffeepos'); ?></h2><p><?php echo esc_html((string) $detail['name']); ?></p></div><a class="coffeepos-btn" href="<?php echo esc_url($listUrl); ?>"><?php esc_html_e('Members', 'coffeepos'); ?></a></div>
        <?php if ($detail['orders'] === []) : ?><p class="coffeepos-members__empty"><?php esc_html_e('This member has no CoffeePOS orders.', 'coffeepos'); ?></p><?php else : ?>
        <div class="coffeepos-members__order-list">
            <?php foreach ($detail['orders'] as $order) : ?><button type="button" class="coffeepos-members__order" data-action="view-member-order" data-order-id="<?php echo esc_attr((string) $order['id']); ?>"><span><strong><?php echo esc_html(sprintf(__('Order #%s', 'coffeepos'), $order['number'])); ?></strong><small><?php echo esc_html($order['date']); ?></small></span><span><span class="coffeepos-status-badge"><?php echo esc_html($order['status']); ?></span><b><?php echo esc_html($order['total']); ?></b></span></button><?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
    <aside class="coffeepos-members__card coffeepos-members__profile-widget">
        <div class="coffeepos-members__card-heading"><div><h2><?php echo esc_html((string) $detail['name']); ?></h2><p><?php echo esc_html((string) ($membership['tier_label'] ?? __('No tier', 'coffeepos'))); ?> · <?php echo esc_html((string) $detail['net_spend']); ?></p></div></div>
        <?php $formId = 'coffeepos-member-profile-form'; require COFFEEPOS_PATH . 'templates/members/member-form.php'; ?>
        <section class="coffeepos-members__pin" aria-labelledby="coffeepos-member-pin-title">
            <h3 id="coffeepos-member-pin-title"><?php esc_html_e('Member portal PIN', 'coffeepos'); ?></h3>
            <p data-component="member-pin-status"><?php echo ! empty($detail['portal_pin_configured'])
                ? esc_html(! empty($detail['portal_pin_must_change']) ? __('A temporary PIN is active and must be changed on first login.', 'coffeepos') : __('This member has an active portal PIN.', 'coffeepos'))
                : esc_html__('This member does not have a portal PIN yet.', 'coffeepos'); ?></p>
            <form method="post" action="<?php echo esc_url(add_query_arg('member_id', (int) $detail['id'], $listUrl)); ?>" data-component="member-pin-reset-form">
                <?php wp_nonce_field('coffeepos_manage_membership', 'coffeepos_membership_nonce'); ?>
                <input type="hidden" name="customer_id" value="<?php echo esc_attr((string) $detail['id']); ?>">
                <label class="coffeepos-members__pin-confirm"><input type="checkbox" name="confirm_pin_reset" value="1" required><span><?php esc_html_e('I understand that the current PIN and all member sessions will be replaced.', 'coffeepos'); ?></span></label>
                <button class="coffeepos-btn" name="membership_action" value="member-pin-reset">
                    <?php echo ! empty($detail['portal_pin_configured']) ? esc_html__('Reset temporary PIN', 'coffeepos') : esc_html__('Generate temporary PIN', 'coffeepos'); ?>
                </button>
            </form>
            <small><?php esc_html_e('Generating a PIN signs this member out on every device and clears their failed attempts.', 'coffeepos'); ?></small>
        </section>
    </aside>
</div>
<dialog class="coffeepos-member-pin-dialog" data-component="member-pin-dialog" aria-labelledby="coffeepos-member-pin-dialog-title">
    <header><div><p><?php esc_html_e('Member portal', 'coffeepos'); ?></p><h2 id="coffeepos-member-pin-dialog-title"><?php esc_html_e('Temporary PIN generated', 'coffeepos'); ?></h2></div><button type="button" data-action="close-member-pin" aria-label="<?php esc_attr_e('Close temporary PIN', 'coffeepos'); ?>">×</button></header>
    <div class="coffeepos-member-pin-dialog__body">
        <p><?php esc_html_e('Give this PIN to the member now. It is displayed once and must be changed after first login.', 'coffeepos'); ?></p>
        <output class="coffeepos-member-pin-dialog__value" data-field="temporary-pin" aria-live="polite"></output>
        <p class="coffeepos-inline-error" data-component="member-pin-error" role="alert" hidden></p>
        <div class="coffeepos-member-pin-dialog__actions"><button type="button" class="coffeepos-btn" data-action="show-member-pin-customer"><?php esc_html_e('Show on Customer Display', 'coffeepos'); ?></button><button type="button" class="coffeepos-btn" data-action="copy-member-pin"><?php esc_html_e('Copy PIN', 'coffeepos'); ?></button><button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="close-member-pin"><?php esc_html_e('Done', 'coffeepos'); ?></button></div>
    </div>
</dialog>
<dialog class="coffeepos-member-edit-dialog" data-component="member-edit-dialog" aria-labelledby="coffeepos-member-edit-title"><header><div><p><?php esc_html_e('Member', 'coffeepos'); ?></p><h2 id="coffeepos-member-edit-title"><?php esc_html_e('Quick edit', 'coffeepos'); ?></h2></div><button type="button" data-action="close-member-edit" aria-label="<?php esc_attr_e('Close member details', 'coffeepos'); ?>">×</button></header><div class="coffeepos-member-edit-dialog__body"><?php $formId = 'coffeepos-member-quick-edit-form'; require COFFEEPOS_PATH . 'templates/members/member-form.php'; ?></div></dialog>
<dialog class="coffeepos-history-dialog" data-component="history-detail-dialog" aria-labelledby="coffeepos-member-order-title">
    <header><div><p data-field="detail-status"></p><h2 id="coffeepos-member-order-title" data-field="detail-number"></h2></div><button type="button" data-action="close-order-detail" aria-label="<?php esc_attr_e('Close order detail', 'coffeepos'); ?>">×</button></header>
    <div class="coffeepos-history-detail-meta"><p><span><?php esc_html_e('Created', 'coffeepos'); ?></span><strong data-field="detail-created"></strong></p><p><span><?php esc_html_e('Customer', 'coffeepos'); ?></span><strong data-field="detail-customer"></strong></p><p><span><?php esc_html_e('Service', 'coffeepos'); ?></span><strong data-field="detail-service"></strong></p><p><span><?php esc_html_e('Payment', 'coffeepos'); ?></span><strong data-field="detail-payment"></strong></p></div>
    <div class="coffeepos-history-detail-items" data-component="detail-items"></div>
    <section class="coffeepos-order-note" data-component="history-order-note" hidden><strong><?php esc_html_e('Order note', 'coffeepos'); ?></strong><p data-field="detail-order-note"></p></section>
    <dl class="coffeepos-history-totals"><div><dt><?php esc_html_e('Subtotal', 'coffeepos'); ?></dt><dd data-field="detail-subtotal"></dd></div><div><dt><?php esc_html_e('Discount', 'coffeepos'); ?></dt><dd data-field="detail-discount"></dd></div><div><dt><?php esc_html_e('Refunded', 'coffeepos'); ?></dt><dd data-field="detail-refunded"></dd></div><div class="is-total"><dt><?php esc_html_e('Total', 'coffeepos'); ?></dt><dd data-field="detail-total"></dd></div></dl>
    <p class="coffeepos-inline-error" data-component="detail-error" hidden></p>
</dialog>
<template data-template="history-detail-item"><article class="coffeepos-history-detail-item"><div><strong data-field="name"></strong><small data-field="quick_notes"></small><small data-field="note"></small></div><span data-field="quantity"></span><strong data-field="total"></strong></article></template>
<?php endif; ?>
