<?php

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

$category = isset($category) && is_array($category) ? $category : [];
?>
<button type="button" class="coffeepos-chip" data-action="select-category" data-category-id="<?php echo esc_attr((string) ($category['id'] ?? '')); ?>" aria-pressed="false">
    <?php echo esc_html((string) ($category['name'] ?? '')); ?>
</button>
