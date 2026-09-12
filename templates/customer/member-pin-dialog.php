<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}
?>
<dialog class="coffeepos-customer-member-pin" data-component="customer-member-pin-dialog" aria-label="<?php esc_attr_e('Temporary member PIN', 'coffeepos'); ?>">
    <div class="coffeepos-customer-member-pin__body"><p><?php esc_html_e('Use this PIN with your registered phone number. You will choose a new PIN after signing in.', 'coffeepos'); ?></p><output class="coffeepos-customer-member-pin__value" data-field="customer-member-pin"></output></div>
</dialog>
