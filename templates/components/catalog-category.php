<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$category = isset($category) && is_array($category) ? $category : [];
$products = isset($category['products']) && is_array($category['products']) ? $category['products'] : [];
$productCount = count($products);
?>
<section
    class="coffeepos-catalog-category"
    data-component="catalog-category-section"
    data-category-id="<?php echo esc_attr((string) ($category['id'] ?? '')); ?>"
>
    <header class="coffeepos-category-heading">
        <h2><?php echo esc_html((string) ($category['name'] ?? '')); ?></h2>
        <span>
            <?php
            echo esc_html(
                sprintf(
                    /* translators: %d: number of products. */
                    _n('%d item', '%d items', $productCount, 'coffeepos'),
                    $productCount
                )
            );
            ?>
        </span>
    </header>
    <div class="coffeepos-product-grid" data-component="catalog-category-products">
        <?php foreach ($products as $product) : ?>
            <?php require COFFEEPOS_PATH . 'templates/components/product-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
