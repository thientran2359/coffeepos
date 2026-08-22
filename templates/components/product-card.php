<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$product = isset($product) && is_array($product) ? $product : [];
$isInStock = ! empty($product['is_in_stock']);
$isVariable = ! empty($product['is_variable']);
$state = $isInStock ? 'normal' : 'out_of_stock';
?>
<article class="coffeepos-product-card" data-component="product-card" data-product-id="<?php echo esc_attr((string) ($product['id'] ?? '')); ?>" data-state="<?php echo esc_attr($state); ?>">
    <button type="button" class="coffeepos-product-card-button" data-action="select-product"<?php disabled(! $isInStock); ?>>
        <span class="coffeepos-product-name"><?php echo esc_html((string) ($product['name'] ?? '')); ?></span>
        <span class="coffeepos-product-price"><?php echo esc_html((string) ($product['price_display'] ?? '')); ?></span>
        <span class="coffeepos-product-meta">
            <?php
            echo esc_html(
                ! $isInStock
                    ? __('Out of stock', 'coffeepos')
                    : ($isVariable ? __('Variation available', 'coffeepos') : __('In stock', 'coffeepos'))
            );
            ?>
        </span>
    </button>
</article>
