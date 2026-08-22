<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<aside class="coffeepos-drawer" data-component="drawer" data-state="closed" aria-hidden="true" hidden>
    <div class="coffeepos-modal-backdrop" data-action="close-drawer"></div>
    <section class="coffeepos-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="coffeepos-drawer-title" tabindex="-1">
        <header class="coffeepos-modal-header">
            <h3 id="coffeepos-drawer-title" data-component="drawer-title"><?php esc_html_e('Panel', 'coffeepos'); ?></h3>
            <button type="button" class="coffeepos-icon-button" data-action="close-drawer" aria-label="<?php esc_attr_e('Close panel', 'coffeepos'); ?>">&#215;</button>
        </header>
        <div data-component="drawer-content"></div>
    </section>
</aside>
