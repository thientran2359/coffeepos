<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

?>
<div class="coffeepos-overlay-root" data-component="overlay-root" aria-live="polite">
    <div class="coffeepos-modal" data-component="modal" data-state="closed" hidden>
        <div class="coffeepos-modal-backdrop" data-action="cancel-modal"></div>
        <div class="coffeepos-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-modal-title">
            <h3 id="coffeepos-modal-title" data-component="modal-title"><?php esc_html_e('Modal title', 'coffeepos'); ?></h3>
            <p data-component="modal-message"><?php esc_html_e('Modal message', 'coffeepos'); ?></p>
            <div class="coffeepos-modal-actions">
                <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="cancel-modal">
                    <?php esc_html_e('Cancel', 'coffeepos'); ?>
                </button>
                <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="confirm-modal">
                    <?php esc_html_e('Confirm', 'coffeepos'); ?>
                </button>
            </div>
        </div>
    </div>

    <div class="coffeepos-confirm-dialog" data-component="confirm-dialog" data-state="closed" hidden></div>
    <div class="coffeepos-toast-region" data-component="toast-region" aria-live="polite" aria-atomic="true"></div>
</div>
