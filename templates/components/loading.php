<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$loadingVariant = isset($loadingVariant) ? sanitize_key((string) $loadingVariant) : 'component';
$loadingLabel = isset($loadingLabel) ? (string) $loadingLabel : __('Loading...', 'coffeepos');
?>
<div class="coffeepos-loading coffeepos-loading--<?php echo esc_attr($loadingVariant); ?>" data-component="loading" data-variant="<?php echo esc_attr($loadingVariant); ?>" role="status">
    <span class="coffeepos-spinner" aria-hidden="true"></span>
    <span><?php echo esc_html($loadingLabel); ?></span>
</div>
