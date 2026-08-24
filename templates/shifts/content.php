<?php
declare(strict_types=1);
if (! defined('ABSPATH')) { exit; }
?>
<section class="coffeepos-operations coffeepos-shifts" data-component="shifts-screen" data-state="loading">
    <header class="coffeepos-operations__header">
        <div><span class="coffeepos-eyebrow"><?php esc_html_e('Operations', 'coffeepos'); ?></span><h1><?php esc_html_e('Shift Management', 'coffeepos'); ?></h1></div>
        <div class="coffeepos-operations__tools"><a class="coffeepos-btn" href="<?php echo esc_url(home_url('/' . trim(\CoffeePOS\Infrastructure\Settings\Settings::getPosBaseSlug(), '/') . '/cashier/')); ?>"><?php esc_html_e('Back to Cashier', 'coffeepos'); ?></a></div>
    </header>
    <p class="coffeepos-inline-error" data-component="shift-error" hidden></p>

    <section class="coffeepos-shift-card" data-component="shift-open-panel" hidden>
        <h2><?php esc_html_e('Open shift', 'coffeepos'); ?></h2>
        <p><?php esc_html_e('Count the cash drawer before accepting the first order.', 'coffeepos'); ?></p>
        <form data-component="open-shift-form">
            <label><?php esc_html_e('Opening cash', 'coffeepos'); ?><input name="opening_cash" inputmode="decimal" required value="0"></label>
            <label><?php esc_html_e('Opening note', 'coffeepos'); ?><textarea name="opening_note" maxlength="2000"></textarea></label>
            <button class="coffeepos-btn coffeepos-btn-primary" type="submit"><?php esc_html_e('Open shift', 'coffeepos'); ?></button>
        </form>
    </section>

    <section class="coffeepos-shift-card" data-component="shift-active-panel" hidden>
        <div class="coffeepos-shift-title"><div><p><?php esc_html_e('Active shift', 'coffeepos'); ?></p><h2 data-field="shift-number"></h2></div><span class="coffeepos-status-badge"><?php esc_html_e('Open', 'coffeepos'); ?></span></div>
        <p data-field="shift-meta"></p>
        <div class="coffeepos-shift-metrics">
            <div><span><?php esc_html_e('Opening cash', 'coffeepos'); ?></span><strong data-field="opening-cash"></strong></div>
            <div><span><?php esc_html_e('Cash sales', 'coffeepos'); ?></span><strong data-field="cash-sales"></strong></div>
            <div><span><?php esc_html_e('Bank transfer', 'coffeepos'); ?></span><strong data-field="bank-sales"></strong></div>
            <div><span><?php esc_html_e('Total sales', 'coffeepos'); ?></span><strong data-field="total-sales"></strong></div>
            <div><span><?php esc_html_e('Expected cash', 'coffeepos'); ?></span><strong data-field="expected-cash"></strong></div>
            <div><span><?php esc_html_e('Orders', 'coffeepos'); ?></span><strong data-field="order-count"></strong></div>
        </div>
        <form data-component="close-shift-form">
            <label><?php esc_html_e('Actual cash', 'coffeepos'); ?><input name="actual_cash" inputmode="decimal" required></label>
            <label><?php esc_html_e('Closing note', 'coffeepos'); ?><textarea name="closing_note" maxlength="2000"></textarea></label>
            <button class="coffeepos-btn coffeepos-btn-danger" type="submit"><?php esc_html_e('Close and reconcile shift', 'coffeepos'); ?></button>
        </form>
    </section>

    <section class="coffeepos-shift-card">
        <h2><?php esc_html_e('Shift history', 'coffeepos'); ?></h2>
        <p data-component="shift-history-empty" hidden><?php esc_html_e('No closed shifts yet.', 'coffeepos'); ?></p>
        <div class="coffeepos-shift-history" data-component="shift-history"></div>
    </section>

    <template data-template="shift-history-row"><article class="coffeepos-shift-history-row"><div><strong data-field="number"></strong><span data-field="period"></span></div><div><span><?php esc_html_e('Sales', 'coffeepos'); ?></span><strong data-field="sales"></strong></div><div><span><?php esc_html_e('Expected', 'coffeepos'); ?></span><strong data-field="expected"></strong></div><div><span><?php esc_html_e('Actual', 'coffeepos'); ?></span><strong data-field="actual"></strong></div><div><span><?php esc_html_e('Variance', 'coffeepos'); ?></span><strong data-field="variance"></strong></div></article></template>
</section>
