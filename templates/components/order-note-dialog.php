<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

?>
<div class="coffeepos-modal coffeepos-order-note-dialog" data-component="order-note-dialog" data-state="closed" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-order-note"></div>
    <section class="coffeepos-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-order-note-title" tabindex="-1">
        <header class="coffeepos-modal-header">
            <div>
                <span class="coffeepos-eyebrow"><?php esc_html_e('Current order', 'coffeepos'); ?></span>
                <h3 id="coffeepos-order-note-title"><?php esc_html_e('Order note', 'coffeepos'); ?></h3>
            </div>
            <button type="button" class="coffeepos-icon-button" data-action="close-order-note" aria-label="<?php esc_attr_e('Close order note', 'coffeepos'); ?>">&times;</button>
        </header>
        <label class="coffeepos-field" for="coffeepos-order-note">
            <span><?php esc_html_e('Private note for the whole order', 'coffeepos'); ?></span>
            <textarea id="coffeepos-order-note" data-component="order-note-input" maxlength="2000" rows="6" placeholder="<?php esc_attr_e('For example: serve together, pack separately…', 'coffeepos'); ?>"></textarea>
        </label>
        <small data-component="order-note-status" role="status" aria-live="polite"></small>
        <footer class="coffeepos-modal-actions">
            <button type="button" class="coffeepos-link-button" data-action="clear-order-note"><?php esc_html_e('Clear', 'coffeepos'); ?></button>
            <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="close-order-note"><?php esc_html_e('Close', 'coffeepos'); ?></button>
            <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="save-order-note"><?php esc_html_e('Save note', 'coffeepos'); ?></button>
        </footer>
    </section>
</div>
