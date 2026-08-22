<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="coffeepos-modal coffeepos-confirm-dialog" data-component="confirm-dialog" data-state="closed" data-close-on-backdrop="false" hidden>
    <div class="coffeepos-modal-backdrop"></div>
    <section class="coffeepos-modal-dialog coffeepos-confirm-dialog__panel" role="alertdialog" aria-modal="true" aria-labelledby="coffeepos-confirm-title" aria-describedby="coffeepos-confirm-message" tabindex="-1">
        <span class="coffeepos-confirm-dialog__visual" aria-hidden="true">!</span>
        <h3 id="coffeepos-confirm-title" data-component="confirm-title"><?php esc_html_e('Confirm action', 'coffeepos'); ?></h3>
        <p id="coffeepos-confirm-message" data-component="confirm-message"></p>
        <div class="coffeepos-modal-actions">
            <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="cancel-confirm"><?php esc_html_e('Cancel', 'coffeepos'); ?></button>
            <button type="button" class="coffeepos-btn coffeepos-btn-danger" data-action="confirm-action"><?php esc_html_e('Confirm', 'coffeepos'); ?></button>
        </div>
    </section>
</div>
