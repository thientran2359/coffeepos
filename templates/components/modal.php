<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<div class="coffeepos-modal" data-component="modal" data-state="closed" data-close-on-backdrop="true" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-modal"></div>
    <section class="coffeepos-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="coffeepos-modal-title" tabindex="-1">
        <header class="coffeepos-modal-header">
            <h3 id="coffeepos-modal-title" data-component="modal-title"><?php esc_html_e('Details', 'coffeepos'); ?></h3>
            <button type="button" class="coffeepos-icon-button" data-action="close-modal" aria-label="<?php esc_attr_e('Close dialog', 'coffeepos'); ?>">&#215;</button>
        </header>
        <div class="coffeepos-modal-body">
            <p data-component="modal-message"></p>
            <div data-component="modal-loading" hidden>
                <?php
                $loadingVariant = 'inline';
                $loadingLabel = __('Working...', 'coffeepos');
                require COFFEEPOS_PATH . 'templates/components/loading.php';
                ?>
            </div>
            <div data-component="modal-error" hidden>
                <?php
                $errorMessage = __('The action could not be completed.', 'coffeepos');
                $errorAction = '';
                require COFFEEPOS_PATH . 'templates/components/error-state.php';
                ?>
            </div>
        </div>
        <footer class="coffeepos-modal-actions">
            <button type="button" class="coffeepos-btn coffeepos-btn-light" data-action="cancel-modal"><?php esc_html_e('Cancel', 'coffeepos'); ?></button>
            <button type="button" class="coffeepos-btn coffeepos-btn-primary" data-action="confirm-modal"><?php esc_html_e('Confirm', 'coffeepos'); ?></button>
        </footer>
    </section>
</div>
