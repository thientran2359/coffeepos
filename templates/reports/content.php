<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$cashierUrl = home_url('/' . trim(\CoffeePOS\Infrastructure\Settings\Settings::getPosBaseSlug(), '/') . '/cashier/');
?>
<section class="coffeepos-operations coffeepos-reports" data-component="reports-screen" data-state="loading">
    <header class="coffeepos-operations__header">
        <div>
            <span class="coffeepos-eyebrow"><?php esc_html_e('Operations', 'coffeepos'); ?></span>
            <h1><?php esc_html_e('Reports & Analytics', 'coffeepos'); ?></h1>
        </div>
        <div class="coffeepos-operations__tools"><span data-field="report-range-label" role="status"><?php esc_html_e('Loading report range…', 'coffeepos'); ?></span><a class="coffeepos-btn" href="<?php echo esc_url($cashierUrl); ?>"><?php esc_html_e('Back to Cashier', 'coffeepos'); ?></a></div>
    </header>

    <form class="coffeepos-report-filters" data-component="report-filters">
        <label>
            <?php esc_html_e('Date range', 'coffeepos'); ?>
            <select name="preset">
                <option value="today"><?php esc_html_e('Today', 'coffeepos'); ?></option>
                <option value="yesterday"><?php esc_html_e('Yesterday', 'coffeepos'); ?></option>
                <option value="last_7_days"><?php esc_html_e('Last 7 days', 'coffeepos'); ?></option>
                <option value="current_month"><?php esc_html_e('Current month', 'coffeepos'); ?></option>
                <option value="custom"><?php esc_html_e('Custom range', 'coffeepos'); ?></option>
            </select>
        </label>
        <label data-component="report-custom-from" hidden>
            <?php esc_html_e('From', 'coffeepos'); ?>
            <input type="date" name="date_from">
        </label>
        <label data-component="report-custom-to" hidden>
            <?php esc_html_e('To', 'coffeepos'); ?>
            <input type="date" name="date_to">
        </label>
        <label>
            <?php esc_html_e('Product rows', 'coffeepos'); ?>
            <select name="product_limit">
                <option value="10">10</option>
                <option value="25">25</option>
                <option value="50">50</option>
                <option value="100">100</option>
            </select>
        </label>
        <button class="coffeepos-btn coffeepos-btn-primary" type="submit"><?php esc_html_e('Apply', 'coffeepos'); ?></button>
        <div class="coffeepos-report-export-actions">
            <button class="coffeepos-btn" type="button" data-action="export-report" data-format="csv"><?php esc_html_e('Export CSV', 'coffeepos'); ?></button>
            <button class="coffeepos-btn" type="button" data-action="export-report" data-format="xlsx"><?php esc_html_e('Export Excel', 'coffeepos'); ?></button>
        </div>
    </form>

    <div class="coffeepos-report-status" role="status" aria-live="polite" data-component="report-status"><?php esc_html_e('Loading sales report…', 'coffeepos'); ?></div>
    <p class="coffeepos-inline-error" data-component="report-error" role="alert" hidden></p>
    <div class="coffeepos-report-warnings" data-component="report-warnings" hidden>
        <h2><?php esc_html_e('Data quality notes', 'coffeepos'); ?></h2>
        <ul data-component="report-warning-list"></ul>
    </div>
    <div class="coffeepos-report-groups" data-component="report-groups"></div>
    <div class="coffeepos-report-empty" data-component="report-empty" hidden>
        <h2><?php esc_html_e('No CoffeePOS sales in this range', 'coffeepos'); ?></h2>
        <p><?php esc_html_e('Try another date range. Cancelled, pending, failed, and non-CoffeePOS orders are excluded.', 'coffeepos'); ?></p>
    </div>

    <template id="coffeepos-report-currency-template">
        <article class="coffeepos-report-currency" data-key="currency">
            <header><div><p><?php esc_html_e('Currency', 'coffeepos'); ?></p><h2 data-field="currency"></h2></div><p><span data-field="order_count"></span> <?php esc_html_e('orders', 'coffeepos'); ?></p></header>
            <div class="coffeepos-report-kpis">
                <section><span><?php esc_html_e('Net revenue', 'coffeepos'); ?></span><strong data-field="net_revenue.display"></strong><small><?php esc_html_e('After WooCommerce refunds', 'coffeepos'); ?></small></section>
                <section><span><?php esc_html_e('Orders', 'coffeepos'); ?></span><strong data-field="order_count"></strong><small><?php esc_html_e('Paid CoffeePOS orders', 'coffeepos'); ?></small></section>
                <section><span><?php esc_html_e('Average order value', 'coffeepos'); ?></span><strong data-field="aov.display"></strong><small><?php esc_html_e('Net revenue ÷ orders', 'coffeepos'); ?></small></section>
                <section><span><?php esc_html_e('Products sold', 'coffeepos'); ?></span><strong data-field="products_sold"></strong><small><?php esc_html_e('Net item quantity', 'coffeepos'); ?></small></section>
                <section><span><?php esc_html_e('Refunds', 'coffeepos'); ?></span><strong data-field="refund_total.display"></strong><small><?php esc_html_e('WooCommerce total refunded', 'coffeepos'); ?></small></section>
            </div>
            <div class="coffeepos-report-section-grid">
                <section class="coffeepos-report-panel">
                    <h3><?php esc_html_e('Payment composition', 'coffeepos'); ?></h3>
                    <div class="coffeepos-report-payment-list" data-component="report-payment-list"></div>
                </section>
                <section class="coffeepos-report-panel">
                    <h3><?php esc_html_e('Peak hours', 'coffeepos'); ?></h3>
                    <div class="coffeepos-report-hours" data-component="report-hours"></div>
                </section>
                <section class="coffeepos-report-panel">
                    <h3><?php esc_html_e('Best sellers by revenue', 'coffeepos'); ?></h3>
                    <div class="coffeepos-report-product-list" data-component="report-products-revenue"></div>
                </section>
                <section class="coffeepos-report-panel">
                    <h3><?php esc_html_e('Best sellers by quantity', 'coffeepos'); ?></h3>
                    <div class="coffeepos-report-product-list" data-component="report-products-quantity"></div>
                </section>
            </div>
        </article>
    </template>

    <template id="coffeepos-report-payment-template">
        <div class="coffeepos-report-payment" data-key="method">
            <div><strong data-field="label"></strong><small><span data-field="order_count"></span> <?php esc_html_e('orders', 'coffeepos'); ?></small></div>
            <div><strong data-field="net.display"></strong><small><span data-field="refund.display"></span> <?php esc_html_e('refunded', 'coffeepos'); ?></small></div>
        </div>
    </template>

    <template id="coffeepos-report-product-template">
        <div class="coffeepos-report-product" data-key="key">
            <strong data-field="name"></strong>
            <span>× <span data-field="quantity"></span></span>
            <span data-field="revenue.display"></span>
        </div>
    </template>

    <template id="coffeepos-report-hour-template">
        <div class="coffeepos-report-hour" data-key="key">
            <strong data-field="label"></strong>
            <span><span data-field="order_count"></span> <?php esc_html_e('orders', 'coffeepos'); ?></span>
            <small data-field="net.display"></small>
        </div>
    </template>

    <template id="coffeepos-report-warning-template">
        <li data-field="message"></li>
    </template>
</section>
