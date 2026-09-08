<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$product = isset($product) && is_array($product) ? $product : [];
$isInStock = ! empty($product['is_in_stock']);
$isPurchasable = ! empty($product['is_purchasable']);
$isVariable = ! empty($product['is_variable']);
$isDisabled = ! $isInStock || ! $isPurchasable;
$state = $isDisabled ? 'out_of_stock' : 'normal';
$productName = (string) ($product['name'] ?? '');
$visualLabel = '?';
$firstCharacter = [];

if ($productName !== '' && preg_match('/^./u', $productName, $firstCharacter) === 1) {
    $visualLabel = strtoupper($firstCharacter[0]);
}

$imageUrl = (string) ($product['image_url'] ?? '');
?>
<article
    class="coffeepos-product-card"
    data-component="product-card"
    data-product-id="<?php echo esc_attr((string) ($product['id'] ?? '')); ?>"
    data-occurrence-key="<?php echo esc_attr((string) ($product['occurrence_key'] ?? '')); ?>"
    data-state="<?php echo esc_attr($state); ?>"
>
    <button type="button" class="coffeepos-product-card-button" data-action="select-product"<?php disabled($isDisabled); ?> aria-disabled="<?php echo $isDisabled ? 'true' : 'false'; ?>">
        <span class="coffeepos-product-visual" aria-hidden="true">
            <img class="coffeepos-product-image"<?php if ($imageUrl !== '') : ?> src="<?php echo esc_url($imageUrl); ?>"<?php endif; ?> alt="">
            <span class="coffeepos-product-initial"><?php echo esc_html($visualLabel); ?></span>
        </span>
        <span class="coffeepos-product-body">
            <span class="coffeepos-product-topline">
                <strong class="coffeepos-product-name"><?php echo esc_html($productName); ?></strong>
                <?php if (($product['badge_label'] ?? '') !== '') : ?>
                    <span class="coffeepos-product-badge"><?php echo esc_html((string) $product['badge_label']); ?></span>
                <?php endif; ?>
            </span>
            <span class="coffeepos-product-price"><?php echo esc_html((string) ($product['price_display'] ?? '')); ?></span>
            <span class="coffeepos-product-meta">
                <?php echo esc_html($isDisabled ? __('Out of stock', 'coffeepos') : __('In stock', 'coffeepos')); ?>
            </span>
            <?php if ($isVariable) : ?>
                <span class="coffeepos-product-meta"><?php esc_html_e('Options available', 'coffeepos'); ?></span>
            <?php endif; ?>
        </span>
    </button>
    <?php if (current_user_can(\CoffeePOS\Support\Capabilities::MANAGE_STOCK)) : ?>
        <button type="button" class="coffeepos-product-stock-button" data-action="edit-product-stock" aria-label="<?php esc_attr_e('Update stock', 'coffeepos'); ?>" title="<?php esc_attr_e('Update stock', 'coffeepos'); ?>">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5 12 3l8 4.5v9L12 21l-8-4.5v-9Zm8 4.3 6-3.4L12 5 6 8.4l6 3.4Zm-6 5 5 2.8v-6.1l-5-2.8v6.1Zm7 2.8 5-2.8v-6.1l-5 2.8v6.1Z"/></svg>
        </button>
    <?php endif; ?>
</article>
