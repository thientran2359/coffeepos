<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="coffeepos-toast-region" data-component="toast-region" aria-live="polite" aria-atomic="false"></div>
<template id="coffeepos-toast-template">
    <div class="coffeepos-toast" data-component="toast" data-key="id" data-attr="data-state:type" role="status">
        <span class="coffeepos-toast__mark" aria-hidden="true"></span>
        <span data-field="message"></span>
        <button type="button" class="coffeepos-icon-button" data-action="dismiss-toast" aria-label="<?php esc_attr_e('Dismiss notification', 'coffeepos'); ?>">&#215;</button>
    </div>
</template>
