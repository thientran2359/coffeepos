<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$emptyTitle = isset($emptyTitle) ? (string) $emptyTitle : __('Nothing here yet', 'coffeepos');
$emptyDescription = isset($emptyDescription) ? (string) $emptyDescription : '';
$emptyAction = isset($emptyAction) ? sanitize_key((string) $emptyAction) : '';
$emptyActionLabel = isset($emptyActionLabel) ? (string) $emptyActionLabel : '';
?>
<div class="coffeepos-empty-state" data-component="empty-state" data-state="empty">
    <span class="coffeepos-empty-state__visual" aria-hidden="true">+</span>
    <strong><?php echo esc_html($emptyTitle); ?></strong>
    <?php if ($emptyDescription !== '') : ?>
        <p><?php echo esc_html($emptyDescription); ?></p>
    <?php endif; ?>
    <?php if ($emptyAction !== '' && $emptyActionLabel !== '') : ?>
        <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="<?php echo esc_attr($emptyAction); ?>">
            <?php echo esc_html($emptyActionLabel); ?>
        </button>
    <?php endif; ?>
</div>
