<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$errorMessage = isset($errorMessage) ? (string) $errorMessage : __('Something went wrong.', 'coffeepos');
$errorAction = isset($errorAction) ? sanitize_key((string) $errorAction) : '';
?>
<div class="coffeepos-error-state" data-component="error-state" data-state="error" role="alert">
    <strong><?php esc_html_e('Unable to continue', 'coffeepos'); ?></strong>
    <p><?php echo esc_html($errorMessage); ?></p>
    <?php if ($errorAction !== '') : ?>
        <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="<?php echo esc_attr($errorAction); ?>">
            <?php esc_html_e('Retry', 'coffeepos'); ?>
        </button>
    <?php endif; ?>
</div>
